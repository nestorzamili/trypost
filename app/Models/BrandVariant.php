<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BrandVariantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandVariant extends Model
{
    /** @use HasFactory<BrandVariantFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'language_code',
        'label',
        'colors',
        'brand_color',
        'background_color',
        'text_color',
        'headline_font',
        'body_font',
        'label_font',
        'accent_font',
        'visual_notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
