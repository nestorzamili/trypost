<script setup lang="ts">
import type { ChatLabel } from '@/types/chat';

defineProps<{
    data: ChatLabel;
}>();
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-xl border border-foreground/15 bg-background p-3"
        data-testid="chat-label-card"
        dusk="chat-label-card"
    >
        <p
            v-if="data.deleted"
            class="text-sm text-muted-foreground"
            data-testid="chat-label-card-deleted"
        >
            {{ $t('chat.tool_card.label_deleted') }}
        </p>

        <template v-else>
            <span
                class="size-3 shrink-0 rounded-full border border-foreground/20"
                :style="{ backgroundColor: data.color }"
            />
            <span class="text-sm font-medium">{{ data.name }}</span>
        </template>
    </div>
</template>
