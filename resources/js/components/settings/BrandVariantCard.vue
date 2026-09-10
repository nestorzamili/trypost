<script setup lang="ts">
import { IconPencil, IconTrash } from '@tabler/icons-vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { BrandVariant } from '@/types';

const props = defineProps<{
    variant: BrandVariant;
}>();

const emit = defineEmits<{
    edit: [variant: BrandVariant];
    delete: [variant: BrandVariant];
}>();

const colors = [
    ['brand_color', 'settings.brand.brand_color'],
    ['background_color', 'settings.brand.background_color'],
    ['text_color', 'settings.brand.text_color'],
] as const;

const fonts = [
    ['headline_font', 'settings.brand.variant_headline_font'],
    ['body_font', 'settings.brand.variant_body_font'],
    ['label_font', 'settings.brand.variant_label_font'],
    ['accent_font', 'settings.brand.variant_accent_font'],
] as const;
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-start justify-between gap-4">
            <div class="grid gap-1">
                <CardTitle class="flex items-center gap-2">
                    {{ variant.label }}
                    <span
                        class="rounded-full bg-secondary px-2 py-0.5 text-xs font-semibold uppercase"
                    >
                        {{ variant.language_code }}
                    </span>
                </CardTitle>
            </div>
            <div class="flex gap-2">
                <Button
                    type="button"
                    size="icon-sm"
                    variant="outline"
                    @click="emit('edit', props.variant)"
                >
                    <IconPencil class="size-4" />
                    <span class="sr-only">{{
                        $t('settings.brand.edit_variant')
                    }}</span>
                </Button>
                <Button
                    type="button"
                    size="icon-sm"
                    variant="destructive"
                    @click="emit('delete', props.variant)"
                >
                    <IconTrash class="size-4" />
                    <span class="sr-only">{{
                        $t('settings.brand.delete_variant')
                    }}</span>
                </Button>
            </div>
        </CardHeader>
        <CardContent class="grid gap-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div
                    v-for="[key, label] in colors"
                    :key="key"
                    class="grid gap-1"
                >
                    <span class="text-xs font-semibold text-muted-foreground">{{
                        $t(label)
                    }}</span>
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span
                            class="size-6 rounded-md border-2 border-foreground shadow-2xs"
                            :style="{
                                backgroundColor: variant[key] ?? undefined,
                            }"
                        />
                        {{ variant[key] || '—' }}
                    </div>
                </div>
            </div>

            <div
                v-if="variant.colors && Object.keys(variant.colors).length"
                class="grid gap-2"
            >
                <span class="text-xs font-semibold text-muted-foreground">{{
                    $t('settings.brand.variant_colors')
                }}</span>
                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="(hex, name) in variant.colors"
                        :key="name"
                        class="flex items-center gap-2 rounded-md border-2 border-foreground bg-card px-2 py-1 text-xs font-medium"
                    >
                        <span
                            class="size-4 rounded border border-foreground"
                            :style="{ backgroundColor: hex }"
                        />
                        <span>{{ name }}</span>
                        <span class="text-muted-foreground">{{ hex }}</span>
                    </div>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div
                    v-for="[key, label] in fonts"
                    :key="key"
                    class="grid gap-1"
                >
                    <span class="text-xs font-semibold text-muted-foreground">{{
                        $t(label)
                    }}</span>
                    <span
                        v-if="variant[key]"
                        :style="{ fontFamily: `'${variant[key]}', sans-serif` }"
                        class="text-sm"
                    >
                        {{ variant[key] }}
                    </span>
                    <span v-else class="text-sm text-muted-foreground">—</span>
                </div>
            </div>

            <p
                v-if="variant.visual_notes"
                class="text-sm whitespace-pre-wrap text-muted-foreground"
            >
                {{ variant.visual_notes }}
            </p>
        </CardContent>
    </Card>
</template>
