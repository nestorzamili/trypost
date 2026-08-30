<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import WorkspaceController from '@/actions/App/Http/Controllers/App/WorkspaceController';
import BrandForm from '@/components/BrandForm.vue';
import ConfirmDeleteModal from '@/components/ConfirmDeleteModal.vue';
import BrandReferencePhotos from '@/components/settings/BrandReferencePhotos.vue';
import BrandVariantCard from '@/components/settings/BrandVariantCard.vue';
import BrandVariantDialog from '@/components/settings/BrandVariantDialog.vue';
import { Button } from '@/components/ui/button';
import { destroy as brandVariantsDestroy } from '@/routes/app/workspace/brand-variants';
import type {
    BrandVariant,
    BrandVariantLanguage,
    ContentLanguageOption,
} from '@/types';
import type { MediaItem } from '@/types/media';

interface Workspace {
    id: string;
    name: string;
    brand_website: string | null;
    brand_description: string | null;
    brand_guidelines: string | null;
    brand_voice_traits: string[] | null;
    brand_color: string | null;
    background_color: string | null;
    text_color: string | null;
    brand_font: string;
    image_style: string;
    content_language: string;
    brand_variants: BrandVariant[];
}

const props = defineProps<{
    workspace: Workspace;
    brandReferences?: MediaItem[];
    availableFonts: string[];
    availableImageStyles: string[];
    availableVoiceTraits: Record<string, string[]>;
    availableContentLanguages: ContentLanguageOption[];
    variantLanguages: BrandVariantLanguage[];
}>();

const form = useForm({
    name: props.workspace.name,
    brand_website: props.workspace.brand_website ?? '',
    brand_description: props.workspace.brand_description ?? '',
    brand_guidelines: props.workspace.brand_guidelines ?? '',
    brand_voice_traits: props.workspace.brand_voice_traits ?? [],
    brand_color: props.workspace.brand_color,
    background_color: props.workspace.background_color,
    text_color: props.workspace.text_color,
    brand_font: props.workspace.brand_font ?? 'Inter',
    image_style: props.workspace.image_style ?? 'cinematic',
    content_language: props.workspace.content_language ?? 'en',
    logo_url: '' as string | null,
});

const variantDialogOpen = ref(false);
const selectedVariant = ref<BrandVariant | null>(null);
const canAddVariant = computed(() =>
    props.variantLanguages.some((language) => language.available),
);
const deleteModal = ref<InstanceType<typeof ConfirmDeleteModal> | null>(null);

const submit = () => {
    if (form.processing) return;
    form.put(WorkspaceController.updateSettings.url(), {
        preserveScroll: true,
    });
};

const addVariant = () => {
    selectedVariant.value = null;
    variantDialogOpen.value = true;
};

const editVariant = (variant: BrandVariant) => {
    selectedVariant.value = variant;
    variantDialogOpen.value = true;
};

const deleteVariant = (variant: BrandVariant) => {
    deleteModal.value?.open({
        url: brandVariantsDestroy.url(variant.id),
    });
};
</script>

<template>
    <div class="flex flex-col gap-8">
        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <HeadingSmall
                :title="$t('settings.brand.title')"
                :description="$t('settings.brand.description')"
            />

            <BrandForm
                :fields="form"
                :errors="form.errors"
                :available-fonts="availableFonts"
                :available-image-styles="availableImageStyles"
                :available-voice-traits="availableVoiceTraits"
                :available-content-languages="availableContentLanguages"
                :autofill="!workspace.brand_website"
                :show-default-visuals="workspace.brand_variants.length === 0"
            />

            <Button type="submit" :disabled="form.processing">
                {{ $t('settings.workspace.save') }}
            </Button>
        </form>

        <section class="grid gap-4 border-t-2 border-foreground pt-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="grid gap-1">
                    <h2 class="text-lg font-bold">
                        {{ $t('settings.brand.variants_title') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ $t('settings.brand.variants_description') }}
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    :disabled="!canAddVariant"
                    @click="addVariant"
                >
                    {{ $t('settings.brand.add_variant') }}
                </Button>
            </div>

            <p
                v-if="workspace.brand_variants.length === 0"
                class="rounded-lg border-2 border-dashed border-foreground/40 p-4 text-sm text-muted-foreground"
            >
                {{ $t('settings.brand.variant_upgrade_prompt') }}
            </p>

            <div v-else class="grid gap-4">
                <BrandVariantCard
                    v-for="variant in workspace.brand_variants"
                    :key="variant.id"
                    :variant="variant"
                    @edit="editVariant"
                    @delete="deleteVariant"
                />
            </div>
        </section>

        <BrandReferencePhotos :references="brandReferences ?? []" />

        <BrandVariantDialog
            v-model:open="variantDialogOpen"
            :variant="selectedVariant"
            :variant-languages="variantLanguages"
            :available-fonts="availableFonts"
            :defaults="{
                language_code:
                    variantLanguages.find((language) => language.available)
                        ?.code ?? 'en',
                label:
                    variantLanguages.find((language) => language.available)
                        ?.label ?? '',
                brand_color: workspace.brand_color,
                background_color: workspace.background_color,
                text_color: workspace.text_color,
                headline_font: workspace.brand_font,
                body_font: workspace.brand_font,
                label_font: workspace.brand_font,
                colors: {
                    ...(workspace.brand_color
                        ? { 'Brand Color': workspace.brand_color }
                        : {}),
                    ...(workspace.background_color
                        ? { Background: workspace.background_color }
                        : {}),
                    ...(workspace.text_color
                        ? { Text: workspace.text_color }
                        : {}),
                },
            }"
        />

        <ConfirmDeleteModal
            ref="deleteModal"
            :title="$t('settings.brand.delete_variant')"
            :description="$t('settings.brand.variant_confirm_delete')"
            :action="$t('settings.brand.delete_variant')"
            :cancel="$t('common.cancel')"
        />
    </div>
</template>
