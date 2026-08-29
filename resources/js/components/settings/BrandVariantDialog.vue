<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { IconPlus, IconTrash } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import FontPicker from '@/components/FontPicker.vue';
import HexColorInput from '@/components/HexColorInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import {
    store as brandVariantsStore,
    update as brandVariantsUpdate,
} from '@/routes/app/workspace/brand-variants';
import type { BrandVariant, BrandVariantLanguage } from '@/types';

interface ColorRow {
    name: string;
    hex: string;
}

const props = defineProps<{
    variant: BrandVariant | null;
    variantLanguages: BrandVariantLanguage[];
    availableFonts: string[];
    defaults?: Partial<BrandVariant>;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    language_code: 'en' as 'en' | 'zh',
    label: '',
    colors: {} as Record<string, string>,
    brand_color: null as string | null,
    background_color: null as string | null,
    text_color: null as string | null,
    headline_font: 'Inter',
    body_font: 'Inter',
    label_font: 'Inter',
    accent_font: '',
    visual_notes: '',
    sort_order: 0,
});

const colorRows = ref<ColorRow[]>([]);

const syncColors = () => {
    form.colors = Object.fromEntries(
        colorRows.value
            .filter((row) => row.name.trim() !== '')
            .map((row) => [row.name.trim(), row.hex]),
    );
};

const isEditing = computed(() => props.variant !== null);
const availableLanguages = computed(() =>
    props.variantLanguages.filter(
        (language) =>
            language.available || language.code === form.language_code,
    ),
);

const resetForm = () => {
    const variant = props.variant;
    const defaults = props.defaults ?? {};
    form.clearErrors();
    form.reset();
    form.language_code =
        variant?.language_code ??
        (defaults.language_code as 'en' | 'zh' | undefined) ??
        'en';
    form.label = variant?.label ?? (defaults.label as string | undefined) ?? '';
    form.colors =
        variant?.colors ??
        (defaults.colors as Record<string, string> | null | undefined) ??
        {};
    colorRows.value = Object.entries(form.colors).map(([name, hex]) => ({
        name,
        hex,
    }));
    form.brand_color =
        variant?.brand_color ??
        (defaults.brand_color as string | null | undefined) ??
        null;
    form.background_color =
        variant?.background_color ??
        (defaults.background_color as string | null | undefined) ??
        null;
    form.text_color =
        variant?.text_color ??
        (defaults.text_color as string | null | undefined) ??
        null;
    form.headline_font =
        variant?.headline_font ??
        (defaults.headline_font as string | undefined) ??
        'Inter';
    form.body_font =
        variant?.body_font ??
        (defaults.body_font as string | undefined) ??
        'Inter';
    form.label_font =
        variant?.label_font ??
        (defaults.label_font as string | undefined) ??
        'Inter';
    form.accent_font =
        variant?.accent_font ??
        (defaults.accent_font as string | null | undefined) ??
        '';
    form.visual_notes =
        variant?.visual_notes ??
        (defaults.visual_notes as string | undefined) ??
        '';
    form.sort_order = variant?.sort_order ?? 0;
};

watch(
    () => props.variant,
    () => {
        if (open.value) resetForm();
    },
    { immediate: true },
);

const addColor = () => {
    if (colorRows.value.length >= 20) return;
    colorRows.value.push({ name: '', hex: '#000000' });
};

const removeColor = (index: number) => {
    colorRows.value.splice(index, 1);
    syncColors();
};

const updateColorRow = (
    index: number,
    field: keyof ColorRow,
    value: string,
) => {
    const row = colorRows.value[index];
    if (!row) return;

    row[field] = value;
    syncColors();
};

const submit = () => {
    syncColors();

    const options = {
        onSuccess: () => {
            open.value = false;
        },
    };

    if (props.variant) {
        form.put(brandVariantsUpdate.url(props.variant.id), options);
    } else {
        form.post(brandVariantsStore.url(), options);
    }
};

const handleOpenChange = (value: boolean) => {
    if (value) resetForm();
    open.value = value;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        isEditing
                            ? $t('settings.brand.edit_variant')
                            : $t('settings.brand.add_variant')
                    }}
                </DialogTitle>
                <DialogDescription>{{
                    $t('settings.brand.variants_description')
                }}</DialogDescription>
            </DialogHeader>

            <form class="grid gap-5" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="variant-language">{{
                            $t('settings.brand.variant_language')
                        }}</Label>
                        <NativeSelect
                            id="variant-language"
                            v-model="form.language_code"
                            :disabled="isEditing"
                        >
                            <NativeSelectOption
                                v-for="language in availableLanguages"
                                :key="language.code"
                                :value="language.code"
                            >
                                {{ language.label }}
                            </NativeSelectOption>
                        </NativeSelect>
                        <p
                            v-if="form.errors.language_code"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.language_code }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="variant-label">{{
                            $t('settings.brand.variant_label')
                        }}</Label>
                        <Input
                            id="variant-label"
                            v-model="form.label"
                            :placeholder="$t('settings.brand.variant_label')"
                        />
                        <p
                            v-if="form.errors.label"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.label }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <Label>{{
                                $t('settings.brand.variant_colors')
                            }}</Label>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'settings.brand.variant_colors_description',
                                    )
                                }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="colorRows.length >= 20"
                            @click="addColor"
                        >
                            <IconPlus class="size-4" />
                            {{ $t('settings.brand.add_color') }}
                        </Button>
                    </div>
                    <div v-if="colorRows.length" class="grid gap-2">
                        <div
                            v-for="(row, index) in colorRows"
                            :key="index"
                            class="grid grid-cols-[1fr_9rem_auto] items-center gap-2"
                        >
                            <Input
                                :model-value="row.name"
                                :placeholder="$t('settings.brand.color_name')"
                                @update:model-value="
                                    updateColorRow(
                                        index,
                                        'name',
                                        String($event),
                                    )
                                "
                            />
                            <HexColorInput
                                :model-value="row.hex"
                                @update:model-value="
                                    updateColorRow(index, 'hex', $event ?? '')
                                "
                            />
                            <Button
                                type="button"
                                size="icon-sm"
                                variant="ghost"
                                @click="removeColor(index)"
                            >
                                <IconTrash class="size-4" />
                                <span class="sr-only">{{
                                    $t('settings.brand.remove_color')
                                }}</span>
                            </Button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        {{ $t('settings.brand.no_colors') }}
                    </p>
                    <p
                        v-if="form.errors.colors"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.colors }}
                    </p>
                </div>

                <div class="grid gap-3">
                    <Label>{{ $t('settings.brand.variant_roles') }}</Label>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="variant-brand-color">{{
                                $t('settings.brand.brand_color')
                            }}</Label>
                            <HexColorInput
                                id="variant-brand-color"
                                v-model="form.brand_color"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="variant-background-color">{{
                                $t('settings.brand.background_color')
                            }}</Label>
                            <HexColorInput
                                id="variant-background-color"
                                v-model="form.background_color"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="variant-text-color">{{
                                $t('settings.brand.text_color')
                            }}</Label>
                            <HexColorInput
                                id="variant-text-color"
                                v-model="form.text_color"
                            />
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{
                            $t('settings.brand.variant_headline_font')
                        }}</Label>
                        <FontPicker
                            v-model="form.headline_font"
                            :fonts="availableFonts"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            $t('settings.brand.variant_body_font')
                        }}</Label>
                        <FontPicker
                            v-model="form.body_font"
                            :fonts="availableFonts"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            $t('settings.brand.variant_label_font')
                        }}</Label>
                        <FontPicker
                            v-model="form.label_font"
                            :fonts="availableFonts"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>{{
                            $t('settings.brand.variant_accent_font')
                        }}</Label>
                        <FontPicker
                            v-model="form.accent_font"
                            :fonts="availableFonts"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="variant-visual-notes">{{
                        $t('settings.brand.variant_visual_notes')
                    }}</Label>
                    <Textarea
                        id="variant-visual-notes"
                        v-model="form.visual_notes"
                        rows="4"
                        :placeholder="
                            $t(
                                'settings.brand.variant_visual_notes_placeholder',
                            )
                        "
                    />
                    <p
                        v-if="form.errors.visual_notes"
                        class="text-sm text-destructive"
                    >
                        {{ form.errors.visual_notes }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? $t('settings.workspace.save')
                                : $t('settings.workspace.save')
                        }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        >{{ $t('common.cancel') }}</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
