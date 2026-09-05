<script setup lang="ts">
import type { ChatSignature } from '@/types/chat';

defineProps<{
    data: ChatSignature;
}>();
</script>

<template>
    <div
        class="space-y-1 rounded-xl border border-foreground/15 bg-background p-3"
        data-testid="chat-signature-card"
        dusk="chat-signature-card"
    >
        <p
            v-if="data.deleted"
            class="text-sm text-muted-foreground"
            data-testid="chat-signature-card-deleted"
        >
            {{ $t('chat.tool_card.signature_deleted') }}
        </p>

        <template v-else>
            <p class="text-sm font-semibold">{{ data.name }}</p>
            <p class="line-clamp-3 text-sm text-foreground/90">
                {{ data.content }}
            </p>
        </template>
    </div>
</template>
