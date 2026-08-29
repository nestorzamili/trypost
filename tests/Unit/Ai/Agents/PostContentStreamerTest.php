<?php

declare(strict_types=1);

use App\Ai\Agents\PostContentStreamer;
use App\Models\Workspace;

it('uses the active language variant in streamed content instructions', function () {
    $workspace = Workspace::factory()->make([
        'content_language' => 'zh',
        'brand_guidelines' => 'Human first.',
    ]);

    $brand = new \App\Support\ResolvedBrand(
        languageCode: 'zh',
        variantId: 'variant-id',
        variantLabel: 'Chinese Content',
        hasVariant: true,
        brandDescription: '',
        brandVoiceTraits: [],
        brandGuidelines: 'Human first.',
        brandColor: '#D6A928',
        backgroundColor: '#F7F3EA',
        textColor: '#292723',
        headlineFont: 'Noto Serif TC',
        bodyFont: 'Noto Sans TC',
        labelFont: 'Inter',
        accentFont: '',
        colors: ['Warm Ivory' => '#F7F3EA'],
        visualNotes: 'Traditional Chinese editorial direction.',
    );

    $instructions = (new PostContentStreamer($workspace, brand: $brand))->instructions();

    expect($instructions)
        ->toContain('language with code: zh')
        ->toContain('Traditional Chinese editorial direction.')
        ->toContain('Human first.');
});
