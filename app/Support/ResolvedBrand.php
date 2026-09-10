<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BrandVariant;
use App\Models\Workspace;

final readonly class ResolvedBrand
{
    /**
     * @param  array<int, string>  $brandVoiceTraits
     * @param  array<string, string>  $colors
     */
    public function __construct(
        public string $languageCode,
        public ?string $variantId,
        public ?string $variantLabel,
        public bool $hasVariant,
        public string $brandDescription,
        public array $brandVoiceTraits,
        public string $brandGuidelines,
        public string $brandColor,
        public string $backgroundColor,
        public string $textColor,
        public string $headlineFont,
        public string $bodyFont,
        public string $labelFont,
        public string $accentFont,
        public array $colors = [],
        public string $visualNotes = '',
    ) {}

    public static function fromWorkspace(Workspace $workspace, ?string $languageCode = null): self
    {
        $language = $languageCode ?: (string) ($workspace->content_language ?: 'en');
        $variant = self::findVariant($workspace, $language);
        $brandFont = (string) ($workspace->brand_font ?: 'Inter');
        $brandVoiceTraits = is_array($workspace->brand_voice_traits) ? $workspace->brand_voice_traits : [];

        if ($variant === null) {
            return new self(
                languageCode: $language,
                variantId: null,
                variantLabel: null,
                hasVariant: false,
                brandDescription: (string) ($workspace->brand_description ?? ''),
                brandVoiceTraits: $brandVoiceTraits,
                brandGuidelines: (string) ($workspace->brand_guidelines ?? ''),
                brandColor: (string) ($workspace->brand_color ?? ''),
                backgroundColor: (string) ($workspace->background_color ?? ''),
                textColor: (string) ($workspace->text_color ?? ''),
                headlineFont: $brandFont,
                bodyFont: $brandFont,
                labelFont: $brandFont,
                accentFont: '',
                colors: self::flatColors($workspace),
            );
        }

        return new self(
            languageCode: $language,
            variantId: (string) $variant->getKey(),
            variantLabel: $variant->label,
            hasVariant: true,
            brandDescription: (string) ($workspace->brand_description ?? ''),
            brandVoiceTraits: $brandVoiceTraits,
            brandGuidelines: (string) ($workspace->brand_guidelines ?? ''),
            brandColor: (string) ($variant->brand_color ?: ($workspace->brand_color ?? '')),
            backgroundColor: (string) ($variant->background_color ?: ($workspace->background_color ?? '')),
            textColor: (string) ($variant->text_color ?: ($workspace->text_color ?? '')),
            headlineFont: (string) ($variant->headline_font ?: $brandFont),
            bodyFont: (string) ($variant->body_font ?: $brandFont),
            labelFont: (string) ($variant->label_font ?: $brandFont),
            accentFont: (string) ($variant->accent_font ?? ''),
            colors: self::normalizeColors($variant->colors),
            visualNotes: (string) ($variant->visual_notes ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public static function fromSnapshot(array $snapshot): self
    {
        return new self(
            languageCode: (string) data_get($snapshot, 'language', 'en'),
            variantId: data_get($snapshot, 'variant_id'),
            variantLabel: data_get($snapshot, 'variant_label'),
            hasVariant: (bool) data_get($snapshot, 'has_variant', false),
            brandDescription: (string) data_get($snapshot, 'brand_description', ''),
            brandVoiceTraits: is_array(data_get($snapshot, 'brand_voice_traits')) ? data_get($snapshot, 'brand_voice_traits') : [],
            brandGuidelines: (string) data_get($snapshot, 'brand_guidelines', ''),
            brandColor: (string) data_get($snapshot, 'brand_color', ''),
            backgroundColor: (string) data_get($snapshot, 'background_color', ''),
            textColor: (string) data_get($snapshot, 'text_color', ''),
            headlineFont: (string) data_get($snapshot, 'headline_font', 'Inter'),
            bodyFont: (string) data_get($snapshot, 'body_font', 'Inter'),
            labelFont: (string) data_get($snapshot, 'label_font', 'Inter'),
            accentFont: (string) data_get($snapshot, 'accent_font', ''),
            colors: self::normalizeColors(data_get($snapshot, 'colors', [])),
            visualNotes: (string) data_get($snapshot, 'visual_notes', ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'language' => $this->languageCode,
            'variant_id' => $this->variantId,
            'variant_label' => $this->variantLabel,
            'has_variant' => $this->hasVariant,
            'brand_color' => $this->brandColor,
            'background_color' => $this->backgroundColor,
            'text_color' => $this->textColor,
            'headline_font' => $this->headlineFont,
            'body_font' => $this->bodyFont,
            'label_font' => $this->labelFont,
            'accent_font' => $this->accentFont,
            'colors' => $this->colors,
        ];
    }

    private static function findVariant(Workspace $workspace, string $languageCode): ?BrandVariant
    {
        if ($workspace->relationLoaded('brandVariants')) {
            return $workspace->brandVariants->first(
                fn (BrandVariant $variant): bool => $variant->language_code === $languageCode,
            );
        }

        if (! $workspace->exists) {
            return null;
        }

        return $workspace->brandVariants()->where('language_code', $languageCode)->first();
    }

    /**
     * @return array<string, string>
     */
    private static function flatColors(Workspace $workspace): array
    {
        return self::normalizeColors([
            'Brand Color' => $workspace->brand_color,
            'Background' => $workspace->background_color,
            'Text' => $workspace->text_color,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function normalizeColors(mixed $colors): array
    {
        if (! is_array($colors)) {
            return [];
        }

        $normalized = [];
        foreach ($colors as $name => $hex) {
            if (! is_string($name) || ! is_string($hex)) {
                continue;
            }

            $name = trim($name);
            $hex = trim($hex);
            if ($name === '' || ! preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex)) {
                continue;
            }

            $normalized[$name] = $hex;
        }

        return $normalized;
    }
}
