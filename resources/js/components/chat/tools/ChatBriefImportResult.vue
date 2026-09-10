<script setup lang="ts">
import { IconFileImport } from '@tabler/icons-vue';

import { index as postsIndex } from '@/routes/app/posts';

defineProps<{
    data: {
        created_count: number;
        failed_count: number;
        label: string;
        post_ids: string[];
    } | null;
}>();
</script>

<template>
    <div
        v-if="data"
        class="mt-2 flex flex-col gap-2 rounded-xl border border-foreground/15 bg-background p-3"
        data-testid="chat-brief-import-result"
    >
        <div class="flex items-center gap-2 text-sm">
            <IconFileImport class="size-4 shrink-0 text-muted-foreground" />
            <span class="font-medium">
                {{
                    $t('chat.brief_import.created', {
                        count: String(data.created_count),
                    })
                }}
            </span>
        </div>

        <p
            v-if="data.failed_count > 0"
            class="text-xs text-muted-foreground"
        >
            {{
                $t('chat.brief_import.failed', {
                    count: String(data.failed_count),
                })
            }}
        </p>

        <a
            v-if="data.created_count > 0"
            :href="postsIndex.url('draft')"
            class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
        >
            {{ $t('chat.brief_import.view_drafts', { label: data.label }) }}
        </a>
    </div>
</template>
