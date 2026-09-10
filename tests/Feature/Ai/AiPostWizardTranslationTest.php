<?php

declare(strict_types=1);

$langBase = dirname(__DIR__, 3).'/lang';

$locales = array_values(array_filter(
    array_map('basename', glob($langBase.'/*') ?: []),
    fn (string $locale): bool => is_dir("{$langBase}/{$locale}") && file_exists("{$langBase}/{$locale}/posts.php"),
));

dataset('wizard_locales', $locales);

it('defines all required wizard translation keys inside the wizard array', function (string $locale): void {
    $file = dirname(__DIR__, 3)."/lang/{$locale}/posts.php";
    expect(file_exists($file))->toBeTrue();

    $posts = require $file;
    expect($posts)->toBeArray()
        ->and($posts)->toHaveKey('wizard');

    $wizard = $posts['wizard'];
    expect($wizard)->toBeArray();

    $requiredKeys = [
        'prompt_label',
        'prompt_placeholder',
        'format_label',
        'account_label',
        'style_label',
        'images_label',
        'brand_colors_label',
        'brand_references_label',
        'language_label',
        'generate',
        'failed',
        'detached',
        'connect_first',
        'connect_cta',
        'generating_text',
        'text_ready',
        'submitting',
        'check_status',
        'status_check_failed',
        'credits_exhausted',
        'brand_references_select_all',
        'brand_references_clear',
        'language_variant_label',
        'language_variant_description',
        'language_variant_default',
        'brand_references_title',
        'brand_references_description',
        'brand_references_attach',
        'brand_references_uploading',
        'brand_references_empty',
        'media_none',
        'media_images',
    ];

    foreach ($requiredKeys as $key) {
        expect($wizard)->toHaveKey($key);
        expect($wizard[$key])->toBeString();
        expect(trim($wizard[$key]))->not->toBeEmpty();
    }
})->with('wizard_locales');

it('does not leak wizard keys to the root posts array', function (string $locale): void {
    $file = dirname(__DIR__, 3)."/lang/{$locale}/posts.php";
    $posts = require $file;

    $disallowedRootKeys = [
        'prompt_label',
        'prompt_placeholder',
        'format_label',
        'account_label',
        'style_label',
        'images_label',
        'brand_colors_label',
        'brand_references_label',
        'language_label',
        'generate',
        'failed',
        'detached',
        'brand_references_select_all',
        'brand_references_clear',
    ];

    foreach ($disallowedRootKeys as $key) {
        expect($posts)->not->toHaveKey($key);
    }
})->with('wizard_locales');

it('defines all required loading translation keys inside create.steps', function (string $locale): void {
    $file = dirname(__DIR__, 3)."/lang/{$locale}/posts.php";
    $posts = require $file;

    expect($posts)->toHaveKey('create');
    expect($posts['create'])->toHaveKey('steps');

    $steps = $posts['create']['steps'];
    $loadingKeys = [
        'loading_page_title',
        'loading_eta',
        'loading_eta_minute_one',
        'loading_eta_minute_other',
        'loading_leave_title',
        'loading_leave_body',
        'loading_leave_cta',
        'loading_create_another_cta',
        'loading_tip_credits',
        'loading_tip_edit',
        'loading_tip_draft',
        'loading_tip_brand',
        'loading_tip_carousel',
        'loading_tip_quality',
        'preview_error',
    ];

    foreach ($loadingKeys as $key) {
        expect($steps)->toHaveKey($key);
        expect($steps[$key])->toBeString();
        expect(trim($steps[$key]))->not->toBeEmpty();
    }
})->with('wizard_locales');
