<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';

import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldLegend, FieldSet } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import {
    useAiMediaRegeneration,
    type RegenerationPayload,
} from '@/composables/useAiMediaRegeneration';
import { isImage } from '@/lib/mediaType';
import type { MediaItem } from '@/types/media';

const props = defineProps<{
    postId: string;
    mediaItem: MediaItem | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    (e: 'regenerated', payload: RegenerationPayload): void;
}>();

const {
    instruction,
    mode,
    errorMessage,
    instructionError,
    modeError,
    status,
    isBusy,
    canContinueInBackground,
    isProcessing,
    canSubmit,
    submit,
    resetState,
    blockDismissWhileBusy,
} = useAiMediaRegeneration({
    postId: props.postId,
    getMediaItem: () => props.mediaItem,
    onRegenerated: (payload) => emit('regenerated', payload),
    onCompleted: () => {
        open.value = false;
    },
});

watch(open, (isOpen) => {
    if (!isOpen) {
        if (isProcessing.value) {
            if (!canContinueInBackground.value) {
                open.value = true;
                return;
            }

            toast.info(trans('posts.ai.image_regenerate.background_notice'));
            return;
        }

        if (!isProcessing.value) {
            resetState();
        }
    }
});

// IMG-5: show the image being edited so the user writes the instruction with
// the source in view, not blind.
const sourceImageUrl = computed<string | null>(() =>
    props.mediaItem && isImage(props.mediaItem) ? props.mediaItem.url : null,
);

// Quick-edit chips: common image-to-image instructions that seed the textarea.
// Modes that touch the visual only (image_only / both) benefit; text_only edits
// caption overlays, so the chips stay hidden there.
const QUICK_EDIT_KEYS = [
    'brighten',
    'remove_background',
    'night',
    'warmer',
    'sharpen',
] as const;

const showQuickEdits = computed(
    () => mode.value !== 'text_only' && !isBusy.value,
);

const applyQuickEdit = (key: string): void => {
    const phrase = trans(`posts.ai.image_regenerate.quick_edits.${key}`);
    instruction.value = instruction.value.trim()
        ? `${instruction.value.trim()} ${phrase}`
        : phrase;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            class="sm:max-w-xl"
            :show-close-button="!isBusy || canContinueInBackground"
            @pointer-down-outside="blockDismissWhileBusy"
            @escape-key-down="blockDismissWhileBusy"
        >
            <DialogHeader>
                <DialogTitle>{{
                    $t('posts.ai.image_regenerate.title')
                }}</DialogTitle>
                <DialogDescription>{{
                    $t(`posts.ai.image_regenerate.descriptions.${mode}`)
                }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div
                    v-if="sourceImageUrl"
                    class="flex justify-center"
                    data-testid="ai-image-source-preview"
                >
                    <img
                        :src="sourceImageUrl"
                        :alt="$t('posts.ai.image_regenerate.source_alt')"
                        class="max-h-48 rounded-lg border border-border object-contain"
                    />
                </div>

                <FieldSet class="gap-2">
                    <FieldLegend variant="label">{{
                        $t('posts.ai.image_regenerate.mode_label')
                    }}</FieldLegend>
                    <RadioGroup v-model="mode" :disabled="isBusy" class="gap-2">
                        <Label
                            v-for="option in [
                                'text_only',
                                'image_only',
                                'both',
                            ]"
                            :key="option"
                            :for="`ai-image-regeneration-${option}`"
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors hover:bg-muted/50',
                                mode === option
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border',
                            ]"
                        >
                            <RadioGroupItem
                                :id="`ai-image-regeneration-${option}`"
                                :value="option"
                                class="mt-0.5"
                            />
                            <span class="space-y-0.5">
                                <span class="block text-sm font-medium">
                                    {{
                                        $t(
                                            `posts.ai.image_regenerate.modes.${option}.label`,
                                        )
                                    }}
                                </span>
                                <span
                                    class="block text-sm font-normal text-muted-foreground"
                                >
                                    {{
                                        $t(
                                            `posts.ai.image_regenerate.modes.${option}.description`,
                                        )
                                    }}
                                </span>
                            </span>
                        </Label>
                    </RadioGroup>
                    <InputError :message="modeError" />
                </FieldSet>

                <div class="space-y-2">
                    <Label for="ai-image-instruction">{{
                        $t('posts.ai.image_regenerate.instruction_label')
                    }}</Label>
                    <div
                        v-if="showQuickEdits"
                        class="flex flex-wrap gap-1.5"
                        data-testid="ai-image-quick-edits"
                    >
                        <Button
                            v-for="key in QUICK_EDIT_KEYS"
                            :key="key"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-7 rounded-full px-2.5 text-xs font-medium"
                            @click="applyQuickEdit(key)"
                        >
                            {{ $t(`posts.ai.image_regenerate.quick_edits.${key}`) }}
                        </Button>
                    </div>
                    <Textarea
                        id="ai-image-instruction"
                        v-model="instruction"
                        :disabled="isBusy"
                        :placeholder="
                            $t(
                                `posts.ai.image_regenerate.instruction_placeholders.${mode}`,
                            )
                        "
                        rows="4"
                    />
                    <InputError :message="instructionError" />
                </div>

                <p
                    v-if="status === 'processing'"
                    class="text-sm text-foreground/70"
                >
                    {{ $t(`posts.ai.image_regenerate.processing.${mode}`) }}
                </p>
                <p
                    v-if="errorMessage"
                    class="text-sm font-semibold text-rose-700"
                >
                    {{ errorMessage }}
                </p>
            </div>

            <DialogFooter>
                <Button
                    :loading="isBusy"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    {{ $t(`posts.ai.image_regenerate.submit.${mode}`) }}
                </Button>
                <Button
                    variant="outline"
                    :disabled="isBusy && !canContinueInBackground"
                    @click="open = false"
                >
                    {{
                        canContinueInBackground
                            ? $t(
                                  'posts.ai.image_regenerate.continue_in_background',
                              )
                            : $t('posts.ai.image_regenerate.cancel')
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
