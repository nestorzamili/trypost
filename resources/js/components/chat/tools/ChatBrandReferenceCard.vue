<script setup lang="ts">
import { IconExternalLink } from '@tabler/icons-vue';

import type { ChatBrandReference } from '@/types/chat';

defineProps<{
    data: ChatBrandReference;
}>();
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-xl border border-foreground/15 bg-background p-3"
        data-testid="chat-brand-reference-card"
        dusk="chat-brand-reference-card"
    >
        <p
            v-if="data.deleted"
            class="text-sm text-muted-foreground"
            data-testid="chat-brand-reference-card-deleted"
        >
            {{ $t('chat.tool_card.reference_deleted') }}
        </p>

        <template v-else>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                    {{ data.label ?? data.original_filename }}
                </p>
                <p
                    v-if="data.label"
                    class="truncate text-xs text-muted-foreground"
                >
                    {{ data.original_filename }}
                </p>
            </div>

            <a
                :href="data.url ?? ''"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary hover:underline"
            >
                <IconExternalLink class="size-3.5" />
            </a>
        </template>
    </div>
</template>
