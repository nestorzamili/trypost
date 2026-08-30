<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\Workspace\ContentLanguage;
use App\Enums\Workspace\ImageStyle;
use App\Support\HexColorName;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image as AiImageFile;
use Laravel\Ai\Image;
use Laravel\Ai\Responses\ImageResponse;
use Throwable;

class AiImageClient
{
    private const BRAND_DESCRIPTION_MAX = 200;

    /**
     * Generate an image via the configured AI_IMAGE_PROVIDER (defaults to OpenAI).
     * Returns null on any failure so the caller can fall back to a stock photo
     * without throwing.
     *
     * @param  array<int, string>  $keywords
     * @param  array<int, string|AiImageFile>  $referenceImages
     * @return array{bytes: string, provider: string, model: string}|null
     */
    public function generate(
        array $keywords,
        ImageStyle $style,
        string $orientation = 'portrait',
        string $language = 'en',
        ?string $brandColor = null,
        ?string $backgroundColor = null,
        ?string $textColor = null,
        ?string $brandDescription = null,
        array $extendedPalette = [],
        ?string $visualNotes = null,
        ?string $brandGuidelines = null,
        string $quality = 'low',
        int $timeout = 180,
        array $typography = [],
        array $referenceImages = [],
    ): ?array {
        $keywords = $this->cleanKeywords($keywords);

        if ($keywords === []) {
            return null;
        }

        $attachments = $this->resolveAttachments($referenceImages);

        $prompt = $this->buildPrompt(
            keywords: $keywords,
            style: $style,
            language: $language,
            brandColor: $brandColor,
            backgroundColor: $backgroundColor,
            textColor: $textColor,
            brandDescription: $brandDescription,
            extendedPalette: $extendedPalette,
            visualNotes: $visualNotes,
            brandGuidelines: $brandGuidelines,
            typography: $typography,
            hasReferenceImages: ! empty($attachments),
        );

        try {
            $builder = Image::of($prompt)->quality($quality)->timeout($timeout);

            if (! empty($attachments)) {
                $builder = $builder->attachments($attachments);
            }

            $isSeedream = str_contains((string) config('ai.providers.openai.models.image.default'), 'seedream')
                || str_contains((string) config('ai.providers.openai.url'), 'byteplus');

            $builder = match ($orientation) {
                'portrait' => $isSeedream ? $builder->size('1664x2496') : $builder->portrait(),
                'landscape' => $isSeedream ? $builder->size('2496x1664') : $builder->landscape(),
                default => $isSeedream ? $builder->size('2048x2048') : $builder->square(),
            };

            return $this->toResult($builder->generate());
        } catch (Throwable $e) {
            Log::warning('AiImageClient: generation failed', [
                'style' => $style->value,
                'orientation' => $orientation,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Resolve reference image inputs (paths, URLs, or AiImageFile objects) into
     * an array of Laravel\Ai\Files\Image attachments.
     *
     * @param  array<int, string|AiImageFile>  $referenceImages
     * @return array<int, AiImageFile>
     */
    private function resolveAttachments(array $referenceImages): array
    {
        return collect($referenceImages)
            ->map(function (mixed $ref): ?AiImageFile {
                if ($ref instanceof AiImageFile) {
                    return $ref;
                }

                if (! is_string($ref) || trim($ref) === '') {
                    return null;
                }

                $path = trim($ref);

                if (filter_var($path, FILTER_VALIDATE_URL)) {
                    return AiImageFile::fromUrl($path);
                }

                if (Storage::exists($path)) {
                    return AiImageFile::fromStorage($path);
                }

                if (file_exists($path)) {
                    return AiImageFile::fromPath($path);
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, string>
     */
    private function cleanKeywords(array $keywords): array
    {
        return collect($keywords)
            ->map(fn (string $keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function buildPrompt(
        array $keywords,
        ImageStyle $style,
        string $language,
        ?string $brandColor,
        ?string $backgroundColor,
        ?string $textColor,
        ?string $brandDescription,
        array $extendedPalette = [],
        ?string $visualNotes = null,
        ?string $brandGuidelines = null,
        array $typography = [],
        bool $hasReferenceImages = false,
    ): string {
        $palette = $this->buildPaletteContext($brandColor, $backgroundColor, $textColor);
        $extendedPalette = $this->cleanExtendedPalette($extendedPalette);

        return view('prompts.post_image.generator', [
            'style' => $style->value,
            'scene' => implode(', ', $keywords),
            'language_name' => $this->languageName($language),
            'has_brand_palette' => data_get($palette, 'is_defined', false),
            'brand_color_name' => data_get($palette, 'brand_color_name'),
            'background_color_name' => data_get($palette, 'background_color_name'),
            'text_color_name' => data_get($palette, 'text_color_name'),
            'role_colors' => array_filter([
                'Brand / primary accent' => $brandColor,
                'Background / surfaces' => $backgroundColor,
                'Text / in-scene typography' => $textColor,
            ]),
            'extended_palette' => $extendedPalette,
            'visual_notes' => $this->resolveBrandContext($visualNotes, 500),
            'brand_context' => $this->resolveBrandContext($brandDescription, 200),
            'brand_guidelines' => $this->resolveBrandContext($brandGuidelines, 500),
            'brand_typography' => $this->cleanTypography($typography),
            'has_reference_images' => $hasReferenceImages,
        ])->render();
    }

    private function resolveBrandContext(?string $brandDescription, int $maxLength = self::BRAND_DESCRIPTION_MAX): ?string
    {
        $trimmed = trim((string) $brandDescription);

        if ($trimmed === '') {
            return null;
        }

        return mb_strlen($trimmed) > $maxLength
            ? mb_substr($trimmed, 0, $maxLength).'…'
            : $trimmed;
    }

    /**
     * @return array<string, string>
     */
    private function cleanExtendedPalette(mixed $palette): array
    {
        if (! is_array($palette)) {
            return [];
        }

        $clean = [];
        foreach ($palette as $name => $hex) {
            if (! is_string($name) || ! is_string($hex)) {
                continue;
            }

            $name = trim($name);
            $hex = trim($hex);
            if ($name === '' || ! preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex)) {
                continue;
            }

            $clean[$name] = $hex;
        }

        return array_slice($clean, 0, 20, true);
    }

    /**
     * @return array<string, string>
     */
    private function cleanTypography(mixed $typography): array
    {
        if (! is_array($typography)) {
            return [];
        }

        return collect($typography)
            ->filter(fn ($font): bool => is_string($font) && trim($font) !== '')
            ->map(fn (string $font): string => trim($font))
            ->all();
    }

    /**
     * Extract the raw image bytes and the provider/model that produced them.
     * Called from inside generate()'s try block so a malformed response
     * (e.g. no images) is treated as a failure, not an uncaught exception.
     *
     * @return array{bytes: string, provider: string, model: string}|null
     */
    private function toResult(ImageResponse $response): ?array
    {
        $bytes = (string) $response;

        if ($bytes === '') {
            return null;
        }

        return [
            'bytes' => $bytes,
            'provider' => (string) $response->meta->provider,
            'model' => (string) $response->meta->model,
        ];
    }

    private function languageName(string $code): string
    {
        if ($code === ContentLanguage::Chinese->value) {
            return 'Traditional Chinese';
        }

        return (ContentLanguage::tryFrom($code) ?? ContentLanguage::DEFAULT)->englishName();
    }

    /**
     * @return array{
     *   is_defined: bool,
     *   brand_color_name: ?string,
     *   background_color_name: ?string,
     *   text_color_name: ?string
     * }
     */
    private function buildPaletteContext(
        ?string $brandColor,
        ?string $backgroundColor,
        ?string $textColor,
    ): array {
        $brandColorName = $this->resolveColorName($brandColor);
        $backgroundColorName = $this->resolveColorName($backgroundColor);
        $textColorName = $this->resolveColorName($textColor);

        return [
            'is_defined' => $brandColorName !== null || $backgroundColorName !== null || $textColorName !== null,
            'brand_color_name' => $brandColorName,
            'background_color_name' => $backgroundColorName,
            'text_color_name' => $textColorName,
        ];
    }

    private function resolveColorName(?string $hex): ?string
    {
        if ($hex === null || trim($hex) === '') {
            return null;
        }

        return HexColorName::approximate($hex);
    }
}
