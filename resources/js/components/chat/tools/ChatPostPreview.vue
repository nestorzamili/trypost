<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { IconEye, IconEyeOff } from '@tabler/icons-vue';
import { computed, onMounted, ref } from 'vue';

import ImagePreviewDialog from '@/components/ImagePreviewDialog.vue';
import PreviewTab from '@/components/posts/editor/PreviewTab.vue';
import { MediaType } from '@/lib/mediaType';
import { chatPreview } from '@/routes/app/posts';
import type { MediaItem } from '@/types/media';

interface PreviewSocialAccount {
    id: string;
    platform: string;
    display_name: string | null;
    username: string | null;
    display_label: string;
    handle_label: string;
    avatar_url: string | null;
}

interface PreviewPlatform {
    id: string;
    platform: string;
    platform_name: string | null;
    platform_avatar: string | null;
    content_type: string | null;
    enabled: boolean;
    social_account: PreviewSocialAccount | null;
}

interface ChatPostPreviewData {
    content: string;
    media: MediaItem[];
    platforms: PreviewPlatform[];
    platform_content_types: Record<string, string>;
    platform_meta: Record<string, Record<string, unknown>>;
    contents: Record<string, string>;
}

const props = defineProps<{
    postId: string;
    /** Reserve N skeleton tiles while the media loads (avoids layout shift). */
    expectedMedia?: number;
}>();

const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const data = ref<ChatPostPreviewData | null>(null);
const lightbox = ref<InstanceType<typeof ImagePreviewDialog> | null>(null);

const http = useHttp<Record<string, never>, ChatPostPreviewData>({});

const skeletonCount = computed<number>(() =>
    Math.min(4, Math.max(1, props.expectedMedia ?? 1)),
);

const mediaItems = computed<MediaItem[]>(() => data.value?.media ?? []);
const hasThumbnails = computed<boolean>(() => mediaItems.value.length > 0);

const mediaTypeOf = (item: MediaItem): MediaType =>
    (item.type as MediaType | undefined) ?? MediaType.Image;

/** Eagerly load the post's media so generated images show without expanding. */
const fetchData = async (): Promise<void> => {
    if (data.value !== null || loading.value) {
        return;
    }

    loading.value = true;
    failed.value = false;

    try {
        data.value = await http.get(chatPreview.url({ post: props.postId }));
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
};

const openLightbox = (index: number): void => {
    const collection = mediaItems.value.map((item) => ({
        url: item.url,
        type: mediaTypeOf(item),
        altText: item.meta?.alt_text ?? undefined,
    }));

    lightbox.value?.openCollection(collection, index);
};

const toggle = (): void => {
    open.value = !open.value;
    void fetchData();
};

onMounted(() => {
    // Thumbnails are the primary artifact of a generation, so load them up
    // front rather than waiting for the user to expand the phone preview.
    void fetchData();
});
</script>

<template>
    <div data-testid="chat-post-preview">
        <!-- IMG-2: skeleton tiles reserve space while media loads (no CLS). -->
        <div
            v-if="loading && !hasThumbnails"
            class="mb-2 flex gap-1.5"
            data-testid="chat-post-preview-thumbs-skeleton"
        >
            <div
                v-for="n in skeletonCount"
                :key="n"
                class="size-16 shrink-0 animate-pulse rounded-lg bg-accent"
            />
        </div>

        <!-- IMG-1: generated images shown inline; click opens the lightbox. -->
        <div
            v-else-if="hasThumbnails"
            class="mb-2 flex flex-wrap gap-1.5"
            data-testid="chat-post-preview-thumbs"
        >
            <button
                v-for="(item, index) in mediaItems"
                :key="item.id"
                type="button"
                class="group relative size-16 shrink-0 overflow-hidden rounded-lg border border-foreground/15 bg-accent transition hover:ring-2 hover:ring-primary"
                :aria-label="$t('chat.post_generation.view_image')"
                data-testid="chat-post-preview-thumb"
                @click="openLightbox(index)"
            >
                <video
                    v-if="mediaTypeOf(item) === 'video'"
                    :src="item.url"
                    class="size-full object-cover"
                    muted
                    playsinline
                    preload="metadata"
                />
                <img
                    v-else
                    :src="item.url"
                    :alt="item.meta?.alt_text ?? ''"
                    class="size-full object-cover transition-transform group-hover:scale-105"
                    loading="lazy"
                />
            </button>
        </div>

        <button
            type="button"
            class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
            data-testid="chat-post-preview-toggle"
            @click="toggle"
        >
            <component :is="open ? IconEyeOff : IconEye" class="size-3.5" />
            {{
                open
                    ? $t('chat.post_generation.preview_hide')
                    : $t('chat.post_generation.preview_show')
            }}
        </button>

        <div v-if="open" class="mt-2">
            <div
                v-if="loading"
                class="space-y-2 rounded-xl border border-foreground/15 bg-background p-3"
                data-testid="chat-post-preview-loading"
            >
                <div class="h-4 w-2/3 animate-pulse rounded bg-accent" />
                <div
                    class="mx-auto h-64 w-full max-w-75 animate-pulse rounded-3xl bg-accent"
                />
            </div>

            <p
                v-else-if="failed || data === null"
                class="text-xs text-muted-foreground"
                data-testid="chat-post-preview-error"
            >
                {{ $t('chat.post_generation.preview_error') }}
            </p>

            <div
                v-else
                class="overflow-hidden rounded-xl border border-foreground/15 bg-background"
            >
                <PreviewTab
                    :platforms="data.platforms"
                    :content="data.content"
                    :media="data.media"
                    :platform-content-types="data.platform_content_types"
                    :platform-meta="data.platform_meta"
                    :platform-contents="data.contents"
                    show-disabled-badge
                />
            </div>
        </div>

        <ImagePreviewDialog ref="lightbox" />
    </div>
</template>
