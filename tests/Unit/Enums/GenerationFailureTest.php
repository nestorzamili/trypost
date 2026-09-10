<?php

declare(strict_types=1);

use App\Enums\Ai\GenerationFailure;

test('each failure kind resolves to a non-empty, translated message', function (GenerationFailure $kind) {
    $message = $kind->message('en');

    expect($message)->toBeString()->not->toBe('')
        // Must resolve a real translation, not echo the dotted key back.
        ->and($message)->not->toContain('posts.ai.generate.errors');
})->with([
    GenerationFailure::Text,
    GenerationFailure::ImagePartial,
    GenerationFailure::ImageNone,
    GenerationFailure::Timeout,
    GenerationFailure::Unknown,
]);

test('image_partial interpolates the done and expected counts', function () {
    $message = GenerationFailure::ImagePartial->message('en', ['done' => 3, 'expected' => 5]);

    expect($message)->toContain('3')->toContain('5')
        ->and($message)->not->toContain(':done')
        ->and($message)->not->toContain(':expected');
});

test('fromThrowable classifies a cURL timeout as Timeout and never leaks the raw message', function () {
    $e = new RuntimeException('cURL error 28: Operation timed out after 60002 milliseconds for https://api.deepseek.com/v1/chat/completions');

    $kind = GenerationFailure::fromThrowable($e, GenerationFailure::Text);
    $message = $kind->message('en');

    expect($kind)->toBe(GenerationFailure::Timeout)
        // The user-facing message must not carry the raw provider detail.
        ->and($message)->not->toContain('cURL')
        ->and($message)->not->toContain('deepseek.com');
});

test('fromThrowable falls back to the given default for an unclassified error', function () {
    $e = new RuntimeException('some internal db error');

    expect(GenerationFailure::fromThrowable($e, GenerationFailure::Text))->toBe(GenerationFailure::Text)
        ->and(GenerationFailure::fromThrowable($e))->toBe(GenerationFailure::Unknown);
});

test('every failure kind resolves in every supported locale (no missing translation)', function () {
    $locales = array_map(
        fn (string $path): string => basename($path),
        glob(lang_path('*'), GLOB_ONLYDIR),
    );

    foreach ($locales as $locale) {
        foreach (GenerationFailure::cases() as $kind) {
            $message = $kind->message($locale, ['done' => 1, 'expected' => 2]);
            expect($message)
                ->not->toContain('posts.ai.generate.errors', "missing {$kind->value} in {$locale}");
        }
    }
});
