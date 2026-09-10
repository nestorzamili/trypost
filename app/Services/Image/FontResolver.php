<?php

declare(strict_types=1);

namespace App\Services\Image;

use App\Enums\Workspace\BrandFont;
use App\Enums\Workspace\ContentLanguage;

class FontResolver
{
    public const WEIGHT_BOLD = 'bold';

    public const WEIGHT_MEDIUM = 'medium';

    public const WEIGHT_REGULAR = 'regular';

    public const WEIGHT_LIGHT = 'light';

    public const WEIGHT_SEMIBOLD = 'semibold';

    /**
     * Resolve the best-matching font path for a headline (bold).
     */
    public function headlineFont(?string $preferredFont = null, ?string $languageCode = null, ?string $sampleText = null): ?string
    {
        return $this->resolve($preferredFont, $languageCode, self::WEIGHT_BOLD, $sampleText);
    }

    /**
     * Resolve the best-matching font path for body text (medium).
     */
    public function bodyFont(?string $preferredFont = null, ?string $languageCode = null, ?string $sampleText = null): ?string
    {
        return $this->resolve($preferredFont, $languageCode, self::WEIGHT_MEDIUM, $sampleText);
    }

    /**
     * Resolve the best-matching font path for footer / secondary text (light).
     */
    public function lightFont(?string $preferredFont = null, ?string $languageCode = null, ?string $sampleText = null): ?string
    {
        return $this->resolve($preferredFont, $languageCode, self::WEIGHT_LIGHT, $sampleText);
    }

    /**
     * Resolve a font path for the specified weight, brand font preference, and language.
     */
    public function resolve(?string $preferredFont = null, ?string $languageCode = null, string $weight = self::WEIGHT_MEDIUM, ?string $sampleText = null): ?string
    {
        $needsCjk = $this->isCjkLanguage($languageCode)
            || $this->isCjkFont($preferredFont)
            || ($sampleText !== null && $this->containsCjk($sampleText));

        if ($needsCjk) {
            $cjkPath = $this->resolveCjkFontPath($weight);
            if ($cjkPath !== null) {
                return $cjkPath;
            }
        }

        if ($preferredFont !== null && trim($preferredFont) !== '') {
            $customPath = $this->resolveCustomFontPath($preferredFont, $weight);
            if ($customPath !== null) {
                return $customPath;
            }
        }

        return $this->resolveInterFontPath($weight);
    }

    /**
     * Check if a language code belongs to CJK (Chinese, Japanese, Korean).
     */
    public function isCjkLanguage(?string $languageCode): bool
    {
        if ($languageCode === null) {
            return false;
        }

        $code = strtolower(trim($languageCode));

        return in_array($code, [
            ContentLanguage::Chinese->value,
            ContentLanguage::Japanese->value,
            ContentLanguage::Korean->value,
            'zh',
            'zh-cn',
            'zh-tw',
            'zh-hk',
            'ja',
            'ko',
        ], true);
    }

    /**
     * Check if a string contains any CJK Unicode characters.
     */
    public function containsCjk(string $text): bool
    {
        return (bool) preg_match('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}\x{20000}-\x{2A6DF}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{AC00}-\x{D7AF}\x{3000}-\x{303F}\x{FF00}-\x{FFEF}]/u', $text);
    }

    /**
     * Check if a brand font enum or name is explicitly a CJK font.
     */
    private function isCjkFont(?string $fontName): bool
    {
        if ($fontName === null || trim($fontName) === '') {
            return false;
        }

        return in_array($fontName, [
            BrandFont::NotoSansTC->value,
            BrandFont::NotoSerifTC->value,
            BrandFont::SourceHanSerifTC->value,
            'Noto Sans TC',
            'Noto Serif TC',
            'Source Han Serif TC',
            'Noto Sans SC',
            'Noto Serif SC',
            'Noto Sans CJK',
        ], true);
    }

    private function resolveCjkFontPath(string $weight): ?string
    {
        $filename = match ($weight) {
            self::WEIGHT_BOLD, self::WEIGHT_SEMIBOLD => 'NotoSansCJK-Bold.ttc',
            self::WEIGHT_LIGHT => 'NotoSansCJK-Light.ttc',
            self::WEIGHT_REGULAR => 'NotoSansCJK-Regular.ttc',
            default => 'NotoSansCJK-Medium.ttc',
        };

        $localPath = base_path('resources/fonts/'.$filename);
        if (file_exists($localPath)) {
            return $localPath;
        }

        $systemPath = '/usr/share/fonts/noto-cjk/'.$filename;
        if (file_exists($systemPath)) {
            return $systemPath;
        }

        return null;
    }

    private function resolveCustomFontPath(string $fontName, string $weight): ?string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '', $fontName));
        $weightSuffix = match ($weight) {
            self::WEIGHT_BOLD => '-Bold',
            self::WEIGHT_SEMIBOLD => '-SemiBold',
            self::WEIGHT_LIGHT => '-Light',
            self::WEIGHT_REGULAR => '-Regular',
            default => '-Medium',
        };

        foreach (['.ttf', '.otf', '.ttc'] as $ext) {
            $candidate = base_path("resources/fonts/{$sanitized}{$weightSuffix}{$ext}");
            if (file_exists($candidate)) {
                return $candidate;
            }

            $candidateBase = base_path("resources/fonts/{$sanitized}{$ext}");
            if (file_exists($candidateBase)) {
                return $candidateBase;
            }
        }

        return null;
    }

    private function resolveInterFontPath(string $weight): ?string
    {
        $filename = match ($weight) {
            self::WEIGHT_BOLD => 'Inter-Bold.ttf',
            self::WEIGHT_SEMIBOLD => 'Inter-SemiBold.ttf',
            self::WEIGHT_LIGHT => 'Inter-Light.ttf',
            self::WEIGHT_REGULAR => 'Inter-Regular.ttf',
            default => 'Inter-Medium.ttf',
        };

        $path = base_path('resources/fonts/'.$filename);

        return file_exists($path) ? $path : null;
    }
}
