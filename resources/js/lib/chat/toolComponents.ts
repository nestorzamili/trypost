import type { Component } from 'vue';

import ChatAssetCard from '@/components/chat/tools/ChatAssetCard.vue';
import ChatAssetList from '@/components/chat/tools/ChatAssetList.vue';
import ChatBrandCard from '@/components/chat/tools/ChatBrandCard.vue';
import ChatBrandReferenceCard from '@/components/chat/tools/ChatBrandReferenceCard.vue';
import ChatBrandVariantCard from '@/components/chat/tools/ChatBrandVariantCard.vue';
import ChatLabelCard from '@/components/chat/tools/ChatLabelCard.vue';
import ChatLabelList from '@/components/chat/tools/ChatLabelList.vue';
import ChatPostCard from '@/components/chat/tools/ChatPostCard.vue';
import ChatPostGenerationCard from '@/components/chat/tools/ChatPostGenerationCard.vue';
import ChatPostGenerationResult from '@/components/chat/tools/ChatPostGenerationResult.vue';
import ChatPostList from '@/components/chat/tools/ChatPostList.vue';
import ChatPostMetrics from '@/components/chat/tools/ChatPostMetrics.vue';
import ChatSignatureCard from '@/components/chat/tools/ChatSignatureCard.vue';
import ChatSignatureList from '@/components/chat/tools/ChatSignatureList.vue';

export type ToolComponentKind = 'display' | 'prompt';

export type ToolComponentEntry = {
    component: Component;
    kind: ToolComponentKind;
};

/**
 * Maps a tool name to the component that renders its output. `display`
 * components only render; `prompt` components also emit `submit`, which the
 * page turns into a new user message. `start_post_generation` is the first
 * `prompt` entry: its card collects the generation's choices client-side and
 * submits them as one readable sentence, so filling in a deterministic form
 * costs a single model turn instead of one per choice.
 *
 * `generate_post` is a plain `display` entry even though its card is the only
 * one that keeps working after it renders: the generation runs in the
 * background, so the card waits on a broadcast rather than asking the user
 * for anything.
 */
export const toolComponents: Record<string, ToolComponentEntry> = {
    list_posts: { component: ChatPostList, kind: 'display' },
    get_post: { component: ChatPostCard, kind: 'display' },
    get_post_metrics: { component: ChatPostMetrics, kind: 'display' },
    create_post: { component: ChatPostCard, kind: 'display' },
    update_post: { component: ChatPostCard, kind: 'display' },
    schedule_post: { component: ChatPostCard, kind: 'display' },
    publish_post: { component: ChatPostCard, kind: 'display' },
    delete_post: { component: ChatPostCard, kind: 'display' },
    attach_existing_asset: { component: ChatPostCard, kind: 'display' },
    start_post_generation: {
        component: ChatPostGenerationCard,
        kind: 'prompt',
    },
    generate_post: { component: ChatPostGenerationResult, kind: 'display' },
    get_brand: { component: ChatBrandCard, kind: 'display' },
    list_labels: { component: ChatLabelList, kind: 'display' },
    list_signatures: { component: ChatSignatureList, kind: 'display' },
    list_assets: { component: ChatAssetList, kind: 'display' },
    get_asset: { component: ChatAssetCard, kind: 'display' },
    create_label: { component: ChatLabelCard, kind: 'display' },
    update_label: { component: ChatLabelCard, kind: 'display' },
    delete_label: { component: ChatLabelCard, kind: 'display' },
    create_signature: { component: ChatSignatureCard, kind: 'display' },
    update_signature: { component: ChatSignatureCard, kind: 'display' },
    delete_signature: { component: ChatSignatureCard, kind: 'display' },
    update_brand: { component: ChatBrandCard, kind: 'display' },
    create_brand_variant: { component: ChatBrandVariantCard, kind: 'display' },
    update_brand_variant: { component: ChatBrandVariantCard, kind: 'display' },
    delete_brand_variant: { component: ChatBrandVariantCard, kind: 'display' },
    delete_brand_reference_photo: {
        component: ChatBrandReferenceCard,
        kind: 'display',
    },
    add_brand_reference_from_url: {
        component: ChatBrandReferenceCard,
        kind: 'display',
    },
    delete_asset: { component: ChatAssetCard, kind: 'display' },
    add_asset_from_url: { component: ChatAssetCard, kind: 'display' },
};

export const resolveToolComponent = (
    toolName: string,
): ToolComponentEntry | null => toolComponents[toolName] ?? null;
