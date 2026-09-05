<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use App\Models\BrandVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The workspace brand identity for the chat tools, shared by get_brand and
 * update_brand so the two can never drift apart. Variants and photo
 * references are included because generation follows them on its own — the
 * model needs to see what it is working with, not re-apply it by hand.
 */
class ChatBrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $workspace = $this->resource;

        $variants = $workspace->brandVariants()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (BrandVariant $variant): array => self::variantData($variant))
            ->all();

        $references = $workspace->getMedia('brand_references')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn ($media): array => [
                'id' => $media->id,
                'original_filename' => $media->original_filename,
                'label' => data_get($media->meta, 'label'),
                'mime_type' => $media->mime_type,
                'url' => $media->url,
            ])
            ->all();

        return [
            'name' => $workspace->name,
            'brand_description' => $workspace->brand_description,
            'brand_voice_traits' => $workspace->brand_voice_traits ?? [],
            'brand_guidelines' => $workspace->brand_guidelines,
            'brand_color' => $workspace->brand_color,
            'background_color' => $workspace->background_color,
            'text_color' => $workspace->text_color,
            'brand_font' => $workspace->brand_font,
            'content_language' => $workspace->content_language,
            'variants' => $variants,
            'reference_photos' => $references,
        ];
    }

    /**
     * One language variant, as listed inside the brand payload and as
     * returned on its own by the variant write tools.
     *
     * @return array<string, mixed>
     */
    public static function variantData(BrandVariant $variant): array
    {
        return [
            'id' => $variant->id,
            'label' => $variant->label,
            'language_code' => $variant->language_code,
            'brand_color' => $variant->brand_color,
            'background_color' => $variant->background_color,
            'text_color' => $variant->text_color,
            'headline_font' => $variant->headline_font,
            'body_font' => $variant->body_font,
            'label_font' => $variant->label_font,
            'accent_font' => $variant->accent_font,
            'colors' => $variant->colors ?? [],
            'visual_notes' => $variant->visual_notes,
        ];
    }
}
