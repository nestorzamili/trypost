import { useChat } from '@ai-sdk/vue';
import {
    DefaultChatTransport,
    isTextUIPart,
    isToolUIPart,
    lastAssistantMessageIsCompleteWithApprovalResponses,
    type ChatOnErrorCallback,
    type ChatOnFinishCallback,
    type UIMessage,
} from 'ai';
import { trans } from 'laravel-vue-i18n';

import {
    cancel,
    store,
} from '@/actions/App/Http/Controllers/App/ChatMessageController';

/**
 * One approval decision for a paused tool call, keyed by its call id when
 * submitted through `submitDecisions`. Mirrors the shape
 * `Laravel\Ai\Approvals\Decision` accepts on the wire — `result` only makes
 * sense alongside a `reject`, matching `Decision::reject(?string $result)`.
 */
export interface ChatDecision {
    action: 'approve' | 'reject';
    result?: string;
}

/**
 * Read the URL-decoded XSRF-TOKEN cookie so it can be echoed back as the
 * X-XSRF-TOKEN header. Without it Laravel's CSRF middleware answers 419.
 */
const readXsrfToken = (): string | null => {
    const match = document.cookie
        .split(';')
        .map((row) => row.trim())
        .find((row) => row.startsWith('XSRF-TOKEN='));

    return match === undefined
        ? null
        : decodeURIComponent(match.slice('XSRF-TOKEN='.length));
};

const requestHeaders = (): Record<string, string> => {
    const headers: Record<string, string> = {
        Accept: 'text/event-stream, application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const token = readXsrfToken();

    if (token !== null) {
        headers['X-XSRF-TOKEN'] = token;
    }

    return headers;
};

/**
 * Extract the newest user prompt so the request body carries only that —
 * never the client's transcript. Conversation history lives server-side in
 * WorkspaceConversationStore, and sending it from the client would be a trust
 * boundary this endpoint deliberately avoids.
 *
 * Scans back for the newest *user* message rather than blindly taking the
 * tail: after a failed or aborted turn the tail can be a partial assistant
 * message, and `regenerate()` must resend the prompt, not the fragment.
 */
const latestMessageText = (messages: UIMessage[]): string => {
    for (let index = messages.length - 1; index >= 0; index--) {
        const message = messages[index];

        if (message?.role === 'user') {
            return (
                message.parts
                    .filter(isTextUIPart)
                    .map((part) => part.text)
                    .join('') ?? ''
            );
        }
    }

    return '';
};

/**
 * Build the `decisions` payload from the newest message's current step, if
 * that message is the assistant's own paused turn rather than a fresh user
 * prompt. Restricted to parts after the last `step-start` marker — the same
 * scope `lastAssistantMessageIsCompleteWithApprovalResponses` uses to decide
 * whether to auto-resend — so a decision already resolved in an earlier step
 * of the same message is never resubmitted.
 */
const pendingDecisions = (
    messages: UIMessage[],
): Record<string, ChatDecision> | null => {
    const last = messages[messages.length - 1];

    if (last === undefined || last.role !== 'assistant') {
        return null;
    }

    const lastStepStartIndex = last.parts.reduce(
        (index, part, currentIndex) =>
            part.type === 'step-start' ? currentIndex : index,
        -1,
    );

    const decisions: Record<string, ChatDecision> = {};

    for (const part of last.parts.slice(lastStepStartIndex + 1)) {
        if (isToolUIPart(part) && part.state === 'approval-responded') {
            decisions[part.approval.id] = part.approval.approved
                ? { action: 'approve' }
                : { action: 'reject', result: part.approval.reason };
        }
    }

    return Object.keys(decisions).length > 0 ? decisions : null;
};

/**
 * A failed chat request, carrying the HTTP status alongside the localized
 * message so a consuming component can branch on which failure this is
 * (402 out of AI credits — show a billing CTA; 409 a turn is already
 * streaming — recoverable by waiting; 403/404 someone else's or a deleted
 * conversation) without parsing the message text, which is locale-dependent.
 */
export class ChatRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'ChatRequestError';
    }
}

/**
 * Read a translated `message` out of a non-2xx JSON error body — the shape
 * every abort()/gate response on this route uses (402 out of AI credits, 403
 * someone else's conversation, 404 deleted, 409 turn already in progress) —
 * instead of surfacing the raw response body as the error text.
 */
const parseErrorMessage = async (response: Response): Promise<string> => {
    const body: unknown = await response.json().catch(() => null);

    if (body !== null && typeof body === 'object' && 'message' in body) {
        const { message } = body as { message: unknown };

        if (typeof message === 'string' && message !== '') {
            return message;
        }
    }

    return trans('chat.errors.request_failed');
};

const fetchWithReadableErrors: typeof fetch = async (input, init) => {
    const response = await fetch(input, init);

    if (!response.ok) {
        throw new ChatRequestError(
            await parseErrorMessage(response),
            response.status,
        );
    }

    return response;
};

/**
 * Consume the chat SSE stream for one workspace conversation.
 *
 * The backend accepts `message` XOR `decisions` (never both) at
 * `POST /chat/{conversation}`, streamed back over the Vercel AI SDK UI
 * message protocol. This composable reshapes `useChat`'s default
 * `{ messages }` request body into that contract via a custom transport, and
 * resumes a run paused on a `tool-approval-request` part through
 * `submitDecisions`, which drives the SDK's own `addToolApprovalResponse` so
 * the resend replays through the same stream-processing pipeline as a normal
 * turn. Request failures surface as `ChatRequestError` on `error`, carrying
 * the HTTP status for callers that need to branch on it.
 *
 * Optional `onFinish`/`onError` callbacks are forwarded to `useChat`.
 * `cancelTurn` releases the server-side turn claim (`POST .../cancel`) for
 * use after `stop()` and on failures the server never cleans up itself.
 */
export const useConversationChat = (
    conversationId: string,
    initialMessages: UIMessage[] = [],
    callbacks: {
        onFinish?: ChatOnFinishCallback<UIMessage>;
        onError?: ChatOnErrorCallback;
    } = {},
) => {
    const transport = new DefaultChatTransport<UIMessage>({
        api: store.url({ conversation: conversationId }),
        credentials: 'same-origin',
        fetch: fetchWithReadableErrors,
        prepareSendMessagesRequest: ({ messages }) => {
            const decisions = pendingDecisions(messages);

            return {
                body:
                    decisions !== null
                        ? { decisions }
                        : { message: latestMessageText(messages) },
                headers: requestHeaders(),
            };
        },
    });

    const chat = useChat({
        transport,
        messages: initialMessages,
        sendAutomaticallyWhen:
            lastAssistantMessageIsCompleteWithApprovalResponses,
        onFinish: callbacks.onFinish,
        onError: callbacks.onError,
    });

    /**
     * Resolve one or more paused tool approvals. Each decision is recorded
     * locally through `addToolApprovalResponse`; once every pending approval
     * in the current step has a decision, `sendAutomaticallyWhen` fires the
     * resend automatically — which posts `{ decisions }` to the same route
     * as a normal message, via the transport's `prepareSendMessagesRequest`
     * above.
     */
    const submitDecisions = async (
        decisions: Record<string, ChatDecision>,
    ): Promise<void> => {
        for (const [id, decision] of Object.entries(decisions)) {
            await chat.addToolApprovalResponse({
                id,
                approved: decision.action === 'approve',
                reason: decision.result,
            });
        }
    };

    /**
     * Release the server-side turn claim for this conversation. The claim
     * only clears itself on a clean stream end, so call this after `stop()`
     * and best-effort on non-409 failures — otherwise the conversation stays
     * locked behind a 409 that is no longer true. Never throws: releasing is
     * a courtesy, and a failed release must not mask the error that caused it.
     */
    const cancelTurn = async (): Promise<void> => {
        try {
            await fetch(cancel.url({ conversation: conversationId }), {
                method: 'POST',
                credentials: 'same-origin',
                headers: requestHeaders(),
            });
        } catch {
            // Intentionally ignored — see above.
        }
    };

    return { ...chat, submitDecisions, cancelTurn };
};
