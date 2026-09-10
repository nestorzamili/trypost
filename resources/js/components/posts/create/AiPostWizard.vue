<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconArrowLeft, IconCheck, IconSparkles } from '@tabler/icons-vue';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { loading as loadingRoute } from '@/actions/App/Http/Controllers/App/PostCreateController';
import BrandReferencePicker from '@/components/posts/create/BrandReferencePicker.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    getPlatformLabel,
    getPlatformLogo,
} from '@/composables/usePlatformLogo';
import { credits as creditsRoute } from '@/routes/app/posts/ai';
import type { MediaItem } from '@/types/media';
import { uuid } from '@/utils/uuid';

interface CatalogFormat {
    value: string;
    platform: string;
    label: string;
    accounts: Array<{
        id: string;
        label: string;
        username: string | null;
        platform: string;
    }>;
}

interface CatalogStyle {
    key: string;
    name: string;
    description: string;
    preview: string;
    needs_account: boolean;
    supported_formats: string[];
    applies_brand_visuals: boolean;
}

interface Props {
    catalog: {
        formats: CatalogFormat[];
        styles: CatalogStyle[];
        applies_brand_visuals_default: boolean;
        content_language: string | null;
        languages: Array<{
            language_code: string;
            label: string;
            swatch?: string[];
        }>;
        brand_reference_count: number;
    };
    date?: string | null;
    brandReferences?: MediaItem[];
    canManageBrandReferences?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    date: null,
    brandReferences: () => [],
    canManageBrandReferences: false,
});

const emit = defineEmits<{ cancel: [] }>();

const PROMPT_MIN = 3;
const PROMPT_MAX = 2000;

const prompt = ref('');
const format = ref<string | null>(null);
const accountId = ref<string | null>(null);
const style = ref('image_card');
const imageCount = ref(1);
const languageCode = ref<string | null>(props.catalog.content_language);

const localReferences = ref<MediaItem[]>([...props.brandReferences]);
const selectedReferenceIds = ref<string[]>(
    props.brandReferences.map((reference) => reference.id),
);

const submitting = ref(false);
const failed = ref<string | null>(null);
const credits = ref<{
    allowed: boolean;
    remaining?: number;
    limit?: number;
    message?: string;
} | null>(null);
const checkingCredits = ref(false);

const STORAGE_KEY = 'trypost:wizard:state';

const saveState = () => {
    const state = {
        prompt: prompt.value,
        format: format.value,
        accountId: accountId.value,
        style: style.value,
        imageCount: imageCount.value,
        selectedReferenceIds: selectedReferenceIds.value,
        languageCode: languageCode.value,
    };
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
};

const restoreState = () => {
    const raw = sessionStorage.getItem(STORAGE_KEY);
    if (!raw) return;
    try {
        const state = JSON.parse(raw);
        if (state.prompt) prompt.value = state.prompt;
        if (state.format) format.value = state.format;
        if (state.accountId) accountId.value = state.accountId;
        if (state.style) style.value = state.style;
        if (typeof state.imageCount === 'number')
            imageCount.value = state.imageCount;
        if (Array.isArray(state.selectedReferenceIds)) {
            selectedReferenceIds.value = state.selectedReferenceIds.filter(
                (id: string) =>
                    localReferences.value.some((ref) => ref.id === id),
            );
        }
        if (state.languageCode) languageCode.value = state.languageCode;
    } catch {
        sessionStorage.removeItem(STORAGE_KEY);
    }
};

const clearState = () => sessionStorage.removeItem(STORAGE_KEY);

watch(
    [
        prompt,
        format,
        accountId,
        style,
        imageCount,
        selectedReferenceIds,
        languageCode,
    ],
    saveState,
    { deep: true },
);

restoreState();

/** Deduplicate formats while preserving all linked accounts. */
const formats = computed(() => {
    const byValue = new Map<string, CatalogFormat>();

    for (const entry of props.catalog.formats) {
        const existing = byValue.get(entry.value);

        if (existing) {
            existing.accounts.push(...entry.accounts);
            continue;
        }

        byValue.set(entry.value, {
            value: entry.value,
            platform: entry.platform,
            label: entry.label,
            accounts: [...entry.accounts],
        });
    }

    return [...byValue.values()];
});

const accountsForFormat = computed(
    () =>
        formats.value.find((entry) => entry.value === format.value)?.accounts ??
        [],
);

const languages = computed(() => props.catalog.languages);

const isCarousel = computed(() => format.value === 'instagram_carousel');

const selectFormat = (value: string): void => {
    format.value = value;

    if (value === 'instagram_carousel') {
        imageCount.value = 5;
    } else if (
        imageCount.value === 0 &&
        (value === 'instagram_story' || value === 'pinterest_pin')
    ) {
        imageCount.value = 1;
    }

    const accounts =
        formats.value.find((entry) => entry.value === value)?.accounts ?? [];
    accountId.value = accounts.length === 1 ? accounts[0].id : null;
};

const onReferenceAdded = (newItem: MediaItem) => {
    localReferences.value = [newItem, ...localReferences.value];
    if (!selectedReferenceIds.value.includes(newItem.id)) {
        selectedReferenceIds.value = [
            ...selectedReferenceIds.value,
            newItem.id,
        ];
    }
};

const promptLength = computed(() => [...prompt.value.trim()].length);

/**
 * A style is usable for the chosen format when it declares no format
 * restriction (empty supported_formats = universal) or explicitly lists the
 * selected format. Before a format is picked, every style is allowed.
 */
const styleSupportsFormat = (entry: CatalogStyle): boolean => {
    if (format.value === null) return true;
    if (!entry.supported_formats || entry.supported_formats.length === 0) {
        return true;
    }

    return entry.supported_formats.includes(format.value);
};

const selectedStyle = computed(
    () => props.catalog.styles.find((entry) => entry.key === style.value) ?? null,
);

const styleCompatible = computed(
    () => selectedStyle.value !== null && styleSupportsFormat(selectedStyle.value),
);

// When the chosen format no longer supports the selected style, fall back to
// the first compatible style so the user is never stuck on an invalid combo.
watch(format, () => {
    if (selectedStyle.value && styleSupportsFormat(selectedStyle.value)) {
        return;
    }

    const fallback = props.catalog.styles.find((entry) =>
        styleSupportsFormat(entry),
    );

    if (fallback) {
        style.value = fallback.key;
    }
});

const canContinue = computed(() => {
    return (
        format.value !== null &&
        accountId.value !== null &&
        style.value !== null &&
        styleCompatible.value &&
        promptLength.value >= PROMPT_MIN &&
        promptLength.value <= PROMPT_MAX
    );
});

/**
 * The first unmet requirement, surfaced under the disabled generate button so
 * the user knows why they cannot continue instead of facing a dead button.
 */
const blockingReason = computed<string | null>(() => {
    if (format.value === null) return trans('posts.wizard.need_format');
    if (accountId.value === null) return trans('posts.wizard.need_account');
    if (!styleCompatible.value) return trans('posts.wizard.need_compatible_style');
    if (promptLength.value < PROMPT_MIN) return trans('posts.wizard.need_prompt');
    if (promptLength.value > PROMPT_MAX) return trans('posts.wizard.prompt_too_long');

    return null;
});

const generate = (): void => {
    if (submitting.value || !canContinue.value) return;

    submitting.value = true;
    failed.value = null;

    const hasReferences =
        imageCount.value > 0 && selectedReferenceIds.value.length > 0;

    const creationId = uuid();
    const query: Record<string, string> = {
        images: String(imageCount.value),
        format: format.value ?? '',
        style: style.value ?? '',
        template: style.value ?? '',
        prompt: prompt.value.trim(),
        apply_brand_visuals: '1',
    };
    if (accountId.value) query.social_account_id = accountId.value;
    if (props.date) query.date = props.date;
    if (languageCode.value) query.language_code = languageCode.value;
    if (hasReferences) {
        query.use_brand_references = '1';
        query.reference_media_ids = selectedReferenceIds.value.join(',');
    }

    clearState();

    router.visit(loadingRoute({ creationId }, { query }).url, {
        onError: () => {
            toast.error(trans('posts.wizard.failed'));
            submitting.value = false;
        },
    });
};

const checkCredits = async (): Promise<void> => {
    if (checkingCredits.value) return;

    checkingCredits.value = true;

    try {
        const response = await fetch(creditsRoute.url(), {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            credits.value = { allowed: true };
            checkingCredits.value = false;
            return;
        }

        credits.value = (await response.json()) as {
            allowed: boolean;
            remaining?: number;
            limit?: number;
            message?: string;
        };
    } catch {
        credits.value = { allowed: true };
    }

    checkingCredits.value = false;
};

// Pre-flight credit check on mount.
void checkCredits();
</script>

<template>
    <div class="space-y-8">
        <!-- Header Back Action -->
        <div>
            <button
                type="button"
                class="group inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-foreground/70 transition-colors hover:text-foreground"
                @click="emit('cancel')"
            >
                <span
                    class="inline-flex size-7 items-center justify-center rounded-lg border-2 border-foreground bg-card shadow-2xs transition-transform group-hover:-translate-x-0.5"
                >
                    <IconArrowLeft
                        class="size-3.5 text-foreground"
                        stroke-width="2.5"
                    />
                </span>
                {{ $t('common.back') }}
            </button>
        </div>

        <!-- 1. Format Selection with Social Media Icons -->
        <div class="space-y-3">
            <Label class="text-sm font-bold">
                {{ $t('posts.wizard.format_label') }}
            </Label>
            <div class="grid gap-2.5 sm:grid-cols-2">
                <button
                    v-for="entry in formats"
                    :key="entry.value"
                    type="button"
                    class="group flex cursor-pointer items-center gap-3 rounded-xl border-2 border-foreground bg-card p-3.5 text-left text-sm shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-md"
                    :class="{
                        '!bg-violet-100 shadow-md ring-2 ring-foreground':
                            format === entry.value,
                    }"
                    @click="selectFormat(entry.value)"
                >
                    <span
                        class="inline-flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs"
                    >
                        <img
                            :src="getPlatformLogo(entry.platform)"
                            :alt="getPlatformLabel(entry.platform)"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                    </span>
                    <span class="flex-1 font-semibold text-foreground">
                        {{ entry.label }}
                    </span>
                    <IconCheck
                        v-if="format === entry.value"
                        class="size-4 shrink-0 text-foreground"
                        stroke-width="3"
                    />
                </button>
            </div>
        </div>

        <!-- 1b. Account Selection (when multiple accounts match format) -->
        <div v-if="accountsForFormat.length > 1" class="space-y-3">
            <Label class="text-sm font-bold">
                {{ $t('posts.wizard.account_label') }}
            </Label>
            <div class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="account in accountsForFormat"
                    :key="account.id"
                    type="button"
                    class="group flex cursor-pointer items-center gap-3 rounded-xl border-2 border-foreground bg-card p-3 text-left text-sm shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-md"
                    :class="{
                        '!bg-violet-100 shadow-md ring-2 ring-foreground':
                            accountId === account.id,
                    }"
                    @click="accountId = account.id"
                >
                    <span
                        class="inline-flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-foreground bg-card shadow-2xs"
                    >
                        <img
                            :src="getPlatformLogo(account.platform)"
                            :alt="account.platform"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-xs leading-tight font-bold text-foreground"
                        >
                            {{ account.label }}
                        </p>
                        <p
                            v-if="account.username"
                            class="truncate text-xs font-medium text-foreground/60"
                        >
                            @{{ account.username }}
                        </p>
                    </div>
                    <IconCheck
                        v-if="accountId === account.id"
                        class="size-4 shrink-0 text-foreground"
                        stroke-width="3"
                    />
                </button>
            </div>
        </div>

        <!-- 2. Visual Style (Image Cards Preview) -->
        <div class="space-y-3">
            <Label class="text-sm font-bold">
                {{ $t('posts.wizard.style_label') }}
            </Label>
            <div class="grid gap-3 sm:grid-cols-3">
                <button
                    v-for="entry in catalog.styles"
                    :key="entry.key"
                    type="button"
                    :disabled="!styleSupportsFormat(entry)"
                    class="group relative flex cursor-pointer flex-col overflow-hidden rounded-xl border-2 border-foreground bg-card text-left shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0 disabled:hover:shadow-2xs"
                    :class="{
                        '!bg-violet-100 shadow-md ring-2 ring-foreground':
                            style === entry.key,
                    }"
                    :title="
                        !styleSupportsFormat(entry)
                            ? $t('posts.wizard.style_unsupported_for_format')
                            : undefined
                    "
                    @click="styleSupportsFormat(entry) && (style = entry.key)"
                >
                    <div class="aspect-video w-full overflow-hidden bg-muted">
                        <img
                            :src="entry.preview"
                            :alt="entry.name"
                            class="size-full object-cover transition-transform group-hover:scale-102"
                            loading="lazy"
                        />
                    </div>
                    <div class="flex items-start gap-2 p-3">
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-bold text-foreground"
                            >
                                {{ entry.name }}
                            </p>
                            <p
                                v-if="entry.description"
                                class="mt-0.5 text-xs leading-snug text-foreground/60"
                            >
                                {{ entry.description }}
                            </p>
                        </div>
                        <IconCheck
                            v-if="style === entry.key"
                            class="mt-0.5 size-4 shrink-0 text-foreground"
                            stroke-width="3"
                        />
                    </div>
                </button>
            </div>
        </div>

        <!-- 3. Media / Image Count (Ergonomic Pill Buttons) -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <Label class="text-sm font-bold">
                    {{ $t('posts.wizard.images_label') }}
                </Label>
                <span class="text-xs font-semibold text-foreground/70">
                    {{
                        imageCount === 0
                            ? $t('posts.wizard.media_none')
                            : `${imageCount} ${$t('posts.wizard.media_images')}`
                    }}
                </span>
            </div>

            <!-- Carousel pills (2 to 10) -->
            <div v-if="isCarousel" class="flex flex-wrap gap-2">
                <Button
                    v-for="n in [2, 3, 4, 5, 6, 7, 8, 9, 10]"
                    :key="n"
                    type="button"
                    size="sm"
                    class="h-9 min-w-9 font-bold"
                    :variant="imageCount === n ? 'default' : 'outline'"
                    @click="imageCount = n"
                >
                    {{ n }}
                </Button>
            </div>

            <!-- Standard feed pills (None, 1 to 4) -->
            <div v-else class="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    size="sm"
                    class="h-9 font-semibold"
                    :variant="imageCount === 0 ? 'default' : 'outline'"
                    @click="imageCount = 0"
                >
                    {{ $t('posts.wizard.media_none') }}
                </Button>
                <Button
                    v-for="n in [1, 2, 3, 4]"
                    :key="n"
                    type="button"
                    size="sm"
                    class="h-9 min-w-9 font-bold"
                    :variant="imageCount === n ? 'default' : 'outline'"
                    @click="imageCount = n"
                >
                    {{ n }}
                </Button>
            </div>
        </div>

        <!-- 4. Language Variant Selection -->
        <div class="space-y-3">
            <div class="space-y-0.5">
                <Label class="text-sm font-bold">
                    {{ $t('posts.wizard.language_variant_label') }}
                </Label>
                <p class="text-xs text-foreground/60">
                    {{ $t('posts.wizard.language_variant_description') }}
                </p>
            </div>

            <!-- If multiple language variants exist -->
            <div v-if="languages.length > 1" class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="entry in languages"
                    :key="entry.language_code"
                    type="button"
                    class="flex cursor-pointer items-center justify-between rounded-xl border-2 border-foreground bg-card p-3 text-left text-sm shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-md"
                    :class="{
                        '!bg-violet-100 shadow-md ring-2 ring-foreground':
                            languageCode === entry.language_code,
                    }"
                    @click="languageCode = entry.language_code"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex size-6 items-center justify-center rounded-md border border-foreground/30 bg-muted text-[11px] font-bold text-foreground uppercase"
                        >
                            {{ entry.language_code }}
                        </span>
                        <span class="font-semibold text-foreground">
                            {{ entry.label }}
                        </span>
                        <span
                            v-if="entry.swatch && entry.swatch.length"
                            class="ml-1 flex items-center gap-0.5"
                            :title="$t('posts.wizard.language_variant_swatch_hint')"
                        >
                            <span
                                v-for="(color, i) in entry.swatch"
                                :key="i"
                                class="size-3 rounded-full border border-foreground/40"
                                :style="{ backgroundColor: color }"
                            />
                        </span>
                    </div>
                    <IconCheck
                        v-if="languageCode === entry.language_code"
                        class="size-4 shrink-0 text-foreground"
                        stroke-width="3"
                    />
                </button>
            </div>

            <!-- Single default variant badge -->
            <div
                v-else-if="languages.length === 1"
                class="flex items-center justify-between rounded-xl border-2 border-foreground/20 bg-card p-3.5"
            >
                <div class="flex items-center gap-2.5">
                    <span
                        class="inline-flex size-7 items-center justify-center rounded-lg border border-foreground/30 bg-muted text-xs font-bold text-foreground uppercase"
                    >
                        {{ languages[0].language_code }}
                    </span>
                    <div>
                        <p class="text-sm font-bold text-foreground">
                            {{ languages[0].label }}
                        </p>
                        <p class="text-xs text-foreground/60">
                            {{ $t('posts.wizard.language_variant_default') }}
                        </p>
                    </div>
                </div>
                <span
                    class="rounded-md border border-foreground/20 bg-muted/60 px-2 py-1 text-[11px] font-semibold text-foreground/70"
                >
                    {{ $t('posts.wizard.language_variant_default') }}
                </span>
            </div>
        </div>

        <!-- 5. Brand References (shown when image count > 0) -->
        <div v-if="imageCount > 0" class="space-y-3">
            <Label class="text-sm font-bold">
                {{ $t('posts.wizard.brand_references_title') }}
            </Label>
            <BrandReferencePicker
                v-model:selected-ids="selectedReferenceIds"
                :references="localReferences"
                :can-manage="props.canManageBrandReferences"
                @reference-added="onReferenceAdded"
            />
        </div>

        <!-- 6. Prompt Input -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <Label for="wizard-prompt" class="text-sm font-bold">
                    {{ $t('posts.wizard.prompt_label') }}
                </Label>
                <span
                    class="text-xs tabular-nums"
                    :class="
                        promptLength > PROMPT_MAX
                            ? 'font-bold text-destructive'
                            : 'text-muted-foreground'
                    "
                >
                    {{ promptLength }}/{{ PROMPT_MAX }}
                </span>
            </div>
            <Textarea
                id="wizard-prompt"
                v-model="prompt"
                rows="4"
                :placeholder="$t('posts.wizard.prompt_placeholder')"
                class="resize-none"
            />
        </div>

        <!-- Error & Detached Status Alerts -->
        <p v-if="failed" class="text-sm font-medium text-destructive">
            {{ failed }}
        </p>

        <p
            v-if="credits && !credits.allowed"
            class="text-sm font-medium text-destructive"
        >
            {{ credits.message ?? $t('posts.wizard.credits_exhausted') }}
        </p>

        <!-- Generation Actions -->
        <div class="space-y-2 pt-2">
            <div class="flex items-center justify-between gap-3">
                <Button
                    variant="ghost"
                    :disabled="submitting"
                    @click="emit('cancel')"
                >
                    {{ $t('common.back') }}
                </Button>

                <Button
                    size="lg"
                    class="gap-2 font-bold"
                    :disabled="
                        !canContinue ||
                        submitting ||
                        (credits !== null && !credits.allowed)
                    "
                    @click="generate"
                >
                    <IconSparkles class="size-4" />
                    {{
                        submitting
                            ? $t('posts.wizard.submitting')
                            : $t('posts.wizard.generate')
                    }}
                </Button>
            </div>

            <p
                v-if="blockingReason && !submitting"
                class="text-right text-xs font-medium text-foreground/60"
            >
                {{ blockingReason }}
            </p>
        </div>
    </div>
</template>
