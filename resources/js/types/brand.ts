export interface BrandVariant {
    id: string;
    language_code: 'en' | 'zh';
    label: string;
    colors: Record<string, string> | null;
    brand_color: string | null;
    background_color: string | null;
    text_color: string | null;
    headline_font: string | null;
    body_font: string | null;
    label_font: string | null;
    accent_font: string | null;
    visual_notes: string | null;
    sort_order: number;
}

export interface BrandVariantLanguage {
    code: 'en' | 'zh';
    label: string;
    available: boolean;
}
