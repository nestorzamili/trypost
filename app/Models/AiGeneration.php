<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Ai\GenerationStatus;
use Database\Factories\AiGenerationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneration extends Model
{
    /** @use HasFactory<AiGenerationFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'creation_id',
        'status',
        'format',
        'template',
        'apply_brand_visuals',
        'reference_media_ids',
        'use_brand_references',
        'language_code',
        'social_account_id',
        'image_expected',
        'image_done',
        'post_id',
        'structured',
        'slide_media',
        'error_phase',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'apply_brand_visuals' => 'boolean',
            'reference_media_ids' => 'array',
            'use_brand_references' => 'boolean',
            'image_expected' => 'integer',
            'image_done' => 'integer',
            'structured' => 'array',
            'slide_media' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
