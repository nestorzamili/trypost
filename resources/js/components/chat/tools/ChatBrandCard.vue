<script setup lang="ts">
import { computed } from 'vue';

import type { ChatBrand } from '@/types/chat';

const props = defineProps<{
    data: ChatBrand;
}>();

const colors = computed<[string, string][]>(() =>
    Object.entries(props.data.variants[0]?.colors ?? {}),
);

const voice = computed<string>(() =>
    (props.data.brand_voice_traits ?? []).join(', '),
);
</script>

<template>
    <div
        class="space-y-2 rounded-xl border border-foreground/15 bg-background p-3"
        data-testid="chat-brand-card"
        dusk="chat-brand-card"
    >
        <p class="text-sm font-semibold">{{ data.name }}</p>

        <p
            v-if="data.brand_description"
            class="line-clamp-3 text-sm text-foreground/90"
        >
            {{ data.brand_description }}
        </p>

        <p v-if="voice" class="text-xs text-muted-foreground">
            {{ voice }}
        </p>

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

        <p v-if="data.variants.length" class="text-xs text-muted-foreground">
            {{ data.variants.map((variant) => variant.label).join(' · ') }}
        </p>

        <p
            v-if="data.reference_photos.length"
            class="text-xs text-muted-foreground"
        >
            {{ data.reference_photos.length }} reference
            {{ data.reference_photos.length === 1 ? 'photo' : 'photos' }}
        </p>
    </div>
</template>
