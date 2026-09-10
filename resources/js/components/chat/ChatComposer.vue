<script setup lang="ts">
import { IconArrowUp, IconPaperclip, IconPlayerStop, IconX } from '@tabler/icons-vue';
import { ref } from 'vue';

import { Button } from '@/components/ui/button';

export interface ChatAttachment {
    filename: string;
    content: string;
}

const model = defineModel<string>({ default: '' });
const attachment = defineModel<ChatAttachment | null>('attachment', {
    default: null,
});

const props = withDefaults(
    defineProps<{
        placeholder: string;
        sendLabel: string;
        stopLabel: string;
        disabled?: boolean;
        /**
         * True while a turn is in flight. The send button becomes a stop
         * button so there is always a visible "working" cue — and a way out
         * of a long turn — even when the thread's newest output is already
         * fully rendered and nothing in it animates.
         */
        busy?: boolean;
    }>(),
    { disabled: false, busy: false },
);

const emit = defineEmits<{
    submit: [];
    stop: [];
}>();

const MAX_ATTACHMENT_BYTES = 1024 * 1024;

const fileInput = ref<HTMLInputElement | null>(null);
const attachError = ref<string | null>(null);

const canSubmit = (): boolean =>
    Boolean(model.value.trim()) || attachment.value !== null;

const onSubmit = (): void => {
    if (props.disabled || !canSubmit()) {
        return;
    }

    emit('submit');
};

const onAction = (): void => {
    if (props.busy) {
        emit('stop');

        return;
    }

    onSubmit();
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();

        if (!props.busy) {
            onSubmit();
        }
    }
};

const pickFile = (): void => {
    attachError.value = null;
    fileInput.value?.click();
};

const onFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    if (file.size > MAX_ATTACHMENT_BYTES) {
        attachError.value = 'file_too_large';

        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        attachment.value = {
            filename: file.name,
            content: String(reader.result ?? ''),
        };
    };
    reader.onerror = () => {
        attachError.value = 'read_failed';
    };
    reader.readAsText(file);
};

const removeAttachment = (): void => {
    attachment.value = null;
};
</script>

<template>
    <div
        class="flex flex-col gap-2 rounded-3xl border-2 border-foreground bg-card p-2 shadow-2xs"
        data-testid="chat-composer"
        dusk="chat-composer"
    >
        <div
            v-if="attachment"
            class="mx-1 flex items-center gap-2 rounded-xl border border-foreground/15 bg-background px-3 py-1.5 text-xs"
            data-testid="chat-composer-attachment"
        >
            <IconPaperclip class="size-3.5 shrink-0 text-muted-foreground" />
            <span class="min-w-0 flex-1 truncate">{{
                attachment.filename
            }}</span>
            <button
                type="button"
                class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                :aria-label="$t('chat.attachment.remove')"
                data-testid="chat-composer-attachment-remove"
                @click="removeAttachment"
            >
                <IconX class="size-3.5" />
            </button>
        </div>

        <p
            v-if="attachError"
            class="mx-1 text-xs text-destructive"
            data-testid="chat-composer-attachment-error"
        >
            {{ $t(`chat.attachment.errors.${attachError}`) }}
        </p>

        <div class="flex items-end gap-2">
            <Button
                type="button"
                size="icon"
                variant="ghost"
                class="rounded-full"
                :aria-label="$t('chat.attachment.attach')"
                data-testid="chat-attach"
                @click="pickFile"
            >
                <IconPaperclip class="size-5" />
            </Button>

            <textarea
                v-model="model"
                rows="1"
                :placeholder="placeholder"
                class="min-h-10 flex-1 resize-none bg-transparent px-3 py-2 text-sm leading-relaxed text-foreground outline-none placeholder:text-muted-foreground"
                data-testid="chat-composer-input"
                dusk="chat-composer-input"
                @keydown="onKeydown"
            />

            <Button
                type="button"
                size="icon"
                class="rounded-full"
                :disabled="!busy && (disabled || !canSubmit())"
                :aria-label="busy ? stopLabel : sendLabel"
                data-testid="chat-send"
                dusk="chat-send"
                @click="onAction"
            >
                <IconPlayerStop v-if="busy" class="size-5" />
                <IconArrowUp v-else class="size-5" />
            </Button>
        </div>

        <input
            ref="fileInput"
            type="file"
            accept=".csv,text/csv"
            class="hidden"
            data-testid="chat-composer-file"
            @change="onFileChange"
        />
    </div>
</template>
