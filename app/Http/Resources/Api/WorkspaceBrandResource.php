<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceBrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'workspace_id' => $this->id,
            'workspace_name' => $this->name,
            'brand_website' => $this->brand_website,
            'brand_description' => $this->brand_description,
            'brand_guidelines' => $this->brand_guidelines,
            'brand_voice_traits' => $this->brand_voice_traits ?? [],
            'brand_color' => $this->brand_color,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'brand_font' => $this->brand_font ?? 'Inter',
            'image_style' => $this->image_style instanceof \BackedEnum ? $this->image_style->value : ($this->image_style ?? 'cinematic'),
            'content_language' => $this->content_language ?? 'en',
            'has_logo' => $this->has_logo,
            'logo_url' => $this->logo_url,
            'variants' => BrandVariantResource::collection(
                $this->relationLoaded('brandVariants') ? $this->brandVariants : $this->brandVariants()->get()
            )->resolve(),
        ];
    }
}
