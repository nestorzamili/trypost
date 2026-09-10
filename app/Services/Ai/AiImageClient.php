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
        array $referenceKinds = [],
    ): ?array {
        $keywords = $this->cleanKeywords($keywords);

        if ($keywords === []) {
            return null;
        }

        $isSeedream = config('ai.default_for_images') === 'seedream';

        // Seedream conditions on reference images through its `image` array on
        // the same generations endpoint; the SDK path uses OpenAI-style
        // attachments. Build the prompt with the correct "has references" flag
        // either way.
        $attachments = $isSeedream ? [] : $this->resolveAttachments($referenceImages);
        $seedreamReferences = $isSeedream ? $this->resolveSeedreamReferences($referenceImages) : [];
        $hasReferences = $isSeedream ? $seedreamReferences !== [] : ! empty($attachments);

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
            hasReferenceImages: $hasReferences,
            referenceKinds: $hasReferences ? $referenceKinds : [],
        );

        $seedreamSize = match ($orientation) {
            'portrait' => '1664x2496',
            'landscape' => '2496x1664',
            default => '2048x2048',
        };

        // The image endpoint is the flakiest hop in the pipeline: a provider
        // timeout, a 5xx, or an unparsable body all surface here as a thrown
        // Throwable. Rather than fail the whole generation on the first hiccup,
        // retry a few times with a short backoff; only a run of failures
        // returns null so the caller can fall back to a stock photo.
        $attempts = max(1, (int) config('ai.image.max_attempts', 3));
        $baseDelayMs = max(0, (int) config('ai.image.retry_delay_ms', 500));

        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $result = $isSeedream
                    ? app(SeedreamImageClient::class)->generate(
                        prompt: $prompt,
                        size: $seedreamSize,
                        referenceImages: $seedreamReferences,
                        timeout: $timeout,
                    )
                    : $this->generateViaSdk($prompt, $orientation, $quality, $timeout, $attachments);

                // A null result means the provider answered but produced no
                // usable image — not a transient fault a retry fixes.
                if ($result === null) {
                    Log::warning('AiImageClient: generation produced no image', [
                        'style' => $style->value,
                        'orientation' => $orientation,
                        'attempt' => $attempt,
                    ]);

                    return null;
                }

                return $result;
            } catch (Throwable $e) {
                $lastError = $e;

                Log::warning('AiImageClient: generation attempt failed', [
                    'style' => $style->value,
                    'orientation' => $orientation,
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $attempts && $baseDelayMs > 0) {
                    // Linear backoff (500ms, 1000ms, ...). usleep takes µs.
                    usleep($baseDelayMs * 1000 * $attempt);
                }
            }
        }

        Log::warning('AiImageClient: generation failed after retries', [
            'style' => $style->value,
            'orientation' => $orientation,
            'attempts' => $attempts,
            'error' => $lastError?->getMessage(),
        ]);

        return null;
    }

    /**
     * Generate one image through the laravel/ai SDK (OpenAI, Gemini, xAI, ...).
     *
     * @param  array<int, AiImageFile>  $attachments
     * @return array{bytes: string, provider: string, model: string}|null
     */
    private function generateViaSdk(
        string $prompt,
        string $orientation,
        string $quality,
        int $timeout,
        array $attachments,
    ): ?array {
        $builder = Image::of($prompt)->quality($quality)->timeout($timeout);

        if (! empty($attachments)) {
            $builder = $builder->attachments($attachments);
        }

        $builder = match ($orientation) {
            'portrait' => $builder->portrait(),
            'landscape' => $builder->landscape(),
            default => $builder->square(),
        };

        return $this->toResult($builder->generate());
    }

    /**
     * Resolve reference inputs into values Seedream's `image` array accepts:
     * a public URL is passed through; a stored/local file is inlined as a
     * lowercase `data:image/...;base64,...` URI (Seedream accepts both, and can
     * mix them). Anything unreadable is dropped.
     *
     * The generation pipeline only ever passes string paths/URLs here (see
     * TemplateImageGenerator), so non-string inputs are not handled.
     *
     * @param  array<int, string>  $referenceImages
     * @return array<int, string>
     */
    private function resolveSeedreamReferences(array $referenceImages): array
    {
        return collect($referenceImages)
            ->map(function (mixed $ref): ?string {
                if (! is_string($ref) || trim($ref) === '') {
                    return null;
                }

                $ref = trim($ref);

                if (filter_var($ref, FILTER_VALIDATE_URL)) {
                    return $ref;
                }

                $contents = null;
                if (Storage::exists($ref)) {
                    $contents = Storage::get($ref);
                } elseif (file_exists($ref)) {
                    $contents = file_get_contents($ref);
                }

                if (! is_string($contents) || $contents === '') {
                    return null;
                }

                $mime = $this->guessImageMime($ref, $contents);

                return 'data:'.$mime.';base64,'.base64_encode($contents);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Best-effort image MIME for a data-URI, from extension then binary sniff.
     */
    private function guessImageMime(string $path, string $contents): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default => str_starts_with($contents, "\x89PNG") ? 'image/png' : 'image/jpeg',
        };
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
        array $referenceKinds = [],
    ): string {
        $palette = $this->buildPaletteContext($brandColor, $backgroundColor, $textColor);
        $extendedPalette = $this->cleanExtendedPalette($extendedPalette);

        // Categorise the reference photos so the prompt can treat a logo /
        // product / style board differently from a person: without this every
        // reference is prompted as a face to preserve, which mangles a logo.
        $kinds = array_map('strval', $referenceKinds);
        $hasPersonReference = (bool) array_intersect($kinds, ['face_closeup', 'full_body']);
        $hasLogoReference = in_array('logo', $kinds, true);
        $hasProductReference = in_array('product', $kinds, true);
        $hasStyleReference = in_array('style', $kinds, true);

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
            // When kinds are unknown (empty) default to the person treatment,
            // preserving the prior behaviour for callers that pass no kinds.
            'has_person_reference' => $hasReferenceImages && ($kinds === [] || $hasPersonReference),
            'has_logo_reference' => $hasReferenceImages && $hasLogoReference,
            'has_product_reference' => $hasReferenceImages && $hasProductReference,
            'has_style_reference' => $hasReferenceImages && $hasStyleReference,
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
