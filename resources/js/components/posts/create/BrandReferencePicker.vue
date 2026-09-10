<script setup lang="ts">
import { IconCheck, IconEye, IconLoader2, IconPlus } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import { Button } from '@/components/ui/button';
import { MediaType } from '@/lib/mediaType';
import { store as storeReference } from '@/routes/app/workspace/brand-references';
import type { MediaItem } from '@/types/media';

const props = withDefaults(
    defineProps<{
        references: MediaItem[];
        canManage?: boolean;
    }>(),
    {
        canManage: true,
    },
);

const emit = defineEmits<{
    referenceAdded: [item: MediaItem];
}>();

const selectedIds = defineModel<string[]>('selectedIds', { default: () => [] });

const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);
const lightbox = ref<InstanceType<typeof ImagePreviewDialog> | null>(null);

const kindLabel = (reference: MediaItem): string | null => {
    const kind = reference.meta?.kind;
    if (!kind || kind === 'other') return null;

    return trans(`assets.brand_references.kinds.${kind}`);
};

const openLightbox = (index: number): void => {
    const collection = props.references.map((reference) => ({
        url: reference.url,
        type: MediaType.Image,
        altText: reference.meta?.label ?? undefined,
    }));

    lightbox.value?.openCollection(collection, index);
};

const allSelected = computed(
    () =>
        props.references.length > 0 &&
        selectedIds.value.length === props.references.length,
);

const isSelected = (id: string) => selectedIds.value.includes(id);

const toggle = (id: string) => {
    selectedIds.value = isSelected(id)
        ? selectedIds.value.filter((selected) => selected !== id)
        : [...selectedIds.value, id];
};

const selectAll = () => {
    selectedIds.value = props.references.map((reference) => reference.id);
};

const clear = () => {
    selectedIds.value = [];
};

const triggerFileInput = () => {
    if (uploading.value) return;
    fileInput.value?.click();
};

const handleFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;
    if (!files || files.length === 0) return;

    const file = files[0];

    const MAX_PHOTO_SIZE_BYTES = 10 * 1024 * 1024;
    if (file.size > MAX_PHOTO_SIZE_BYTES) {
        toast.error(trans('assets.upload.file_too_large', { max: 10 }));
        if (target) {
            target.value = '';
        }
        return;
    }

    uploading.value = true;

    try {
        const csrfToken =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';

        const formData = new FormData();
        formData.append('photo', file);
        formData.append('label', file.name.replace(/\.[^/.]+$/, ''));
        formData.append('kind', 'other');

        const response = await fetch(storeReference.url(), {
            method: 'POST',
            body: formData,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!response.ok) {
            if (response.status === 413) {
                toast.error(trans('assets.upload.file_too_large', { max: 10 }));
                return;
            }
            const err = await response.json().catch(() => null);
            const msg =
                err?.message ||
                err?.errors?.photo?.[0] ||
                trans('posts.wizard.failed');
            toast.error(msg);
            return;
        }

        const resData = (await response.json()) as
            | { data?: MediaItem }
            | MediaItem;
        const newItem: MediaItem =
            'data' in resData && resData.data
                ? resData.data
                : (resData as MediaItem);

        emit('referenceAdded', newItem);

        if (!selectedIds.value.includes(newItem.id)) {
            selectedIds.value = [...selectedIds.value, newItem.id];
        }

        toast.success(newItem.meta?.label || newItem.original_filename);
    } catch {
        toast.error(trans('posts.wizard.failed'));
    } finally {
        uploading.value = false;
        if (target) {
            target.value = '';
        }
    }
};
</script>

<template>
    <div class="space-y-3">
        <!-- Hidden input for file upload -->
        <input
            ref="fileInput"
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="hidden"
            @change="handleFileChange"
        />

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-foreground/60">
                {{ $t('posts.wizard.brand_references_description') }}
            </p>

            <div class="flex items-center gap-1.5">
                <Button
                    v-if="canManage"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5 text-xs font-semibold"
                    :disabled="uploading"
                    @click="triggerFileInput"
                >
                    <IconLoader2
                        v-if="uploading"
                        class="size-3.5 animate-spin"
                    />
                    <IconPlus v-else class="size-3.5" stroke-width="2.5" />
                    {{
                        uploading
                            ? $t('posts.wizard.brand_references_uploading')
                            : $t('posts.wizard.brand_references_attach')
                    }}
                </Button>

                <Button
                    v-if="references.length > 0"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs font-medium"
                    :disabled="allSelected"
                    @click="selectAll"
                >
                    {{ $t('posts.wizard.brand_references_select_all') }}
                </Button>
                <Button
                    v-if="references.length > 0"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs font-medium"
                    :disabled="selectedIds.length === 0"
                    @click="clear"
                >
                    {{ $t('posts.wizard.brand_references_clear') }}
                </Button>
            </div>
        </div>

        <!-- Empty state when no references exist -->
        <div
            v-if="references.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-foreground/20 p-6 text-center"
        >
            <p class="text-xs text-foreground/70">
                {{ $t('posts.wizard.brand_references_empty') }}
            </p>
            <Button
                v-if="canManage"
                type="button"
                variant="outline"
                size="sm"
                class="gap-1.5 font-semibold"
                :disabled="uploading"
                @click="triggerFileInput"
            >
                <IconLoader2 v-if="uploading" class="size-4 animate-spin" />
                <IconPlus v-else class="size-4" stroke-width="2.5" />
                {{
                    uploading
                        ? $t('posts.wizard.brand_references_uploading')
                        : $t('posts.wizard.brand_references_attach')
                }}
            </Button>
        </div>

        <!-- Gallery grid -->
        <div
            v-else
            class="grid grid-cols-3 gap-2.5 sm:grid-cols-4 md:grid-cols-6"
        >
            <button
                v-for="(reference, refIndex) in references"
                :key="reference.id"
                type="button"
                class="group relative cursor-pointer overflow-hidden rounded-xl border-2 border-foreground bg-card text-left shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-md"
                :class="{
                    '!bg-violet-100 shadow-md ring-2 ring-foreground':
                        isSelected(reference.id),
                }"
                @click="toggle(reference.id)"
            >
                <div class="aspect-square w-full overflow-hidden bg-muted">
                    <img
                        :src="reference.url"
                        :alt="
                            reference.meta?.label || reference.original_filename
                        "
                        class="size-full object-cover transition-transform group-hover:scale-105"
                        loading="lazy"
                    />
                </div>

                <span
                    v-if="kindLabel(reference)"
                    class="pointer-events-none absolute top-1.5 left-1.5 rounded-md bg-black/65 px-1.5 py-0.5 text-[10px] font-semibold text-white backdrop-blur-sm"
                >
                    {{ kindLabel(reference) }}
                </span>

                <span
                    role="button"
                    :aria-label="$t('posts.wizard.brand_references_view')"
                    class="absolute bottom-1.5 left-1.5 inline-flex size-6 items-center justify-center rounded-full border-2 border-foreground bg-card text-foreground opacity-0 shadow-2xs transition-opacity group-hover:opacity-100 focus:opacity-100"
                    data-testid="brand-reference-view"
                    @click.stop="openLightbox(refIndex)"
                >
                    <IconEye class="size-3" />
                </span>

                <div
                    v-if="isSelected(reference.id)"
                    class="absolute top-1.5 right-1.5 inline-flex size-6 items-center justify-center rounded-full border-2 border-foreground bg-primary text-primary-foreground shadow-2xs"
                >
                    <IconCheck class="size-3.5" stroke-width="3" />
                </div>
                <p
                    v-if="reference.meta?.label"
                    class="truncate px-1.5 py-1 text-[11px] font-semibold text-foreground"
                >
                    {{ reference.meta.label }}
                </p>
            </button>

            <!-- Quick Add card at the end of the grid -->
            <button
                v-if="canManage"
                type="button"
                class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-foreground/30 bg-muted/30 text-foreground/70 transition-all hover:border-foreground hover:bg-muted/60 hover:text-foreground"
                :disabled="uploading"
                @click="triggerFileInput"
            >
                <IconLoader2 v-if="uploading" class="size-5 animate-spin" />
                <IconPlus v-else class="size-5" stroke-width="2.5" />
                <span class="text-[11px] font-semibold">
                    {{
                        uploading
                            ? $t('posts.wizard.brand_references_uploading')
                            : $t('posts.wizard.brand_references_attach')
                    }}
                </span>
            </button>
        </div>

        <ImagePreviewDialog ref="lightbox" />
    </div>
</template>
