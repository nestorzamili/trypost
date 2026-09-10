<script setup lang="ts">
import { IconFileSpreadsheet } from '@tabler/icons-vue';
import { computed } from 'vue';

import { parseUserMessage } from '@/lib/chat/userMessage';

const props = defineProps<{
    text: string;
}>();

const parsed = computed(() => parseUserMessage(props.text));
</script>

<template>
    <div
        class="flex animate-in justify-end duration-300 fade-in slide-in-from-bottom-2 motion-reduce:animate-none"
    >
        <div
            class="max-w-[90%] min-w-0 rounded-2xl rounded-tr-md border-2 border-foreground bg-amber-100 px-3.5 py-2.5 shadow-2xs"
        >
            <p
                v-if="parsed.text"
                class="text-sm leading-relaxed text-foreground whitespace-pre-wrap"
            >
                {{ parsed.text }}
            </p>

            <div
                v-if="parsed.attachment"
                class="inline-flex items-center gap-2 rounded-xl border border-foreground/20 bg-background/80 px-3 py-1.5 text-xs text-foreground shadow-2xs"
                :class="{ 'mt-2': parsed.text }"
                data-testid="chat-user-message-attachment"
            >
                <IconFileSpreadsheet
                    class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                />
                <span class="max-w-[220px] truncate font-medium">{{
                    parsed.attachment.filename
                }}</span>
            </div>
        </div>
    </div>
</template>
