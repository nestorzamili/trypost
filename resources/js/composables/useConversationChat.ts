import { useChat } from '@ai-sdk/vue';
import {
    DefaultChatTransport,
    isTextUIPart,
    isToolUIPart,
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
 * Tool part states that end a step's need to wait: the call produced output,
 * was denied, or carries an approval decision ready to be submitted. Mirrors
 * the SDK's own auto-send guard, so a resume fires only once every approval
 * in the current step has been answered.
 */
const SETTLED_TOOL_STATES = [
    'output-available',
    'output-error',
    'output-denied',
    'approval-responded',
];

/**
 * Build the `decisions` payload from the newest message's current step, if
 * that message is the assistant's own paused turn rather than a fresh user
 * prompt. Restricted to parts after the last `step-start` marker — the same
 * scope the SDK's auto-send helper uses — so a decision already resolved in
 * an earlier step of the same message is never resubmitted.
 *
 * Decisions the client has already sent (`submitted`) are skipped: an
 * `approval-responded` part is terminal history — the approval continuation
 * runs inside the same step, so no new `step-start` ever arrives to move it
 * out of scope. Without this, every state change after an approval turn
 * (including the automatic check that runs when the turn's own stream ends)
 * would resubmit the same decisions, and the server would answer each replay
 * with an approval-mismatch failure while pointlessly reclaiming the turn.
 */
const pendingDecisions = (
    messages: UIMessage[],
    submitted: ReadonlySet<string>,
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

    const stepTools = last.parts
        .slice(lastStepStartIndex + 1)
        .filter(isToolUIPart);

    if (
        stepTools.length === 0 ||
        !stepTools.every((part) => SETTLED_TOOL_STATES.includes(part.state))
    ) {
        return null;
    }

    const decisions: Record<string, ChatDecision> = {};

    for (const part of stepTools) {
        if (part.state !== 'approval-responded') {
            continue;
        }

        const id = part.approval?.id;

        if (typeof id !== 'string' || id === '' || submitted.has(id)) {
            continue;
        }

        decisions[id] = part.approval.approved
            ? { action: 'approve' }
            : { action: 'reject', result: part.approval.reason };
    }

    return Object.keys(decisions).length > 0 ? decisions : null;
};

/**
 * A failed chat request, carrying the HTTP status alongside the localized
 * message so a consuming component can branch on which failure this is
 * (402 out of AI credits — show a billing CTA; 409 a turn is already
 * streaming — recoverable by waiting; 403/404 someone else's or a deleted
 * conversation) without parsing the message text, which is locale-dependent.
 *
 * `code` carries the machine-readable `code` the backend attaches to failures
 * that need programmatic healing rather than an error banner — currently only
 * `decisions_resolved`, when a resume replays approvals the server already
 * settled. Absent on every other failure, including validation 422s, which
 * carry `errors` instead.
 */
export class ChatRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly code: string | null = null,
    ) {
        super(message);
        this.name = 'ChatRequestError';
    }
}

/**
 * Read a translated `message` out of a non-2xx JSON error body — the shape
 * every abort()/gate response on this route uses (402 out of AI credits, 403
 * someone else's conversation, 404 deleted, 409 turn already in progress) —
 * instead of surfacing the raw response body as the error text. Also lifts
 * the optional machine-readable `code` for healable failures.
 */
const parseError = async (
    response: Response,
): Promise<{ message: string; code: string | null }> => {
    const body: unknown = await response.json().catch(() => null);

    if (body !== null && typeof body === 'object' && 'message' in body) {
        const { message, code } = body as {
            message: unknown;
            code?: unknown;
        };

        if (typeof message === 'string' && message !== '') {
            return {
                message,
                code: typeof code === 'string' && code !== '' ? code : null,
            };
        }
    }

    return { message: trans('chat.errors.request_failed'), code: null };
};

const fetchWithReadableErrors: typeof fetch = async (input, init) => {
    const response = await fetch(input, init);

    if (!response.ok) {
        const { message, code } = await parseError(response);

        throw new ChatRequestError(message, response.status, code);
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
 * `absorbResolvedDecisions` marks the tail's answered approvals as submitted
 * without sending anything, for healing a `decisions_resolved` rejection.
 */
export const useConversationChat = (
    conversationId: string,
    initialMessages: UIMessage[] = [],
    callbacks: {
        onFinish?: ChatOnFinishCallback<UIMessage>;
        onError?: ChatOnErrorCallback;
    } = {},
) => {
    /**
     * Approval ids already carried to the server. An `approval-responded`
     * part never leaves the tail's last step — the approval continuation
     * streams into the same step — so without this record every later send
     * (including the SDK's own post-turn auto-send check) would replay the
     * same decisions and the server would reject each replay.
     */
    const submittedDecisionIds = new Set<string>();

    const transport = new DefaultChatTransport<UIMessage>({
        api: store.url({ conversation: conversationId }),
        credentials: 'same-origin',
        fetch: fetchWithReadableErrors,
        prepareSendMessagesRequest: ({ messages }) => {
            const decisions = pendingDecisions(messages, submittedDecisionIds);

            if (decisions !== null) {
                for (const id of Object.keys(decisions)) {
                    submittedDecisionIds.add(id);
                }

                return { body: { decisions }, headers: requestHeaders() };
            }

            return {
                body: { message: latestMessageText(messages) },
                headers: requestHeaders(),
            };
        },
    });

    const chat = useChat({
        transport,
        messages: initialMessages,
        // The SDK's own helper cannot tell a freshly answered approval from
        // one submitted turns ago — both read `approval-responded` — so it
        // would auto-resend settled decisions after every approval turn. This
        // predicate only fires while an answer is still unsent.
        sendAutomaticallyWhen: ({ messages }) =>
            pendingDecisions(messages, submittedDecisionIds) !== null,
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
     * Mark the tail's answered approvals as submitted without sending
     * anything. For healing a `decisions_resolved` rejection: the server
     * reports those approvals already settled, so resending them would only
     * fail again — recording them stops the auto-send check from retrying,
     * and the caller clears the error banner since nothing was actually lost.
     */
    const absorbResolvedDecisions = (): void => {
        const last = chat.messages.value[chat.messages.value.length - 1];

        if (last === undefined || last.role !== 'assistant') {
            return;
        }

        for (const part of last.parts) {
            if (isToolUIPart(part) && part.state === 'approval-responded') {
                const id = part.approval?.id;

                if (typeof id === 'string' && id !== '') {
                    submittedDecisionIds.add(id);
                }
            }
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

    return { ...chat, submitDecisions, absorbResolvedDecisions, cancelTurn };
};
