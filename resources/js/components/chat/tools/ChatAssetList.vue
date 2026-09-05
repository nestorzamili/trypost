<script setup lang="ts">
import { IconExternalLink } from '@tabler/icons-vue';

import type { ChatAsset } from '@/types/chat';

defineProps<{
    data: ChatAsset[];
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
    <div class="space-y-2" data-testid="chat-asset-list" dusk="chat-asset-list">
        <p v-if="!data.length" class="text-sm text-muted-foreground">
            {{ $t('chat.tool_card.empty_assets') }}
        </p>

        <div
            v-for="asset in data"
            :key="asset.id"
            class="flex items-center gap-2 rounded-xl border border-foreground/15 bg-background p-3"
        >
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium">
                    {{ asset.original_filename }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ asset.type }} · {{ formatSize(asset.size) }}
                </p>
            </div>

            <a
                :href="asset.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-primary hover:underline"
            >
                <IconExternalLink class="size-3.5" />
            </a>
        </div>
    </div>
</template>
