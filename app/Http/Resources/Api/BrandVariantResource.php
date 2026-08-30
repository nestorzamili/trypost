<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'language_code' => $this->language_code,
            'label' => $this->label,
            'colors' => $this->colors ?? [],
            'brand_color' => $this->brand_color,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
            'headline_font' => $this->headline_font,
            'body_font' => $this->body_font,
            'label_font' => $this->label_font,
            'accent_font' => $this->accent_font,
            'visual_notes' => $this->visual_notes,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
