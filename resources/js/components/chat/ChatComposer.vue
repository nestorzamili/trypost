<script setup lang="ts">
import { IconArrowUp, IconPlayerStop } from '@tabler/icons-vue';

import { Button } from '@/components/ui/button';

const model = defineModel<string>({ default: '' });

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

const onSubmit = (): void => {
    if (props.disabled || !model.value.trim()) {
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
</script>

<template>
    <div
        class="flex items-end gap-2 rounded-3xl border-2 border-foreground bg-card p-2 shadow-2xs"
        data-testid="chat-composer"
        dusk="chat-composer"
    >
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
            :disabled="!busy && (disabled || !model.trim())"
            :aria-label="busy ? stopLabel : sendLabel"
            data-testid="chat-send"
            dusk="chat-send"
            @click="onAction"
        >
            <IconPlayerStop v-if="busy" class="size-5" />
            <IconArrowUp v-else class="size-5" />
        </Button>
    </div>
</template>
