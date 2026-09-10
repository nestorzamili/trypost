<script setup lang="ts">
import type { ChatLabel } from '@/types/chat';

defineProps<{
    data: ChatLabel[];
}>();
</script>

<template>
    <div class="space-y-2" data-testid="chat-label-list" dusk="chat-label-list">
        <p v-if="!data.length" class="text-sm text-muted-foreground">
            {{ $t('chat.tool_card.empty_labels') }}
        </p>

        <div
            v-for="label in data"
            :key="label.id"
            class="flex items-center gap-2 rounded-xl border border-foreground/15 bg-background p-3"
        >
            <span
                class="size-3 shrink-0 rounded-full border border-foreground/20"
                :style="{ backgroundColor: label.color }"
            />
            <span class="text-sm font-medium">{{ label.name }}</span>
        </div>
    </div>
</template>
