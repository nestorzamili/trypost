<?php

declare(strict_types=1);

namespace App\Ai\Tools\Brand;

use App\Ai\Tools\WorkspaceTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetBrandTool extends WorkspaceTool
{
    public function name(): string
    {
        return 'get_brand';
    }

    public function description(): Stringable|string
    {
        return 'Get the current workspace brand identity: name, description, voice traits, guidelines, colors and fonts, plus its language variants and photo references. Generation already follows this brand (variant plus photo references) on its own.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request): string
    {
        $workspace = $this->workspace;

        $variants = $workspace->brandVariants()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($variant): array => [
                'id' => $variant->id,
                'label' => $variant->label,
                'language_code' => $variant->language_code,
                'brand_color' => $variant->brand_color,
                'background_color' => $variant->background_color,
                'text_color' => $variant->text_color,
                'headline_font' => $variant->headline_font,
                'body_font' => $variant->body_font,
                'colors' => $variant->colors ?? [],
                'visual_notes' => $variant->visual_notes,
            ])
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

        return $this->json(['data' => [
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
        ]]);
    }
}
