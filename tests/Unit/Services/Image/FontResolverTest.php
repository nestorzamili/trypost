<?php

declare(strict_types=1);

use App\Enums\Workspace\BrandFont;
use App\Services\Image\FontResolver;

beforeEach(function () {
    $this->resolver = new FontResolver;
});

it('resolves Inter font for default English language', function () {
    $headline = $this->resolver->headlineFont(null, 'en');
    $body = $this->resolver->bodyFont(null, 'en');
    $light = $this->resolver->lightFont(null, 'en');

    expect($headline)->toEndWith('Inter-Bold.ttf')
        ->and($body)->toEndWith('Inter-Medium.ttf')
        ->and($light)->toEndWith('Inter-Light.ttf');
});

it('resolves CJK font when language code is zh, ja, or ko', function (string $lang) {
    $headline = $this->resolver->headlineFont(null, $lang);
    $body = $this->resolver->bodyFont(null, $lang);

    expect($headline)->toEndWith('NotoSansCJK-Bold.ttc')
        ->and($body)->toEndWith('NotoSansCJK-Medium.ttc');
})->with(['zh', 'zh-TW', 'zh-CN', 'ja', 'ko']);

it('resolves CJK font when sample text contains CJK characters even if language is unspecified', function () {
    $font = $this->resolver->headlineFont(null, null, 'Sara 个人品牌介绍');

    expect($font)->toEndWith('NotoSansCJK-Bold.ttc');
});

it('resolves CJK font when preferred font is Noto Sans TC', function () {
    $font = $this->resolver->headlineFont(BrandFont::NotoSansTC->value, 'en');

    expect($font)->toEndWith('NotoSansCJK-Bold.ttc');
});

it('correctly detects CJK characters in text', function (string $text, bool $expected) {
    expect($this->resolver->containsCjk($text))->toBe($expected);
})->with([
    'English text' => ['Hello world', false],
    'Simplified Chinese' => ['个人品牌介绍', true],
    'Traditional Chinese' => ['個人品牌介紹', true],
    'Japanese Kanji and Kana' => ['こんにちは世界', true],
    'Korean Hangul' => ['안녕하세요', true],
    'Mixed English and Chinese' => ['Sara 品牌 AI', true],
]);
