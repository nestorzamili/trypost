<?php

declare(strict_types=1);

use App\Models\BrandVariant;
use App\Models\Workspace;
use App\Support\ResolvedBrand;

it('resolves a matching language variant with explicit roles and palette', function () {
    $workspace = Workspace::factory()->create([
        'content_language' => 'en',
        'brand_color' => '#0000ff',
        'background_color' => '#ffffff',
        'text_color' => '#000000',
        'brand_font' => 'Inter',
    ]);

    $variant = BrandVariant::factory()->english()->create([
        'workspace_id' => $workspace->id,
        'brand_color' => '#D9A62E',
        'background_color' => '#F8F5ED',
        'text_color' => '#292824',
        'headline_font' => 'Playfair Display',
        'body_font' => 'Noto Sans',
        'label_font' => 'Inter',
        'accent_font' => 'Caveat',
        'colors' => [
            'Warm Ivory' => '#F8F5ED',
            'Soft Cream' => '#EFE8DA',
            'Oat Beige' => '#DCCFBD',
            'Soft Mustard' => '#D9A62E',
            'Warm Yellow' => '#E8B94A',
            'Muted Terracotta' => '#B98262',
            'Warm Taupe' => '#9B8975',
            'Stone Grey' => '#716C63',
            'Soft Charcoal' => '#292824',
            'Warm White' => '#FFFDF8',
        ],
    ]);

    $brand = $workspace->resolvedBrand('en');

    expect($brand)
        ->toBeInstanceOf(ResolvedBrand::class)
        ->and($brand->variantId)->toBe($variant->id)
        ->and($brand->hasVariant)->toBeTrue()
        ->and($brand->brandColor)->toBe('#D9A62E')
        ->and($brand->backgroundColor)->toBe('#F8F5ED')
        ->and($brand->textColor)->toBe('#292824')
        ->and($brand->headlineFont)->toBe('Playfair Display')
        ->and($brand->colors)->toHaveCount(10);
});

it('falls back to flat values for an unsupported active language', function () {
    $workspace = Workspace::factory()->create([
        'content_language' => 'uk',
        'brand_color' => '#0000ff',
        'background_color' => '#ffffff',
        'text_color' => '#000000',
        'brand_font' => 'Lora',
    ]);

    BrandVariant::factory()->english()->create(['workspace_id' => $workspace->id]);
    BrandVariant::factory()->chinese()->create(['workspace_id' => $workspace->id]);

    $brand = $workspace->resolvedBrand();

    expect($brand->hasVariant)->toBeFalse()
        ->and($brand->languageCode)->toBe('uk')
        ->and($brand->brandColor)->toBe('#0000ff')
        ->and($brand->headlineFont)->toBe('Lora')
        ->and($brand->colors)->toHaveKey('Brand Color');
});

it('falls back per field when a variant value is missing', function () {
    $workspace = Workspace::factory()->create([
        'content_language' => 'en',
        'brand_color' => '#0000ff',
        'background_color' => '#ffffff',
        'text_color' => '#000000',
        'brand_font' => 'Lora',
    ]);

    BrandVariant::factory()->english()->create([
        'workspace_id' => $workspace->id,
        'brand_color' => null,
        'background_color' => '#f8f5ed',
        'text_color' => null,
        'headline_font' => null,
        'label_font' => null,
        'colors' => ['Warm Ivory' => '#F8F5ED'],
    ]);

    $brand = $workspace->resolvedBrand('en');

    expect($brand->brandColor)->toBe('#0000ff')
        ->and($brand->backgroundColor)->toBe('#f8f5ed')
        ->and($brand->textColor)->toBe('#000000')
        ->and($brand->headlineFont)->toBe('Lora')
        ->and($brand->labelFont)->toBe('Lora');
});

it('does not infer role colors from palette labels', function () {
    $workspace = Workspace::factory()->create([
        'content_language' => 'en',
        'brand_color' => '#0000ff',
        'background_color' => '#ffffff',
        'text_color' => '#000000',
    ]);

    BrandVariant::factory()->english()->create([
        'workspace_id' => $workspace->id,
        'brand_color' => null,
        'background_color' => null,
        'text_color' => null,
        'colors' => [
            'Soft Mustard' => '#D9A62E',
            'Warm Ivory' => '#F8F5ED',
            'Soft Charcoal' => '#292824',
        ],
    ]);

    $brand = $workspace->resolvedBrand('en');

    expect($brand->brandColor)->toBe('#0000ff')
        ->and($brand->backgroundColor)->toBe('#ffffff')
        ->and($brand->textColor)->toBe('#000000');
});

it('round trips a compact snapshot without storing guidelines', function () {
    $workspace = Workspace::factory()->create([
        'content_language' => 'zh',
        'brand_guidelines' => 'Private workspace guidance.',
    ]);
    BrandVariant::factory()->chinese()->create(['workspace_id' => $workspace->id]);

    $brand = $workspace->resolvedBrand();
    $snapshot = $brand->toSnapshot();
    $restored = ResolvedBrand::fromSnapshot($snapshot);

    expect($snapshot)->not->toHaveKey('brand_guidelines')
        ->and($restored->variantId)->toBe($brand->variantId)
        ->and($restored->languageCode)->toBe('zh')
        ->and($restored->colors)->toEqual($brand->colors)
        ->and($restored->headlineFont)->toBe('Noto Serif TC');
});
