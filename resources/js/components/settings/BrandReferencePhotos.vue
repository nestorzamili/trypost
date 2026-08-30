<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    IconLoader2,
    IconPhotoPlus,
    IconTrash,
    IconUserCheck,
} from '@tabler/icons-vue';
import { ref } from 'vue';

import {
    destroy,
    store,
} from '@/actions/App/Http/Controllers/App/BrandReferencePhotoController';
import { AspectRatio } from '@/components/ui/aspect-ratio';
import { Button } from '@/components/ui/button';
import type { MediaItem } from '@/types/media';

defineProps<{
    references: MediaItem[];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const isUploading = ref(false);
const deletingId = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const triggerUpload = () => {
    fileInput.value?.click();
};

const handleFileChange = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    isUploading.value = true;
    errorMessage.value = null;

    const formData = new FormData();
    formData.append('photo', file);

    try {
        const response = await fetch(store.url(), {
            method: 'POST',
            body: formData,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(
                data.message || 'Failed to upload reference photo.',
            );
        }

        // Reload the page props to get the fresh reference list
        router.reload({ only: ['brandReferences'] });
    } catch (err: unknown) {
        errorMessage.value =
            err instanceof Error ? err.message : 'Upload failed';
    } finally {
        isUploading.value = false;
        if (target) target.value = '';
    }
};

const handleDelete = async (mediaId: string) => {
    if (deletingId.value) return;
    deletingId.value = mediaId;
    errorMessage.value = null;

    try {
        const response = await fetch(destroy.url(mediaId), {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to delete reference photo.');
        }

        router.reload({ only: ['brandReferences'] });
    } catch (err: unknown) {
        errorMessage.value =
            err instanceof Error ? err.message : 'Delete failed';
    } finally {
        deletingId.value = null;
    }
};
</script>

<template>
    <section class="grid gap-4 border-t-2 border-foreground pt-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="grid gap-1">
                <div class="flex items-center gap-2">
                    <IconUserCheck class="size-5 text-primary" />
                    <h2 class="text-lg font-bold">
                        {{ $t('settings.brand.reference_photos_title') }}
                    </h2>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ $t('settings.brand.reference_photos_description') }}
                </p>
            </div>

            <div>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="handleFileChange"
                />
                <Button
                    type="button"
                    size="sm"
                    :disabled="isUploading"
                    @click="triggerUpload"
                >
                    <IconLoader2
                        v-if="isUploading"
                        class="mr-2 size-4 animate-spin"
                    />
                    <IconPhotoPlus v-else class="mr-2 size-4" />
                    {{ $t('settings.brand.upload_reference_photo') }}
                </Button>
            </div>
        </div>

        <p v-if="errorMessage" class="text-sm font-medium text-destructive">
            {{ errorMessage }}
        </p>

        <!-- Empty state -->
        <div
            v-if="references.length === 0"
            class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-foreground/30 p-8 text-center"
        >
            <div class="rounded-full bg-muted p-3">
                <IconPhotoPlus class="size-6 text-muted-foreground" />
            </div>
            <h3 class="mt-3 text-sm font-semibold">
                {{ $t('settings.brand.no_reference_photos') }}
            </h3>
            <p class="mt-1 max-w-sm text-xs text-muted-foreground">
                {{ $t('settings.brand.no_reference_photos_hint') }}
            </p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="mt-4"
                :disabled="isUploading"
                @click="triggerUpload"
            >
                <IconPhotoPlus class="mr-2 size-4" />
                {{ $t('settings.brand.upload_first_photo') }}
            </Button>
        </div>

        <!-- Grid of Photos -->
        <div
            v-else
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
        >
            <div
                v-for="photo in references"
                :key="photo.id"
                class="group relative overflow-hidden rounded-lg border bg-card shadow-sm transition hover:shadow-md"
            >
                <AspectRatio :ratio="1">
                    <img
                        :src="photo.url"
                        :alt="photo.original_filename || 'Reference photo'"
                        class="size-full object-cover"
                    />
                </AspectRatio>

                <!-- Delete Overlay -->
                <div
                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100"
                >
                    <Button
                        type="button"
                        variant="destructive"
                        size="icon"
                        class="size-8 rounded-full"
                        :disabled="deletingId === photo.id"
                        @click="handleDelete(photo.id)"
                    >
                        <IconLoader2
                            v-if="deletingId === photo.id"
                            class="size-4 animate-spin"
                        />
                        <IconTrash v-else class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
