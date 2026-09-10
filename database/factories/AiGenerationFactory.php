<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Ai\GenerationStatus;
use App\Models\AiGeneration;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiGeneration>
 */
class AiGenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'creation_id' => (string) Str::uuid(),
            'status' => GenerationStatus::PendingText,
            'format' => 'instagram_feed',
            'template' => 'image_card',
            'image_expected' => 0,
            'image_done' => 0,
        ];
    }
}
