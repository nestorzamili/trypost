<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconExternalLink,
    IconLoader2,
    IconSparkles,
} from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import ChatPostCard from '@/components/chat/tools/ChatPostCard.vue';
import ChatPostPreview from '@/components/chat/tools/ChatPostPreview.vue';
import {
    POST_CREATION_TIMEOUT_MS,
    usePostCreation,
    type PostCreationDetachReason,
} from '@/composables/echo/usePostCreation';
import date from '@/date';
import { edit as editPost } from '@/routes/app/posts';
import { status as creationStatus } from '@/routes/app/posts/ai';
import type { ChatPost, ChatPostGeneration } from '@/types/chat';

const props = defineProps<{
    data: ChatPostGeneration | null;
}>();

/**
 * How long the time-based bar takes to reach its ceiling. Used only for
 * generations without image work; phased generations render done/expected
 * instead (see `progressPercent`). The elapsed clock beside it is the honest
 * number; the bar only exists so a long wait still looks alive.
 */
const ESTIMATED_SECONDS = 120;
const MAX_PROGRESS = 0.95;

/**
 * Phase order for merging live broadcasts over the replayed snapshot: phases
 * only move forward, so a live phase at or ahead of the snapshot wins and a
 * stale duplicate behind it is dropped.
 */
const PHASE_RANK: Record<string, number> = {
    pending_text: 0,
    text_ready: 1,
    image_running: 2,
    ready: 3,
    failed_text: 3,
    failed_image: 3,
};

/**
 * Ceiling on the elapsed clock — the same horizon the generation itself gets
 * (`POST_CREATION_TIMEOUT_MS`, mirrored server-side by
 * `App\Ai\Tools\ToolReplayer::GENERATION_WINDOW_MINUTES`). Past it nothing is
 * coming, so a counter still climbing beside "this keeps running in the
 * background" is telling the user something false.
 *
 * The clock needs a ceiling of its own because the wait does not always end on
 * a timer. A refused subscription — broadcasting unavailable, self-hosted
 * without Reverb, or private-channel auth refused — detaches within seconds
 * and arms nothing, so without this the clock would count for as long as the
 * tab stays open.
 */
const ELAPSED_CEILING_SECONDS = POST_CREATION_TIMEOUT_MS / 1000;

const broadcastPostId = ref<string | null>(null);
const failed = ref(false);
const failureMessage = ref<string | null>(null);
const elapsed = ref(0);
const livePhase = ref<string | null>(null);
const liveImageDone = ref(0);
const liveImageExpected = ref(0);
const liveDraftPostId = ref<string | null>(null);

/**
 * True once the card stopped listening without an answer — the socket refused
 * the channel, or the wait ran out. The generation is unaffected and still
 * writes its post, so the card keeps its waiting shape and only swaps the
 * hint: reopening the conversation resolves the post from `creation_id`.
 */
const detached = ref(false);

let elapsedTimer: ReturnType<typeof setInterval> | null = null;
let pollTimer: ReturnType<typeof setInterval> | null = null;

/**
 * How often to poll the generation status as a fallback when the WebSocket
 * broadcast does not arrive (Reverb down, channel-auth refused, or the event
 * fired before the subscription resolved). The broadcast stays the fast path;
 * this is the safety net so the card resolves in-session without a page reload.
 */
const POLL_INTERVAL_MS = 4000;

/**
 * The post the server already resolved from `creation_id` — present only when
 * the conversation was reopened after the generation had finished. Read
 * through a computed rather than snapshotted, because `ChatToolPart` re-parses
 * the payload on every parent render and hands down a fresh object each time.
 */
const replayedPost = computed<ChatPost | null>(() => props.data?.post ?? null);

const isGenerationReady = computed<boolean>(
    () => props.data?.generation?.status === 'ready',
);

const isGenerationInProgress = computed<boolean>(() => {
    const status = props.data?.generation?.status;

    return (
        status === 'pending_text' ||
        status === 'text_ready' ||
        status === 'image_running'
    );
});

const snapshotPhase = computed<string | null>(
    () => props.data?.generation?.status ?? null,
);

const snapshotFailed = computed<boolean>(
    () =>
        snapshotPhase.value === 'failed_text' ||
        snapshotPhase.value === 'failed_image',
);

/** Local failure or a replayed terminal failure — both own the failed branch. */
const showFailed = computed<boolean>(
    () => failed.value || snapshotFailed.value,
);

/**
 * True once a live broadcast reached or passed the replayed snapshot, so live
 * counts take over and the bar/copy keep moving instead of freezing on the
 * snapshot's values.
 */
const liveIsCurrent = computed<boolean>(() => {
    if (livePhase.value === null) {
        return false;
    }

    if (snapshotPhase.value === null) {
        return true;
    }

    return (
        (PHASE_RANK[livePhase.value] ?? -1) >=
        (PHASE_RANK[snapshotPhase.value] ?? -1)
    );
});

const effectivePhase = computed<string | null>(() =>
    liveIsCurrent.value ? livePhase.value : snapshotPhase.value,
);

const imageDone = computed<number>(() =>
    liveIsCurrent.value
        ? liveImageDone.value
        : (props.data?.generation?.image_done ?? liveImageDone.value),
);

const imageExpected = computed<number>(() =>
    liveIsCurrent.value
        ? liveImageExpected.value
        : (props.data?.generation?.image_expected ?? liveImageExpected.value),
);

const hasImageWork = computed<boolean>(() => imageExpected.value > 0);

const draftPostId = computed<string | null>(
    () =>
        replayedPost.value?.id ??
        props.data?.generation?.post_id ??
        liveDraftPostId.value ??
        null,
);

const readyPostId = computed<string | null>(() => {
    if (showFailed.value) {
        return null;
    }

    if (isGenerationReady.value) {
        return replayedPost.value?.id ?? broadcastPostId.value;
    }

    return replayedPost.value && !isGenerationInProgress.value
        ? replayedPost.value.id
        : broadcastPostId.value;
});

const showDraftWhileWaiting = computed<boolean>(
    () => draftPostId.value !== null && readyPostId.value === null,
);

const isWaiting = computed<boolean>(
    () => readyPostId.value === null && !showFailed.value,
);

const waitingCopy = computed<string>(() => {
    if (!hasImageWork.value) {
        return 'waiting';
    }

    if (effectivePhase.value === 'text_ready') {
        return 'text_ready';
    }

    if (imageDone.value > 0) {
        return 'images_progress';
    }

    return 'images_running';
});

const elapsedLabel = computed<string>(() => date.formatClock(elapsed.value));

const progressPercent = computed<number>(() => {
    if (hasImageWork.value) {
        const fraction = imageDone.value / Math.max(1, imageExpected.value);
        // 8% credits the finished text phase so the bar never reads dead at
        // 0% while images render, plus a small elapsed creep (max 4%) so a
        // long render still looks alive. Capped — completion arrives over Echo.
        const creep = Math.min(4, Math.floor(elapsed.value / 30));

        return Math.min(99, 8 + Math.round(fraction * 87) + creep);
    }

    return Math.round(
        Math.min(MAX_PROGRESS, elapsed.value / ESTIMATED_SECONDS) * 100,
    );
});

const stopElapsed = (): void => {
    if (elapsedTimer !== null) {
        clearInterval(elapsedTimer);
        elapsedTimer = null;
    }
};

const stopPolling = (): void => {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
};

const fail = (message: string | null): void => {
    failed.value = true;
    failureMessage.value = message;
    stopElapsed();
    stopPolling();
};

/** Mark the post ready via the given id, mirroring the broadcast fast path. */
const resolveReady = (postId: string): void => {
    broadcastPostId.value = postId;
    stopElapsed();
    stopPolling();
};

/**
 * Fallback: ask the server for the generation's status by creation_id. Used
 * both once on mount (catch-up for an event that fired before we subscribed)
 * and on an interval while waiting (in case the broadcast never arrives). The
 * broadcast, if it comes, resolves first and cancels this via stopPolling.
 */
const pollStatus = async (): Promise<void> => {
    const creationId = props.data?.creation_id;

    if (!creationId || readyPostId.value !== null || showFailed.value) {
        return;
    }

    try {
        const response = await fetch(creationStatus.url({ creationId }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const body = (await response.json()) as {
            status?: string;
            post_id?: string | null;
            error?: string | null;
        };

        if (
            (body.status === 'ready' || body.status === 'image_running') &&
            body.post_id
        ) {
            liveDraftPostId.value = body.post_id;
        }

        if (body.status === 'ready' && body.post_id) {
            resolveReady(body.post_id);
        } else if (
            body.status === 'failed_text' ||
            body.status === 'failed_image'
        ) {
            fail(body.error ?? null);
        }
    } catch {
        // Network hiccup — the next tick (or the broadcast) will catch up.
    }
};

const { watchCreation } = usePostCreation({
    onReady: (postId: string): void => {
        resolveReady(postId);
    },
    onProgress: (progress): void => {
        if (progress.phase) {
            livePhase.value = progress.phase;
        }

        liveImageDone.value = progress.image_done ?? 0;
        liveImageExpected.value = progress.image_expected ?? 0;

        if (progress.post_id) {
            liveDraftPostId.value = progress.post_id;
        }
    },
    onFailed: fail,
    /**
     * A timeout stops the clock outright: the window closed, measured against
     * real time by the composable's own timer. A refused subscription is the
     * opposite case — it lands seconds after mounting, with the generation
     * genuinely in flight, so the clock keeps running and stays the card's
     * only sign that it is alive rather than frozen. It is bounded instead by
     * ELAPSED_CEILING_SECONDS.
     *
     * The two are complements, not duplicates: `setInterval` is throttled in a
     * backgrounded tab, so `elapsed` can lag real time badly and reach its
     * ceiling long after the window truly closed. Whichever notices first wins.
     */
    onDetached: (reason: PostCreationDetachReason): void => {
        detached.value = true;

        if (reason === 'timeout') {
            stopElapsed();
        }
    },
});

onMounted(() => {
    const generation = props.data?.generation;

    if (
        generation?.status === 'failed_text' ||
        generation?.status === 'failed_image'
    ) {
        fail(generation.error ?? null);

        return;
    }

    if (generation?.status === 'ready' && replayedPost.value !== null) {
        return;
    }

    if (replayedPost.value !== null && !isGenerationInProgress.value) {
        return;
    }

    const channel = props.data?.channel;

    // `settled` means the server already established the generation ended
    // without a post: the turn predates the whole generation window, so
    // nothing will ever arrive on the channel. Subscribing would spin for the
    // length of the timeout implying work is still in progress.
    if (!channel || props.data?.settled === true) {
        fail(generation?.error ?? null);

        return;
    }

    elapsedTimer = setInterval(() => {
        elapsed.value += 1;

        if (elapsed.value >= ELAPSED_CEILING_SECONDS) {
            stopElapsed();
        }
    }, 1000);

    watchCreation(channel);

    // Fallback net for the broadcast: an immediate catch-up (the event may have
    // fired before we subscribed) plus a poll while waiting. resolveReady from
    // either path cancels this.
    void pollStatus();
    pollTimer = setInterval(() => {
        void pollStatus();
    }, POLL_INTERVAL_MS);
});

onBeforeUnmount(() => {
    stopElapsed();
    stopPolling();
});
</script>

<template>
    <div data-testid="chat-post-generation-result">
        <ChatPostCard
            v-if="replayedPost && !isGenerationInProgress && !showFailed"
            :data="replayedPost"
        />

        <div
            v-else-if="readyPostId"
            class="space-y-2 rounded-xl border border-foreground/15 bg-background p-3"
            data-testid="chat-post-generation-ready"
        >
            <div class="flex flex-wrap items-center gap-2">
                <IconSparkles class="size-4 shrink-0 text-primary" />

                <span class="text-sm text-foreground/90">{{
                    $t('chat.post_generation.result_ready')
                }}</span>

                <Link
                    :href="editPost.url(readyPostId)"
                    class="ms-auto inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    data-testid="chat-post-generation-open"
                    dusk="chat-post-generation-open"
                >
                    <IconExternalLink class="size-3.5" />
                    {{ $t('chat.tool_card.open_in_editor') }}
                </Link>
            </div>

            <ChatPostPreview :post-id="readyPostId" :expected-media="imageExpected" />
        </div>

        <div
            v-else-if="isWaiting"
            class="space-y-2 rounded-xl border border-foreground/15 bg-background p-3"
            data-testid="chat-post-generation-waiting"
        >
            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <IconLoader2
                    class="size-4 shrink-0 animate-spin"
                    aria-hidden="true"
                />
                <span v-if="waitingCopy === 'text_ready'">{{
                    $t('chat.post_generation.result_text_ready')
                }}</span>
                <span v-else-if="waitingCopy === 'images_progress'">{{
                    $t('chat.post_generation.result_images_progress', {
                        done: String(imageDone),
                        total: String(imageExpected),
                    })
                }}</span>
                <span v-else-if="waitingCopy === 'images_running'">{{
                    $t('chat.post_generation.result_images_running', {
                        total: String(imageExpected),
                    })
                }}</span>
                <span v-else>{{
                    $t('chat.post_generation.result_waiting')
                }}</span>
                <span
                    class="ms-auto font-mono text-xs"
                    :aria-label="
                        $t('chat.post_generation.result_elapsed_label', {
                            elapsed: elapsedLabel,
                        })
                    "
                >
                    {{ elapsedLabel }}
                </span>
            </div>

            <div
                class="h-1.5 w-full overflow-hidden rounded-full bg-accent"
                role="progressbar"
                :aria-valuenow="progressPercent"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="$t('chat.post_generation.result_waiting')"
            >
                <div
                    class="h-full rounded-full bg-primary transition-[width] duration-700 ease-out"
                    :style="{ width: `${progressPercent}%` }"
                ></div>
            </div>

            <div
                v-if="showDraftWhileWaiting && draftPostId"
                class="flex items-center gap-2"
            >
                <ChatPostCard
                    v-if="replayedPost"
                    :data="replayedPost"
                    class="flex-1"
                />
                <Link
                    v-else
                    :href="editPost.url(draftPostId)"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    data-testid="chat-post-generation-draft-open"
                >
                    <IconExternalLink class="size-3.5" />
                    {{ $t('chat.post_generation.result_open_draft') }}
                </Link>
            </div>

            <p
                class="text-xs text-muted-foreground"
                data-testid="chat-post-generation-waiting-hint"
            >
                {{
                    detached
                        ? $t('chat.post_generation.result_detached_hint')
                        : $t('chat.post_generation.result_waiting_hint')
                }}
            </p>
        </div>

        <div
            v-else
            class="space-y-2 rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
            data-testid="chat-post-generation-failed"
        >
            <div class="flex flex-wrap items-center gap-2">
                <IconAlertTriangle class="size-4 shrink-0" />
                <span class="flex-1">{{
                    failureMessage || $t('chat.post_generation.result_failed')
                }}</span>
                <Link
                    v-if="draftPostId"
                    :href="editPost.url(draftPostId)"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    data-testid="chat-post-generation-failed-open"
                >
                    <IconExternalLink class="size-3.5" />
                    {{ $t('chat.post_generation.result_open_draft') }}
                </Link>
            </div>

            <ChatPostPreview v-if="draftPostId" :post-id="draftPostId" />
        </div>
    </div>
</template>
