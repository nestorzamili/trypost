import { echo } from '@laravel/echo-vue';
import { trans } from 'laravel-vue-i18n';
import { onUnmounted, ref } from 'vue';

import { subscribePrivateChannel } from './subscribePrivateChannel';

interface TextDeltaEvent {
    delta: string;
}

interface ErrorEvent {
    message?: string;
}

export type AiStreamStatus = 'idle' | 'streaming' | 'completed' | 'failed';

const DEFAULT_TIMEOUT_MS = 120_000;

export const aiGenerationChannel = (
    userId: string,
    generationId: string,
): string => `user.${userId}.ai-gen.${generationId}`;

/**
 * Subscribe to a private channel for an in-flight AI generation.
 * Reactive state accumulates `.TextDelta` event deltas and transitions to
 * `completed` on `.StreamEnd` or `failed` on `.Error`.
 *
 * A per-event timeout resets on every `.text_delta` so a genuinely slow
 * stream does not abort, but a stuck one (provider hang, connection drop)
 * fails after `timeoutMs` of silence.
 */
export const useAiStream = (timeoutMs = DEFAULT_TIMEOUT_MS) => {
    const text = ref('');
    const status = ref<AiStreamStatus>('idle');
    const errorMessage = ref<string | null>(null);
    let subscribedName: string | null = null;
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    const clearStreamTimeout = () => {
        if (timeoutId !== null) {
            clearTimeout(timeoutId);
            timeoutId = null;
        }
    };

    const armStreamTimeout = () => {
        clearStreamTimeout();
        timeoutId = setTimeout(() => {
            if (status.value === 'streaming') {
                status.value = 'failed';
                errorMessage.value = trans('posts.ai.generate.errors.timeout');
                unsubscribe();
            }
        }, timeoutMs);
    };

    const reset = () => {
        clearStreamTimeout();
        text.value = '';
        status.value = 'idle';
        errorMessage.value = null;
    };

    const unsubscribe = () => {
        clearStreamTimeout();
        if (subscribedName) {
            echo().leave(`private-${subscribedName}`);
        }
        subscribedName = null;
    };

    const subscribe = async (channelName: string): Promise<boolean> => {
        unsubscribe();
        reset();
        status.value = 'streaming';
        subscribedName = channelName;
        armStreamTimeout();

        const confirmed = await subscribePrivateChannel(
            channelName,
            (channel) => {
                channel
                    .listen('.text_delta', (e: TextDeltaEvent) => {
                        armStreamTimeout();
                        text.value += e.delta ?? '';
                    })
                    .listen('.stream_end', () => {
                        clearStreamTimeout();
                        status.value = 'completed';
                    })
                    .listen('.error', (e: ErrorEvent) => {
                        clearStreamTimeout();
                        status.value = 'failed';
                        errorMessage.value =
                            e?.message ??
                            trans('posts.ai.generate.errors.generation_failed');
                    });
            },
        );

        if (!confirmed) {
            clearStreamTimeout();
            status.value = 'failed';
            errorMessage.value = trans(
                'posts.ai.generate.errors.channel_failed',
            );
        }

        return confirmed;
    };

    onUnmounted(() => unsubscribe());

    return { text, status, errorMessage, subscribe, unsubscribe, reset };
};
