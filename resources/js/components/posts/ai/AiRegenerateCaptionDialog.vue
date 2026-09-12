<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import { IconAlertTriangle } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAiStream } from '@/composables/echo/useAiStream';
import { regenerateCaption } from '@/routes/app/posts/ai';
import { uuid } from '@/utils/uuid';

const props = defineProps<{ postId: string; content: string }>();
const open = defineModel<boolean>('open', { required: true });
const emit = defineEmits<{ (e: 'apply', content: string): void }>();
const page = usePage();
const instruction = ref('');
const dispatching = ref(false);
const { text, status, errorMessage, subscribe, unsubscribe, reset } =
    useAiStream();
const http = useHttp<{
    content: string;
    instruction: string | null;
    regeneration_id: string;
}>({ content: '', instruction: null, regeneration_id: '' });

// The user must not lose an in-flight stream by dismissing the dialog. While
// dispatching or streaming, block the backdrop/escape close and hide the "x".
const isBusy = computed(
    () => dispatching.value || status.value === 'streaming',
);

// First deltas can lag behind the "streaming" transition; show a skeleton
// until the first character lands so the panel is never an empty box.
const isAwaitingFirstToken = computed(
    () => status.value === 'streaming' && text.value.length === 0,
);

const hasError = computed(() => status.value === 'failed');

const canApply = computed(
    () => status.value === 'completed' && text.value.trim().length > 0,
);

const blockDismissWhileBusy = (event: Event) => {
    if (isBusy.value) {
        event.preventDefault();
    }
};

const start = async () => {
    dispatching.value = true;
    const regenerationId = uuid();
    try {
        const subscribed = await subscribe(
            `user.${String(page.props.auth.user.id)}.ai-caption.${regenerationId}`,
        );
        if (!subscribed) throw new Error('Channel subscription failed');
        http.content = props.content;
        http.instruction = instruction.value.trim() || null;
        http.regeneration_id = regenerationId;
        await http.post(regenerateCaption.url(props.postId));
        if (http.hasErrors) {
            unsubscribe();
            reset();
            status.value = 'failed';
            errorMessage.value = trans(
                'posts.ai.regenerate_caption.error_generic',
            );
        }
    } catch {
        unsubscribe();
        status.value = 'failed';
        errorMessage.value = trans('posts.ai.regenerate_caption.error_generic');
    } finally {
        dispatching.value = false;
    }
};

const apply = () => {
    emit('apply', text.value.trim());
    toast.success(trans('posts.ai.regenerate_caption.applied'));
    open.value = false;
};

watch(open, () => {
    unsubscribe();
    reset();
    instruction.value = '';
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="sm:max-w-2xl"
            :show-close-button="!isBusy"
            @pointer-down-outside="blockDismissWhileBusy"
            @escape-key-down="blockDismissWhileBusy"
        >
            <DialogHeader>
                <DialogTitle>{{
                    $t('posts.ai.regenerate_caption.title')
                }}</DialogTitle>
                <DialogDescription>{{
                    $t('posts.ai.regenerate_caption.description')
                }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4">
                <div class="space-y-2">
                    <Label for="ai-caption-instruction">{{
                        $t('posts.ai.regenerate_caption.instruction_label')
                    }}</Label>
                    <Textarea
                        id="ai-caption-instruction"
                        v-model="instruction"
                        :placeholder="
                            $t(
                                'posts.ai.regenerate_caption.instruction_placeholder',
                            )
                        "
                        :disabled="isBusy"
                        rows="3"
                    />
                </div>

                <div
                    v-if="hasError"
                    class="flex items-start gap-2 rounded-lg border-2 border-foreground bg-rose-50 p-3 text-sm font-semibold text-rose-700"
                >
                    <IconAlertTriangle class="mt-0.5 size-4 shrink-0" />
                    <span>{{
                        errorMessage ||
                        $t('posts.ai.regenerate_caption.error_generic')
                    }}</span>
                </div>

                <div
                    v-else-if="status !== 'idle'"
                    class="space-y-2"
                    data-testid="ai-caption-output"
                >
                    <Label>{{
                        $t('posts.ai.regenerate_caption.output_label')
                    }}</Label>
                    <div
                        class="min-h-[120px] rounded-lg border-2 border-foreground bg-card px-3 py-2 text-sm whitespace-pre-wrap"
                        aria-live="polite"
                    >
                        <div
                            v-if="isAwaitingFirstToken"
                            class="space-y-2"
                            data-testid="ai-caption-skeleton"
                        >
                            <div
                                class="h-3 w-3/4 animate-pulse rounded bg-foreground/15"
                            ></div>
                            <div
                                class="h-3 w-full animate-pulse rounded bg-foreground/15"
                            ></div>
                            <div
                                class="h-3 w-2/3 animate-pulse rounded bg-foreground/15"
                            ></div>
                        </div>
                        <template v-else>{{ text }}</template>
                    </div>
                    <p
                        v-if="status === 'streaming'"
                        class="text-xs text-muted-foreground"
                    >
                        {{ $t('posts.ai.regenerate_caption.streaming') }}
                    </p>
                </div>
            </div>
            <DialogFooter>
                <Button v-if="canApply" @click="apply">{{
                    $t('posts.ai.regenerate_caption.apply')
                }}</Button>
                <Button
                    v-if="
                        status === 'idle' || status === 'completed' || hasError
                    "
                    :variant="canApply ? 'outline' : 'default'"
                    :loading="dispatching"
                    @click="start"
                    >{{
                        status === 'idle'
                            ? $t('posts.ai.regenerate_caption.start')
                            : $t('posts.ai.regenerate_caption.retry')
                    }}</Button
                >
                <Button
                    variant="outline"
                    :disabled="isBusy"
                    @click="open = false"
                    >{{ $t('posts.ai.regenerate_caption.cancel') }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
