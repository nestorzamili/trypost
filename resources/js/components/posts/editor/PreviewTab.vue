<script setup lang="ts">
import { IconDeviceMobile } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import PhoneMockup from '@/components/PhoneMockup.vue';
import { PlatformPreview } from '@/components/posts/previews';
import { Avatar } from '@/components/ui/avatar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import type { MediaItem } from '@/types/media';

interface SocialAccount {
    id: string;
    platform: string;
    display_name: string | null;
    username: string | null;
    display_label: string;
    handle_label: string;
    avatar_url: string | null;
}

interface PostPlatform {
    id: string;
    platform: string;
    platform_name: string | null;
    platform_avatar: string | null;
    content_type: string | null;
    enabled?: boolean;
    social_account: SocialAccount | null;
}

const props = defineProps<{
    platforms: PostPlatform[];
    content: string;
    media: MediaItem[];
    platformContentTypes: Record<string, string>;
    platformMeta?: Record<string, Record<string, any>>;
    postedAt?: string | null;
    /**
     * Per-platform content override, keyed by post platform id. Lets a
     * read-only preview show each platform's sanitized text (the editor
     * passes one shared draft instead). Absent entries fall back to content:
     * keys always ship with the platforms map, so a miss only means the row
     * was added after the fetch — raw text beats a blank preview there.
     */
    platformContents?: Record<string, string>;
    /** Mark rows that won't publish. The editor only lists enabled ones. */
    showDisabledBadge?: boolean;
}>();

const getPlatformAvatar = (pp: PostPlatform): string | null =>
    pp.social_account?.avatar_url ?? pp.platform_avatar ?? null;
const getPlatformDisplayName = (pp: PostPlatform): string =>
    pp.social_account?.display_label ?? pp.platform_name ?? pp.platform;

const activeId = ref<string | null>(props.platforms[0]?.id ?? null);

watch(
    () => props.platforms,
    (next) => {
        if (!next.find((pp) => pp.id === activeId.value)) {
            activeId.value = next[0]?.id ?? null;
        }
    },
);

const activePlatform = computed(
    () => props.platforms.find((pp) => pp.id === activeId.value) ?? null,
);
const activeContentType = computed((): string | undefined => {
    if (!activePlatform.value) return undefined;
    return (
        props.platformContentTypes[activePlatform.value.id] ??
        activePlatform.value.content_type ??
        undefined
    );
});
const activeContent = computed(
    (): string =>
        (activePlatform.value &&
            props.platformContents?.[activePlatform.value.id]) ??
        props.content,
);
/**
 * `PlatformPreview` requires non-null names; the endpoint may send nulls for
 * rows whose account was deleted. Normalize here so the preview never breaks
 * on a dangling row.
 */
const activeSocialAccount = computed(() => {
    const account = activePlatform.value?.social_account ?? null;

    if (account === null) {
        return null;
    }

    return {
        ...account,
        display_name: account.display_name ?? '',
        username: account.username ?? '',
    };
});
</script>

<template>
    <div class="flex h-full flex-col">
        <div
            v-if="platforms.length > 1"
            class="border-b-2 border-foreground/10 px-4 py-3"
        >
            <div class="flex flex-wrap gap-3">
                <TooltipProvider
                    v-for="pp in platforms"
                    :key="pp.id"
                    :delay-duration="200"
                >
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="relative cursor-pointer transition-opacity"
                                :class="
                                    activeId === pp.id
                                        ? 'opacity-100'
                                        : 'opacity-40 hover:opacity-70'
                                "
                                @click="activeId = pp.id"
                            >
                                <Avatar
                                    :src="getPlatformAvatar(pp)"
                                    :name="getPlatformDisplayName(pp)"
                                    class="size-9 shrink-0 rounded-full border-2"
                                    :class="
                                        activeId === pp.id
                                            ? 'border-foreground shadow-2xs'
                                            : 'border-foreground/20'
                                    "
                                />
                                <span
                                    class="absolute -right-1 -bottom-1 inline-flex size-5 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs lg:size-4"
                                >
                                    <img
                                        :src="getPlatformLogo(pp.platform)"
                                        :alt="pp.platform"
                                        class="size-full object-cover"
                                    />
                                </span>
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <div class="space-y-0.5 text-xs">
                                <p class="font-semibold">
                                    {{ getPlatformDisplayName(pp)
                                    }}<span
                                        v-if="pp.social_account?.username"
                                        class="font-normal opacity-80"
                                        >&nbsp;·&nbsp;@{{
                                            pp.social_account.username
                                        }}</span
                                    >
                                </p>
                                <p class="opacity-70">
                                    {{ getPlatformLabel(pp.platform) }}
                                </p>
                                <p
                                    v-if="
                                        showDisabledBadge &&
                                        pp.enabled === false
                                    "
                                    class="font-semibold text-amber-500"
                                >
                                    {{ $t('posts.edit.preview_disabled') }}
                                </p>
                            </div>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>
        </div>

        <div
            class="flex flex-1 items-start justify-center overflow-x-hidden px-4 pt-6 pb-8"
        >
            <PhoneMockup v-if="activePlatform">
                <PlatformPreview
                    :platform="activePlatform.platform"
                    :content="activeContent"
                    :media="media"
                    :social-account="activeSocialAccount"
                    :content-type="activeContentType"
                    :meta="platformMeta?.[activePlatform.id] ?? {}"
                    :posted-at="postedAt"
                />
            </PhoneMockup>
            <div v-else class="flex flex-col items-center gap-3 text-center">
                <div
                    class="inline-flex size-12 -rotate-3 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-200 shadow-2xs"
                >
                    <IconDeviceMobile
                        class="size-6 text-foreground"
                        stroke-width="2"
                    />
                </div>
                <p
                    class="text-base font-bold text-foreground"
                    style="font-family: var(--font-display)"
                >
                    {{ $t('posts.edit.preview_empty.title') }}
                </p>
                <p class="text-xs font-medium text-foreground/60">
                    {{ $t('posts.edit.preview_empty.description') }}
                </p>
            </div>
        </div>
    </div>
</template>
