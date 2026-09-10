<?php

declare(strict_types=1);

namespace App\Ai\Templates;

use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Support\ResolvedBrand;

/**
 * Everything a template needs to assemble a post from the LLM output.
 */
class TemplateContext
{
    /**
     * Per-slide media (index => media-item) already rendered on a previous
     * attempt. The carousel assembler reuses these instead of re-rendering
     * (and re-billing) the slide. Empty on a first attempt.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $existingSlideMedia = [];

    /**
     * Per-slide media (index => media-item) produced by the most recent
     * assemble() call — written by the carousel assembler so the job can
     * persist it for an idempotent resume. Read-only to everyone else.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $renderedSlideMedia = [];

    public function __construct(
        public Workspace $workspace,
        public ?SocialAccount $socialAccount,
        public string $format,
        public int $imageCount,
        public bool $isCarousel = false,
        public bool $applyBrandVisuals = true,
        public ?string $languageCode = null,
        public ?ResolvedBrand $brand = null,
        public array $referenceImages = [],
        public array $referenceKinds = [],
    ) {}
}
