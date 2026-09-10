<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\AiTimeouts;
use App\Models\Workspace;
use App\Support\ResolvedBrand;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

#[Temperature(0.7)]
#[Timeout(AiTimeouts::TEXT_SECONDS)]
class PostBriefRefiner implements Agent
{
    use Promptable;

    public function __construct(public Workspace $workspace, public ResolvedBrand $brand) {}

    public function instructions(): string
    {
        return view('prompts.post_content.brief_refiner', [
            'brand_name' => $this->workspace->name,
            'brand_description' => $this->brand->brandDescription,
            'brand_guidelines' => $this->brand->brandGuidelines,
            'brand_voice_traits' => $this->brand->brandVoiceTraits,
            'content_language' => $this->brand->languageCode,
        ])->render();
    }
}
