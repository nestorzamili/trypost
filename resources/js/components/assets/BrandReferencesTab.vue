<script setup lang="ts">
import {
    IconCloudUpload,
    IconLoader2,
    IconPencil,
    IconPhotoPlus,
    IconPlus,
    IconSearch,
    IconTrash,
} from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { toast } from 'vue-sonner';

import EmptyState from '@/components/EmptyState.vue';
import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import MediaPickerDialog from '@/components/posts/MediaPickerDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import debounce from '@/debounce';
import { BRAND_REFERENCE_LIMIT as MAX_REFERENCES } from '@/lib/brandReferences';
import { storeChunked } from '@/routes/app/assets';
import {
    destroy as referencesDestroy,
    fromAsset as referencesFromAsset,
    search as referencesSearch,
    update as referencesUpdate,
} from '@/routes/app/workspace/brand-references';
import type { BrandReferenceKind, MediaItem } from '@/types/media';
import { uploadChunked } from '@/utils/chunkedUpload';

const KINDS: BrandReferenceKind[] = [
    'face_closeup',
    'full_body',
    'logo',
    'product',
    'style',
    'other',
];

withDefaults(
    defineProps<{
        canManage?: boolean;
    }>(),
    {
        canManage: false,
    },
);

const kindLabel = (kind: BrandReferenceKind): string =>
    trans(`assets.references.kinds.${kind}`);

const csrfToken = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? '';

const references = ref<MediaItem[]>([]);
const unfilteredTotal = ref(0);
const searchTerm = ref('');
const kindFilter = ref<'all' | BrandReferenceKind>('all');
const page = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const loadingMore = ref(false);
const hasMore = computed(() => page.value < lastPage.value);
const remaining = computed(() =>
    Math.max(0, MAX_REFERENCES - unfilteredTotal.value),
);

const sentinel = useTemplateRef<HTMLDivElement>('sentinel');
let observer: IntersectionObserver | null = null;

const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const uploading = ref(false);
let uploadAbortController: AbortController | null = null;

const lightbox = ref<InstanceType<typeof ImagePreviewDialog> | null>(null);
const assetPicker = ref<InstanceType<typeof MediaPickerDialog> | null>(null);
const promoting = ref(false);

const editing = ref<MediaItem | null>(null);
const editDialogOpen = ref(false);
const editLabel = ref('');
const editKind = ref<BrandReferenceKind>('other');
const savingEdit = ref(false);

const deletingId = ref<string | null>(null);

const fetchReferences = async (pageNumber: number) => {
    const query: Record<string, string> = {
        search: searchTerm.value.trim(),
        page: String(pageNumber),
    };
    if (kindFilter.value !== 'all') query.kind = kindFilter.value;
    const response = await fetch(referencesSearch.url({ query }), {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
    if (!response.ok) throw new Error('Failed to load brand references');
    return (await response.json()) as {
        data: MediaItem[];
        meta: { current_page: number; last_page: number; total: number };
        unfiltered_total: number;
    };
};

const loadFirstPage = async () => {
    loading.value = true;
    try {
        const response = await fetchReferences(1);
        references.value = response.data;
        page.value = response.meta.current_page;
        lastPage.value = response.meta.last_page;
        unfilteredTotal.value = response.unfiltered_total;
    } catch {
        references.value = [];
    } finally {
        loading.value = false;
    }
};

const loadMore = async () => {
    if (loadingMore.value || !hasMore.value) return;
    loadingMore.value = true;
    try {
        const response = await fetchReferences(page.value + 1);
        references.value.push(...response.data);
        page.value = response.meta.current_page;
        lastPage.value = response.meta.last_page;
        unfilteredTotal.value = response.unfiltered_total;
    } catch {
        // ignore
    } finally {
        loadingMore.value = false;
    }
};

const debouncedSearch = debounce(() => {
    void loadFirstPage();
}, 300);

watch([searchTerm, kindFilter], () => debouncedSearch());

const setupObserver = () => {
    observer?.disconnect();
    observer = new IntersectionObserver(
        (entries) => {
            if (
                entries[0]?.isIntersecting &&
                hasMore.value &&
                !loadingMore.value
            ) {
                void loadMore();
            }
        },
        { rootMargin: '200px' },
    );
    if (sentinel.value) observer.observe(sentinel.value);
};

watch(sentinel, () => setupObserver());

const triggerFileInput = () => {
    if (remaining.value === 0 || uploading.value) return;
    fileInput.value?.click();
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files) {
        void uploadFiles(Array.from(target.files));
        target.value = '';
    }
};

const handleDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer?.files) {
        void uploadFiles(Array.from(event.dataTransfer.files));
    }
};

const uploadFiles = async (files: File[]) => {
    if (uploading.value || remaining.value === 0) return;
    uploading.value = true;
    uploadAbortController = new AbortController();
    const allowed = files.slice(0, remaining.value);
    for (const file of allowed) {
        try {
            await uploadChunked({
                file,
                url: storeChunked.url(),
                collection: 'brand_references',
                signal: uploadAbortController.signal,
            });
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                toast.info(trans('assets.upload.cancelled'));
                break;
            }
            toast.error(trans('assets.upload.failed', { file: file.name }));
        }
    }
    uploading.value = false;
    await loadFirstPage();
};

const openPreview = (item: MediaItem) => {
    const items = references.value.map((reference) => ({
        url: reference.url,
        type: 'image' as const,
    }));
    const index = references.value.findIndex(
        (reference) => reference.id === item.id,
    );
    lightbox.value?.openCollection(items, index);
};

const openEdit = (item: MediaItem) => {
    editing.value = item;
    editLabel.value = item.meta?.label ?? '';
    editKind.value = item.meta?.kind ?? 'other';
    editDialogOpen.value = true;
};

const saveEdit = async () => {
    if (!editing.value || savingEdit.value) return;
    savingEdit.value = true;
    try {
        const response = await fetch(referencesUpdate.url(editing.value.id), {
            method: 'PATCH',
            body: JSON.stringify({
                kind: editKind.value,
                label:
                    editLabel.value.trim() === ''
                        ? null
                        : editLabel.value.trim(),
            }),
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Failed to update reference photo.');
        toast.success(trans('assets.references.updated'));
        editDialogOpen.value = false;
        await loadFirstPage();
    } catch {
        toast.error(
            trans('assets.upload.failed', {
                file: editing.value.original_filename ?? '',
            }),
        );
    } finally {
        savingEdit.value = false;
    }
};

const handleDelete = async (id: string) => {
    if (deletingId.value) return;
    deletingId.value = id;
    try {
        const response = await fetch(referencesDestroy.url(id), {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('Failed to delete reference photo.');
        await loadFirstPage();
    } catch {
        toast.error(trans('assets.upload.failed', { file: '' }));
    } finally {
        deletingId.value = null;
    }
};

const handlePickedAssets = async (picked: { id: string }[]) => {
    if (promoting.value || picked.length === 0) return;
    if (remaining.value === 0) {
        toast.error(
            trans('assets.references.limit_reached', {
                max: String(MAX_REFERENCES),
            }),
        );
        return;
    }
    promoting.value = true;
    const allowed = picked.slice(0, remaining.value);
    if (allowed.length < picked.length) {
        toast.info(
            trans('assets.references.promote_limit', {
                remaining: String(remaining.value),
                max: String(MAX_REFERENCES),
            }),
        );
    }
    for (const asset of allowed) {
        try {
            const response = await fetch(referencesFromAsset.url(), {
                method: 'POST',
                body: JSON.stringify({ asset_id: asset.id }),
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('Failed to save reference.');
        } catch {
            toast.error(trans('assets.upload.failed', { file: '' }));
        }
    }
    promoting.value = false;
    toast.success(trans('assets.references.promoted'));
    await loadFirstPage();
};

defineExpose({ refresh: loadFirstPage });

onMounted(() => {
    void loadFirstPage();
});

onUnmounted(() => {
    observer?.disconnect();
    uploadAbortController?.abort();
});
</script>

<template>
    <div class="mt-6">
        <p class="mb-4 text-sm text-foreground/70">
            {{
                trans('assets.references.description', {
                    max: String(MAX_REFERENCES),
                })
            }}
        </p>

        <div
            v-if="canManage"
            class="relative mb-4 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed p-8 text-center transition-colors"
            :class="[
                isDragging
                    ? 'border-foreground bg-violet-100'
                    : 'border-foreground/25 bg-card hover:bg-foreground/5',
                uploading || remaining === 0
                    ? 'pointer-events-none opacity-60'
                    : '',
            ]"
            @click="triggerFileInput"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
        >
            <div
                class="inline-flex size-12 -rotate-3 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-200 shadow-2xs"
            >
                <IconCloudUpload
                    class="size-6 text-foreground"
                    stroke-width="2"
                />
            </div>
            <p class="text-sm font-semibold text-foreground">
                {{
                    remaining === 0
                        ? trans('assets.references.limit_reached', {
                              max: String(MAX_REFERENCES),
                          })
                        : trans('assets.references.upload.drag_drop')
                }}
            </p>
            <p class="text-xs text-foreground/60">
                {{ trans('assets.references.upload.formats') }}
            </p>
            <input
                ref="fileInput"
                type="file"
                class="hidden"
                multiple
                accept="image/jpeg,image/png,image/gif,image/webp"
                @change="handleFileSelect"
            />
            <div
                v-if="uploading"
                class="absolute inset-0 flex items-center justify-center rounded-2xl bg-card/85"
            >
                <div
                    class="flex items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <IconLoader2 class="size-4 animate-spin" />
                    {{ trans('assets.upload.uploading') }}
                </div>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="relative flex-1 sm:min-w-56">
                <IconSearch
                    class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-foreground/60"
                />
                <Input
                    v-model="searchTerm"
                    type="search"
                    :placeholder="trans('assets.references.search_placeholder')"
                    class="h-12 pl-11 text-base"
                />
            </div>
            <Select v-model="kindFilter">
                <SelectTrigger class="w-44">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{
                        trans('assets.references.all_kinds')
                    }}</SelectItem>
                    <SelectItem v-for="kind in KINDS" :key="kind" :value="kind">
                        {{ kindLabel(kind) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button
                v-if="canManage"
                type="button"
                variant="outline"
                :disabled="promoting || remaining === 0"
                @click="assetPicker?.open()"
            >
                <IconPlus class="mr-2 size-4" />
                {{ trans('assets.references.add_from_assets') }}
            </Button>
        </div>

        <div
            v-if="loading"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
        >
            <Skeleton
                v-for="i in 8"
                :key="i"
                class="aspect-square rounded-xl"
            />
        </div>

        <EmptyState
            v-else-if="references.length === 0"
            :icon="IconPhotoPlus"
            :title="trans('assets.references.empty.title')"
            :description="trans('assets.references.empty.description')"
        />

        <div
            v-else
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5"
        >
            <div
                v-for="item in references"
                :key="item.id"
                class="group relative cursor-pointer overflow-hidden rounded-xl border-2 border-foreground bg-muted shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-md"
                @click="openPreview(item)"
            >
                <div class="aspect-square">
                    <img
                        :src="item.url"
                        :alt="item.meta?.label || item.original_filename"
                        class="size-full object-cover"
                        loading="lazy"
                    />
                </div>

                <div
                    v-if="item.meta?.kind || item.meta?.label"
                    class="absolute inset-x-0 bottom-0 flex flex-col gap-0.5 bg-gradient-to-t from-black/70 to-transparent p-2 pt-6"
                >
                    <p
                        v-if="item.meta?.label"
                        class="truncate text-xs font-semibold text-white"
                    >
                        {{ item.meta.label }}
                    </p>
                    <p
                        v-if="item.meta?.kind"
                        class="text-[11px] font-medium text-white/70"
                    >
                        {{ kindLabel(item.meta.kind) }}
                    </p>
                </div>

                <div
                    v-if="canManage"
                    class="absolute inset-0 flex flex-col justify-between bg-transparent p-2 opacity-100 transition-opacity lg:bg-foreground/60 lg:opacity-0 lg:group-hover:opacity-100"
                >
                    <div class="flex justify-end gap-1.5">
                        <Button
                            variant="outline"
                            size="icon"
                            class="size-8"
                            @click.stop="openEdit(item)"
                        >
                            <IconPencil class="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            class="size-8 bg-rose-100 hover:bg-rose-200"
                            :disabled="deletingId === item.id"
                            @click.stop="handleDelete(item.id)"
                        >
                            <IconLoader2
                                v-if="deletingId === item.id"
                                class="size-4 animate-spin"
                            />
                            <IconTrash v-else class="size-4 text-rose-700" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="hasMore" ref="sentinel" class="mt-4 flex justify-center">
            <IconLoader2
                v-if="loadingMore"
                class="size-5 animate-spin text-foreground/60"
            />
        </div>

        <MediaPickerDialog
            ref="assetPicker"
            title-key="assets.references.from_assets_title"
            @select="handlePickedAssets"
        />

        <Dialog v-model:open="editDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{
                        trans('assets.references.edit')
                    }}</DialogTitle>
                </DialogHeader>
                <div class="grid gap-4 py-2">
                    <img
                        v-if="editing"
                        :src="editing.url"
                        :alt="editing.original_filename"
                        class="aspect-video w-full rounded-xl border-2 border-foreground object-cover"
                    />
                    <div class="space-y-1.5">
                        <Label>{{
                            trans('assets.references.kind_label')
                        }}</Label>
                        <Select v-model="editKind">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="kind in KINDS"
                                    :key="kind"
                                    :value="kind"
                                >
                                    {{ kindLabel(kind) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{
                            trans('assets.references.label_label')
                        }}</Label>
                        <Input
                            v-model="editLabel"
                            maxlength="100"
                            :placeholder="
                                trans('assets.references.label_placeholder')
                            "
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        :disabled="savingEdit"
                        @click="saveEdit"
                    >
                        <IconLoader2
                            v-if="savingEdit"
                            class="size-4 animate-spin"
                        />
                        {{ trans('assets.references.save') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ImagePreviewDialog ref="lightbox" />
    </div>
</template>
