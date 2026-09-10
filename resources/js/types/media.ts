import type { MediaType } from '@/lib/mediaType';

export type MediaSource = 'ai' | 'unsplash' | 'giphy';

export type BrandReferenceKind =
    | 'face_closeup'
    | 'full_body'
    | 'logo'
    | 'product'
    | 'style'
    | 'other';

export type SourceMetaValue =
    | string
    | number
    | boolean
    | null
    | SourceMetaValue[];

export interface MediaItem {
    id: string;
    url: string;
    path?: string;
    type?: MediaType;
    mime_type?: string;
    original_filename?: string;
    size?: number;
    source?: MediaSource;
    source_meta?: Record<string, SourceMetaValue>;
    meta?: {
        width?: number;
        height?: number;
        duration?: number;
        alt_text?: string;
        label?: string;
        kind?: BrandReferenceKind;
    };
}
