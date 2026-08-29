<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Enums\Ai\GeneratorFormat;
use App\Models\Workspace;
use App\Support\ResolvedBrand;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Streaming-compatible agent for inline post content generation in the editor.
 *
 * This is a separate agent from PostContentGenerator because the Laravel AI SDK
 * does not support streaming with HasStructuredOutput. Use this agent for
 * broadcast()/stream() calls; use PostContentGenerator for prompt() with
 * structured output in the AI wizard pipeline.
 */
#[Temperature(0.7)]
class PostContentStreamer implements Agent
{
    use Promptable;

    public function __construct(
        public Workspace $workspace,
        public ?string $currentContent = null,
        public bool $applyBrandVoice = true,
        public ?ResolvedBrand $brand = null,
    ) {}

    public function instructions(): string
    {
        $brand = $this->brand ?? $this->workspace->resolvedBrand();

        return view('prompts.post_content.generator', [
            'brand_name' => $this->workspace->name ?? '',
            'brand_description' => $this->applyBrandVoice ? $brand->brandDescription : '',
            'brand_guidelines' => $this->applyBrandVoice ? $brand->brandGuidelines : '',
            'brand_voice_traits' => $this->applyBrandVoice ? $brand->brandVoiceTraits : [],
            'visual_notes' => $brand->visualNotes,
            'brand_typography' => array_filter([
                'headline' => $brand->headlineFont,
                'body' => $brand->bodyFont,
                'label' => $brand->labelFont,
                'accent' => $brand->accentFont,
            ]),
            'include_description' => $this->applyBrandVoice,
            'include_voice' => $this->applyBrandVoice,
            'include_visuals' => $brand->hasVariant,
            'content_language' => $brand->languageCode,
            'current_content' => $this->currentContent,
            'format' => GeneratorFormat::Single->value,
            'slide_count' => 1,
        ])->render();
    }
}
