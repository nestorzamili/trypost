<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BrandVariant;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandVariant>
 */
class BrandVariantFactory extends Factory
{
    protected $model = BrandVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'language_code' => 'en',
            'label' => 'English Content',
            'colors' => [],
            'brand_color' => null,
            'background_color' => null,
            'text_color' => null,
            'headline_font' => 'Inter',
            'body_font' => 'Inter',
            'label_font' => 'Inter',
            'accent_font' => null,
            'visual_notes' => null,
            'sort_order' => 0,
        ];
    }

    public function english(): static
    {
        return $this->state(fn (): array => [
            'language_code' => 'en',
            'label' => 'English Content',
        ]);
    }

    public function chinese(): static
    {
        return $this->state(fn (): array => [
            'language_code' => 'zh',
            'label' => 'Chinese Content',
            'headline_font' => 'Noto Serif TC',
            'body_font' => 'Noto Sans TC',
            'label_font' => 'Inter',
        ]);
    }
}
