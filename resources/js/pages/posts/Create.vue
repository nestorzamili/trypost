<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { IconPencil, IconSparkles } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import PageHeader from '@/components/PageHeader.vue';
import AiPostWizard from '@/components/posts/create/AiPostWizard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { accounts } from '@/routes/app';
import { store as storePost } from '@/routes/app/posts';

interface Props {
    date?: string | null;
    catalog: {
        formats: Array<{
            value: string;
            platform: string;
            label: string;
            accounts: Array<{
                id: string;
                label: string;
                username: string | null;
            }>;
        }>;
        styles: Array<{
            key: string;
            name: string;
            description: string;
            preview: string;
            needs_account: boolean;
            supported_formats: string[];
            applies_brand_visuals: boolean;
        }>;
        applies_brand_visuals_default: boolean;
        content_language: string | null;
        languages: Array<{ language_code: string; label: string }>;
        brand_reference_count: number;
    };
    brandReferences?: Array<{ id: string; url: string; name: string }>;
    canManageBrandReferences?: boolean;
}

const props = defineProps<Props>();

const view = ref<'choice' | 'ai'>('choice');
const submitting = ref(false);

const startFromScratch = (): void => {
    if (submitting.value) return;

    submitting.value = true;

    router.post(storePost.url(), props.date ? { date: props.date } : {}, {
        onFinish: () => (submitting.value = false),
    });
};

const hasConnectedAccounts = computed(() => props.catalog.formats.length > 0);
</script>

<template>
    <Head :title="$t('posts.wizard.title')" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col p-4">
            <div class="mx-auto flex w-full max-w-2xl flex-col gap-6">
                <PageHeader
                    :title="$t('posts.wizard.title')"
                    :description="$t('posts.wizard.description')"
                />

                <template v-if="view === 'choice'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <button
                            type="button"
                            class="group flex flex-col items-start gap-4 rounded-2xl border-2 border-foreground bg-card p-5 text-left shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="submitting"
                            @click="startFromScratch"
                        >
                            <div
                                class="inline-flex size-12 -rotate-2 items-center justify-center rounded-2xl border-2 border-foreground bg-violet-200 shadow-2xs transition-transform group-hover:rotate-0"
                            >
                                <IconPencil
                                    class="size-6 text-foreground"
                                    stroke-width="2"
                                />
                            </div>
                            <div class="space-y-1">
                                <p class="text-base font-bold text-foreground">
                                    {{ $t('posts.wizard.scratch_title') }}
                                </p>
                                <p
                                    class="text-xs leading-relaxed text-foreground/70"
                                >
                                    {{ $t('posts.wizard.scratch_description') }}
                                </p>
                            </div>
                        </button>

                        <button
                            type="button"
                            class="group flex flex-col items-start gap-4 rounded-2xl border-2 border-foreground bg-card p-5 text-left shadow-2xs transition-all hover:-translate-y-0.5 hover:shadow-md disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!hasConnectedAccounts"
                            @click="view = 'ai'"
                        >
                            <div
                                class="inline-flex size-12 rotate-1 items-center justify-center rounded-2xl border-2 border-foreground bg-amber-200 shadow-2xs transition-transform group-hover:rotate-0"
                            >
                                <IconSparkles
                                    class="size-6 text-foreground"
                                    stroke-width="2"
                                />
                            </div>
                            <div class="space-y-1">
                                <p class="text-base font-bold text-foreground">
                                    {{ $t('posts.wizard.ai_title') }}
                                </p>
                                <p
                                    class="text-xs leading-relaxed text-foreground/70"
                                >
                                    {{ $t('posts.wizard.ai_description') }}
                                </p>
                            </div>
                        </button>
                    </div>

                    <div
                        v-if="!hasConnectedAccounts"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span class="text-muted-foreground">
                            {{ $t('posts.wizard.connect_first') }}
                        </span>
                        <Link
                            :href="accounts.url()"
                            class="font-semibold underline underline-offset-4"
                        >
                            {{ $t('posts.wizard.connect_cta') }}
                        </Link>
                    </div>
                </template>

                <AiPostWizard
                    v-else
                    :catalog="catalog"
                    :date="props.date"
                    :brand-references="props.brandReferences"
                    :can-manage-brand-references="
                        props.canManageBrandReferences
                    "
                    @cancel="view = 'choice'"
                />
            </div>
        </div>
    </AppLayout>
</template>
