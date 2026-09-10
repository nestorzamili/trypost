<?php

declare(strict_types=1);

use App\Models\Workspace;
use App\Services\Image\BrandColorMapper;
use App\Support\ResolvedBrand;

test('maps primary colors to expected buckets', function (string $hex, string $expected) {
    expect((new BrandColorMapper)->fromHex($hex))->toBe($expected);
})->with([
    ['#ff0000', 'red'],
    ['#00ff00', 'green'],
    ['#0000ff', 'blue'],
    ['#ffff00', 'yellow'],
    ['#ff00ff', 'magenta'],
    ['#00ffff', 'teal'],
    ['#ff8000', 'orange'],
    ['#8000ff', 'purple'],
    ['#000000', 'black'],
    ['#ffffff', 'white'],
    ['#808080', 'black_and_white'],
]);

test('returns null on invalid hex', function () {
    $mapper = new BrandColorMapper;
    expect($mapper->fromHex('xyz'))->toBeNull();
    expect($mapper->fromHex(''))->toBeNull();
    expect($mapper->fromHex('#abc'))->toBeNull();
});

test('fromWorkspace uses brand_color when available', function () {
    $workspace = Workspace::factory()->make([
        'brand_color' => '#ff0000',
        'background_color' => '#0000ff',
    ]);

    $bucket = (new BrandColorMapper)->fromWorkspace($workspace);

    expect($bucket)->toBe('red');
});

test('fromWorkspace uses a resolved variant brand color', function () {
    $workspace = Workspace::factory()->make([
        'brand_color' => '#ff0000',
        'background_color' => '#0000ff',
    ]);
    $brand = new ResolvedBrand(
        languageCode: 'zh',
        variantId: 'variant-id',
        variantLabel: 'Chinese Content',
        hasVariant: true,
        brandDescription: '',
        brandVoiceTraits: [],
        brandGuidelines: '',
        brandColor: '#00ff00',
        backgroundColor: '#0000ff',
        textColor: '#000000',
        headlineFont: 'Inter',
        bodyFont: 'Inter',
        labelFont: 'Inter',
        accentFont: '',
    );

    expect((new BrandColorMapper)->fromWorkspace($workspace, $brand))->toBe('green');
});

test('fromWorkspace falls back to background_color when brand_color is null', function () {
    $workspace = Workspace::factory()->make([
        'brand_color' => null,
        'background_color' => '#0000ff',
    ]);

    $bucket = (new BrandColorMapper)->fromWorkspace($workspace);

    expect($bucket)->toBe('blue');
});

test('fromWorkspace returns null when both colors are null', function () {
    $workspace = Workspace::factory()->make([
        'brand_color' => null,
        'background_color' => null,
    ]);

    $bucket = (new BrandColorMapper)->fromWorkspace($workspace);

    expect($bucket)->toBeNull();
});
