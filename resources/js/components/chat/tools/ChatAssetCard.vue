<script setup lang="ts">
import { IconExternalLink } from '@tabler/icons-vue';

import type { ChatAsset } from '@/types/chat';

defineProps<{
    data: ChatAsset;
}>();

const formatSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <div
        class="flex items-center gap-2 rounded-xl border border-foreground/15 bg-background p-3"
        data-testid="chat-asset-card"
        dusk="chat-asset-card"
    >
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">
                {{ data.original_filename }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ data.type }} · {{ formatSize(data.size) }}
            </p>
        </div>

        <a
            :href="data.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary hover:underline"
        >
            <IconExternalLink class="size-3.5" />
        </a>
    </div>
</template>
