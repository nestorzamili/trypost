<?php

declare(strict_types=1);

use App\Services\Ai\CreditCost;

test('forImage returns the configured credits for a known model', function () {
    config()->set('ai-credits.image', [
        'default' => 50,
        'seedream-4-5-251128' => 15,
    ]);

    expect(CreditCost::forImage('seedream-4-5-251128'))->toBe(15);
});

test('forImage falls back to the default for an unknown model', function () {
    config()->set('ai-credits.image', ['default' => 50]);

    expect(CreditCost::forImage('some-unlisted-model'))->toBe(50)
        ->and(CreditCost::forImage(null))->toBe(50);
});

test('the shipped config prices the active seedream image model', function () {
    // Guards against the model silently falling back to the generic default.
    expect(config('ai-credits.image.seedream-4-5-251128'))->not->toBeNull()
        ->and((int) config('ai-credits.image.seedream-4-5-251128'))->toBeGreaterThan(0);
});
