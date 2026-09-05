<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconChartBar,
    IconClock,
    IconFileText,
    IconLayoutSidebar,
    IconX,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';

import ChatComposer from '@/components/chat/ChatComposer.vue';
import ChatHistoryPanel from '@/components/chat/ChatHistoryPanel.vue';
import ChatThread from '@/components/chat/ChatThread.vue';
import { Button } from '@/components/ui/button';
import { useWorkspaceEcho } from '@/composables/echo/useWorkspaceEcho';
import {
    ChatRequestError,
    useConversationChat,
} from '@/composables/useConversationChat';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    patchPostStatus,
    type PostPlatformStatusPayload,
} from '@/lib/chat/postStatus';
import { buildInitialMessages } from '@/lib/chat/seedMessages';
import { chat } from '@/routes/app';
import { index as billingIndex } from '@/routes/app/billing';
import { show } from '@/routes/app/chat';
import type {
    ChatApprovalDecision,
    ChatConversationSummary,
    ChatServerMessage,
} from '@/types/chat';

const props = defineProps<{
    conversations: ChatConversationSummary[];
    conversation?: ChatConversationSummary;
    messages?: ChatServerMessage[];
}>();

// Stable for the component's lifetime: a brand-new chat gets a client-generated
// id up front (the backend's claim() creates the row on the first message, keyed
// by whatever id the client sends), and Inertia remounts this page on every real
// navigation to a different conversation, so this never needs to be reactive.
const conversationId = props.conversation?.id ?? crypto.randomUUID();

const {
    messages,
    status,
    error,
    sendMessage,
    stop,
    regenerate,
    submitDecisions,
    absorbResolvedDecisions,
    cancelTurn,
    clearError,
} = useConversationChat(
    conversationId,
    buildInitialMessages(props.messages ?? []),
    {
        // Fires only on a clean stream end — never on abort, disconnect, or
        // error — so this replaces a status watcher that could not tell a
        // finished turn from a failed one.
        onFinish: ({ isAbort, isDisconnect, isError }) => {
            if (
                !isAbort &&
                !isDisconnect &&
                !isError &&
                props.conversation === undefined &&
                !isKnownConversation.value
            ) {
                router.reload({ only: ['conversations'] });
            }
        },
        // A failed turn never reaches the server's then(), so without this
        // the claim stays in progress until the 5-minute self-heal. Any
        // non-409 failure means no other turn is actually running, making
        // the release safe; on a 409 another turn owns the claim and must
        // keep it.
        //
        // A `decisions_resolved` rejection is not a failure at all: the
        // server reports the replayed approvals already settled, which is
        // exactly what the client wanted to hear. The settled ids are
        // absorbed so nothing resends them, and the banner is cleared
        // without ever showing — there is nothing for the user to retry.
        onError: (chatError: Error) => {
            if (!(chatError instanceof ChatRequestError)) {
                return;
            }

            if (
                chatError.status === 422 &&
                chatError.code === 'decisions_resolved'
            ) {
                absorbResolvedDecisions();
                clearError();

                return;
            }

            if (chatError.status !== 409) {
                void cancelTurn();
            }
        },
    },
);

const draft = ref('');
const historyOpen = ref(false);

/**
 * `@ai-sdk/vue`'s `useChat` stores `messages` in a `shallowRef` and updates it
 * by mutating the array in place (`.push()`, index assignment) followed by a
 * manual `triggerRef()` — see `chatStateWrapper` in
 * `node_modules/@ai-sdk/vue/dist/index.js`. That correctly invalidates this
 * component's own render effect (and any `watch(messages, ...)`), but the
 * array's *reference* never changes, so Vue's child-prop diffing on
 * `<ChatThread :messages="...">` sees the same reference on every stream
 * chunk and skips re-rendering the child — the thread visibly freezes
 * mid-turn until some *other* prop on it changes value. Rebuilding a fresh
 * array here on every read forces the child to see a new reference and
 * re-render on each update, same as the framework-agnostic core already does
 * for the messages/parts objects nested inside it.
 */
const renderedMessages = computed(() => [...messages.value]);

const isBusy = computed(
    () => status.value === 'streaming' || status.value === 'submitted',
);

// Covers the gap between hitting send and the first visible token, plus any
// busy stretch with nothing renderable on screen yet: a reasoning-only
// stretch, an empty first text part, or the pause between two tool steps. The
// SDK can sit in 'streaming' with an assistant tail that ChatThread renders
// as nothing at all, which reads as a dead page rather than a working one —
// so this checks for actual visible output rather than trusting the status
// value or the tail's mere existence alone.
const isWaitingForAssistant = computed(() => {
    if (!isBusy.value) {
        return false;
    }

    const last = messages.value[messages.value.length - 1];

    if (last === undefined || last.role !== 'assistant') {
        return true;
    }

    return !last.parts.some((part) =>
        part.type === 'text' ? part.text !== '' : part.type.startsWith('tool-'),
    );
});

const requestError = computed<ChatRequestError | null>(() =>
    error.value instanceof ChatRequestError ? error.value : null,
);

const errorMessage = computed<string>(() => {
    const requestErr = requestError.value;

    if (requestErr === null) {
        return trans('chat.errors.stream_failed');
    }

    // 402 (billing gate) and 409 (turn already in progress) already carry a
    // translated message from the backend. 403/404 don't — Laravel's default
    // abort() response for those isn't localized, so those get their own copy.
    if (requestErr.status === 403 || requestErr.status === 404) {
        return trans('chat.errors.access_error');
    }

    return requestErr.message;
});

const errorTone = computed<'warning' | 'info' | 'error'>(() => {
    const httpStatus = requestError.value?.status;

    if (httpStatus === 402) {
        return 'warning';
    }

    if (httpStatus === 409) {
        return 'info';
    }

    return 'error';
});

// 402/403/404 carry their own call to action (billing, new chat); every other
// failure — provider 5xx, network drop, 409 after the other turn finished —
// is worth one more attempt via regenerate().
const canRetry = computed<boolean>(() => {
    const httpStatus = requestError.value?.status;

    return (
        httpStatus === undefined ||
        (httpStatus !== 402 && httpStatus !== 403 && httpStatus !== 404)
    );
});

/**
 * A brand-new chat's row is created server-side by the turn's claim(), so the
 * history props the page rendered with predate it. onFinish above pulls the
 * list once the first turn lands; this reports whether that pull is still
 * needed so it runs exactly once.
 */
const isKnownConversation = computed(() =>
    props.conversations.some(
        (conversation) => conversation.id === conversationId,
    ),
);

/**
 * Background titling finishes after the stream closes (queue `ai` plus one
 * LLM call), so the real title replaces the fallback without a manual
 * refresh. The reload merges the whole list, which also heals conversations
 * whose earlier titling failed while the provider was down.
 */
useWorkspaceEcho('.conversation.titled', () => {
    router.reload({ only: ['conversations'] });
});

/**
 * A publish the chat triggered finishes on the queue seconds after the tool
 * answered, so the card would otherwise freeze on the `publishing` snapshot
 * the tool returned when it dispatched the job. Folding the broadcast into
 * the thread's tool outputs flips the badge live; the server history is
 * untouched, and a reopened conversation replays from the server anyway.
 */
useWorkspaceEcho<PostPlatformStatusPayload>(
    '.post.platform.status.updated',
    (payload) => {
        const next = patchPostStatus(messages.value, payload);

        if (next !== null) {
            messages.value = next;
        }
    },
);

const send = (text: string): void => {
    const trimmed = text.trim();

    if (trimmed === '' || isBusy.value) {
        return;
    }

    if (props.conversation === undefined) {
        window.history.replaceState(
            window.history.state,
            '',
            show.url({ conversation: conversationId }),
        );
    }

    draft.value = '';
    sendMessage({ text: trimmed });
};

const submitDraft = (): void => send(draft.value);

const ask = (prompt: string): void => send(prompt);

/**
 * Abort the in-flight turn and release its server-side claim immediately.
 * stop() alone only drops the client side — without cancelTurn() the row
 * would stay in progress until the 5-minute self-heal.
 */
const stopTurn = (): void => {
    stop();
    void cancelTurn();
};

/**
 * Resend the newest user prompt after a failure. Safe against duplicates:
 * the server's claim only appends a user message when the trailing row does
 * not already carry the same content byte for byte.
 */
const retry = (): void => {
    if (isBusy.value) {
        return;
    }

    clearError();
    void regenerate();
};

const onDecide = (decision: ChatApprovalDecision): void => {
    submitDecisions({
        [decision.toolCallId]: {
            action: decision.action,
            result: decision.result,
        },
    });
};
</script>

<template>
    <AppLayout full-width>
        <Head :title="$t('chat.title')" />

        <div
            class="flex min-h-[calc(100dvh-1rem)] w-full"
            data-testid="workspace-chat"
            dusk="workspace-chat"
        >
            <div
                v-if="historyOpen"
                class="fixed inset-0 z-50 lg:hidden"
                data-testid="chat-history-overlay"
                dusk="chat-history-overlay"
            >
                <div
                    class="absolute inset-0 bg-black/50"
                    @click="historyOpen = false"
                />
                <aside
                    class="absolute inset-y-0 end-0 flex w-72 flex-col bg-background p-4 shadow-xl"
                >
                    <ChatHistoryPanel
                        :conversations="props.conversations"
                        :active-id="props.conversation?.id ?? null"
                        @navigate="historyOpen = false"
                    />
                </aside>
            </div>

            <div class="flex min-w-0 flex-1 flex-col px-4 py-8 lg:me-72">
                <div
                    class="mx-auto flex w-full max-w-2xl min-w-0 flex-1 flex-col"
                >
                    <div class="mb-4 lg:hidden">
                        <button
                            type="button"
                            class="inline-flex size-9 shrink-0 items-center justify-center rounded-md border-2 border-foreground bg-card text-foreground shadow-2xs"
                            :aria-label="$t('sidebar.chat_history')"
                            data-testid="chat-history-open"
                            dusk="chat-history-open"
                            @click="historyOpen = true"
                        >
                            <IconLayoutSidebar class="size-4" />
                        </button>
                    </div>
                    <div class="flex flex-1 flex-col">
                        <ChatThread
                            v-if="messages.length"
                            :messages="renderedMessages"
                            :pending="isWaitingForAssistant"
                            :disabled="isBusy"
                            :failed="error !== undefined && error !== null"
                            @submit="ask"
                            @decide="onDecide"
                        />

                        <div
                            v-else
                            class="flex flex-1 flex-col items-center justify-center pb-10 text-center"
                        >
                            <img
                                src="/images/trypost/icon.png"
                                alt=""
                                class="size-14 rounded-2xl border-2 border-foreground shadow-2xs"
                            />
                            <h1
                                class="mt-6 text-2xl font-normal tracking-tight text-foreground"
                                style="font-family: var(--font-display)"
                            >
                                {{ $t('chat.headline') }}
                            </h1>
                            <p
                                class="mt-2 max-w-md text-sm text-muted-foreground"
                            >
                                {{ $t('chat.description') }}
                            </p>
                        </div>
                    </div>

                    <div class="sticky bottom-0 bg-background pt-4 pb-2">
                        <div
                            v-if="error"
                            class="mb-3 flex items-start gap-2 rounded-xl border p-3 text-sm"
                            :class="{
                                'border-amber-300 bg-amber-50 text-amber-900':
                                    errorTone === 'warning',
                                'border-foreground/15 bg-background text-muted-foreground':
                                    errorTone === 'info',
                                'border-destructive/30 bg-destructive/5 text-destructive':
                                    errorTone === 'error',
                            }"
                            data-testid="chat-error"
                            :dusk="`chat-error-${requestError?.status ?? 'unknown'}`"
                        >
                            <IconClock
                                v-if="errorTone === 'info'"
                                class="mt-0.5 size-4 shrink-0"
                            />
                            <IconAlertTriangle
                                v-else
                                class="mt-0.5 size-4 shrink-0"
                            />

                            <div class="flex-1 space-y-2">
                                <p>{{ errorMessage }}</p>

                                <Link
                                    v-if="requestError?.status === 402"
                                    :href="billingIndex.url()"
                                    class="inline-flex text-sm font-semibold underline underline-offset-4"
                                    data-testid="chat-error-billing-cta"
                                    dusk="chat-error-billing-cta"
                                >
                                    {{ $t('chat.errors.payment_required_cta') }}
                                </Link>

                                <Link
                                    v-else-if="
                                        requestError?.status === 403 ||
                                        requestError?.status === 404
                                    "
                                    :href="chat.url()"
                                    class="inline-flex text-sm font-semibold underline underline-offset-4"
                                    data-testid="chat-error-new-chat-cta"
                                    dusk="chat-error-new-chat-cta"
                                >
                                    {{ $t('sidebar.new_chat') }}
                                </Link>

                                <button
                                    v-if="canRetry"
                                    type="button"
                                    class="inline-flex text-sm font-semibold underline underline-offset-4"
                                    data-testid="chat-error-retry"
                                    dusk="chat-error-retry"
                                    @click="retry"
                                >
                                    {{ $t('chat.retry') }}
                                </button>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :aria-label="$t('common.close')"
                                data-testid="chat-error-dismiss"
                                dusk="chat-error-dismiss"
                                @click="clearError"
                            >
                                <IconX class="size-4" />
                            </Button>
                        </div>

                        <ChatComposer
                            v-model="draft"
                            :placeholder="$t('chat.placeholder')"
                            :send-label="$t('chat.send')"
                            :stop-label="$t('chat.stop')"
                            :disabled="isBusy"
                            :busy="isBusy"
                            @submit="submitDraft"
                            @stop="stopTurn"
                        />

                        <div v-if="!messages.length" class="mt-4">
                            <p
                                class="mb-2 text-center text-xs font-bold tracking-wide text-muted-foreground uppercase"
                            >
                                {{ $t('chat.suggestions_label') }}
                            </p>
                            <div class="flex flex-wrap justify-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full border-2 border-foreground bg-card px-3 py-1.5 text-sm font-semibold shadow-2xs hover:bg-accent"
                                    data-testid="chat-suggestion-posts"
                                    dusk="chat-suggestion-posts"
                                    @click="ask($t('chat.suggestions.posts'))"
                                >
                                    <IconFileText class="size-4" />
                                    {{ $t('chat.suggestions.posts') }}
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full border-2 border-foreground bg-card px-3 py-1.5 text-sm font-semibold shadow-2xs hover:bg-accent"
                                    data-testid="chat-suggestion-metrics"
                                    dusk="chat-suggestion-metrics"
                                    @click="ask($t('chat.suggestions.metrics'))"
                                >
                                    <IconChartBar class="size-4" />
                                    {{ $t('chat.suggestions.metrics') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside
                class="fixed inset-y-0 end-0 z-30 hidden w-72 flex-col border-s-2 border-foreground bg-card lg:flex"
                data-testid="chat-history-panel"
                dusk="chat-history-panel"
            >
                <div class="flex h-full min-h-0 flex-col overflow-y-auto p-3">
                    <ChatHistoryPanel
                        :conversations="props.conversations"
                        :active-id="props.conversation?.id ?? null"
                    />
                </div>
            </aside>
        </div>
    </AppLayout>
</template>
