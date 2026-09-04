<script setup lang="ts">
import { useHttp, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useAiStream } from '@/composables/echo/useAiStream';
import { regenerateCaption } from '@/routes/app/posts/ai';

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

const start = async () => {
    dispatching.value = true;
    const regenerationId = crypto.randomUUID();
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
        }
    } catch {
        unsubscribe();
        status.value = 'failed';
    } finally {
        dispatching.value = false;
    }
};

const apply = () => {
    emit('apply', text.value.trim());
    open.value = false;
};

watch(open, () => {
    unsubscribe();
    reset();
    instruction.value = '';
});

const canApply = computed(
    () => status.value === 'completed' && text.value.trim().length > 0,
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{
                    $t('posts.ai.regenerate_caption.title')
                }}</DialogTitle>
                <DialogDescription>{{
                    $t('posts.ai.regenerate_caption.description')
                }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-4">
                <Textarea
                    v-model="instruction"
                    :placeholder="
                        $t(
                            'posts.ai.regenerate_caption.instruction_placeholder',
                        )
                    "
                    :disabled="status === 'streaming'"
                />
                <div
                    v-if="status !== 'idle'"
                    class="min-h-[120px] rounded-lg border-2 border-foreground bg-card px-3 py-2 text-sm whitespace-pre-wrap"
                >
                    {{ text || errorMessage || '...' }}
                </div>
            </div>
            <DialogFooter>
                <Button
                    v-if="status === 'idle' || status === 'failed'"
                    :loading="dispatching"
                    @click="start"
                    >{{ $t('posts.ai.regenerate_caption.start') }}</Button
                >
                <Button v-if="canApply" @click="apply">{{
                    $t('posts.ai.regenerate_caption.apply')
                }}</Button>
                <Button
                    v-if="status === 'completed' || status === 'failed'"
                    variant="outline"
                    @click="start"
                    >{{ $t('posts.ai.regenerate_caption.retry') }}</Button
                >
                <Button variant="outline" @click="open = false">{{
                    $t('posts.ai.regenerate_caption.cancel')
                }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
