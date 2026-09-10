<script setup lang="ts">
import type { ChatSignature } from '@/types/chat';

defineProps<{
    data: ChatSignature[];
}>();
</script>

<template>
    <div
        class="space-y-2"
        data-testid="chat-signature-list"
        dusk="chat-signature-list"
    >
        <p v-if="!data.length" class="text-sm text-muted-foreground">
            {{ $t('chat.tool_card.empty_signatures') }}
        </p>

        <div
            v-for="signature in data"
            :key="signature.id"
            class="space-y-1 rounded-xl border border-foreground/15 bg-background p-3"
        >
            <p class="text-sm font-semibold">{{ signature.name }}</p>
            <p class="line-clamp-3 text-sm text-foreground/90">
                {{ signature.content }}
            </p>
        </div>
    </div>
</template>
