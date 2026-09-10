<script setup lang="ts">
import { computed } from 'vue';

import type { ChatBrandVariant } from '@/types/chat';

const props = defineProps<{
    data: ChatBrandVariant;
}>();

const colors = computed<[string, string][]>(() =>
    Object.entries(props.data.colors ?? {}),
);
</script>

<template>
    <div
        class="space-y-2 rounded-xl border border-foreground/15 bg-background p-3"
        data-testid="chat-brand-variant-card"
        dusk="chat-brand-variant-card"
    >
        <p
            v-if="data.deleted"
            class="text-sm text-muted-foreground"
            data-testid="chat-brand-variant-card-deleted"
        >
            {{ $t('chat.tool_card.variant_deleted') }}
        </p>

        <template v-else>
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-semibold">{{ data.label }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ data.language_code }}
                </p>
            </div>

            <div v-if="colors.length" class="flex flex-wrap gap-1.5">
                <span
                    v-for="[name, hex] in colors"
                    :key="name"
                    class="inline-flex items-center gap-1.5 rounded-full border border-foreground/15 px-2 py-0.5 text-xs"
                    :title="name"
                >
                    <span
                        class="size-3 rounded-full border border-foreground/20"
                        :style="{ backgroundColor: hex }"
                    />
                    {{ hex }}
                </span>
            </div>

            <p
                v-if="data.visual_notes"
                class="line-clamp-3 text-sm text-foreground/90"
            >
                {{ data.visual_notes }}
            </p>
        </template>
    </div>
</template>
