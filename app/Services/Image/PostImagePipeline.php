<?php

declare(strict_types=1);

namespace App\Services\Image;

use App\Enums\Media\Source;
use App\Enums\Media\Type as MediaType;
use App\Enums\PostPlatform\ContentType;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Support\ResolvedBrand;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image;

class PostImagePipeline
{
    public function __construct(
        private TemplateImageGenerator $generator,
    ) {}

    /**
     * Render the single AI image for a structured generator output. Returns a
     * one-element media-item array when an image is produced, or an empty array
     * when the generator renders nothing.
     *
     * @param  array<string, mixed>  $structured
     * @param  array<int, string|Image>  $referenceImages
     * @return array<int, array<string, mixed>>
     */
    public function forSingle(Workspace $workspace, SocialAccount $account, array $structured, ?ContentType $contentType, bool $applyBrandVisuals = true, ?ResolvedBrand $brand = null, array $referenceImages = [], array $referenceKinds = []): array
    {
        ['width' => $width, 'height' => $height] = $this->dimensionsForContentType($contentType);

        $rendered = $this->generator->render(
            workspace: $workspace,
            socialAccount: $account,
            title: (string) data_get($structured, 'image_title', ''),
            body: (string) data_get($structured, 'image_body', ''),
            imageKeywords: data_get($structured, 'image_keywords', []),
            width: $width,
            height: $height,
            applyBrandVisuals: $applyBrandVisuals,
            brand: $brand,
            referenceImages: $referenceImages,
            referenceKinds: $referenceKinds,
        );

        if (! $rendered) {
            return [];
        }

        return [$this->buildAiMediaItem($workspace, $rendered)];
    }

    /**
     * Render one AI image per slide in the structured carousel output, keyed by
     * slide index. Slides that render nothing are omitted (leaving a gap at
     * that index), so the caller can tell which slides still need work.
     *
     * Pass $existingSlideMedia (index => media-item) to resume a partially
     * rendered carousel: any slide already present is reused verbatim — it is
     * NOT re-rendered and NOT re-billed. This is what makes a retry idempotent
     * and stops the double-billing that a full re-render would cause.
     *
     * @param  array<string, mixed>  $structured
     * @param  array<int, string|Image>  $referenceImages
     * @param  array<int, array<string, mixed>>  $existingSlideMedia
     * @return array<int, array<string, mixed>> index => media-item
     */
    public function forCarousel(Workspace $workspace, SocialAccount $account, array $structured, ?ContentType $contentType, bool $applyBrandVisuals = true, ?ResolvedBrand $brand = null, array $referenceImages = [], array $referenceKinds = [], array $existingSlideMedia = []): array
    {
        ['width' => $width, 'height' => $height] = $this->dimensionsForContentType($contentType);

        $media = [];

        foreach (array_values(data_get($structured, 'slides', [])) as $index => $slide) {
            // Reuse an already-rendered slide as-is: no render call, no billing.
            $existing = $existingSlideMedia[$index] ?? null;
            if (is_array($existing) && $existing !== []) {
                $media[$index] = $existing;

                continue;
            }

            $rendered = $this->generator->render(
                workspace: $workspace,
                socialAccount: $account,
                title: (string) data_get($slide, 'title', ''),
                body: (string) data_get($slide, 'body', ''),
                imageKeywords: data_get($slide, 'image_keywords', []),
                width: $width,
                height: $height,
                applyBrandVisuals: $applyBrandVisuals,
                brand: $brand,
                referenceImages: $referenceImages,
                referenceKinds: $referenceKinds,
            );

            if ($rendered) {
                $media[$index] = $this->buildAiMediaItem($workspace, $rendered);
            }
        }

        return $media;
    }

    /**
     * Render a tweet-card image for the given text and return a one-element
     * media-item array, or an empty array when the generator renders nothing.
     *
     * When $imageKeywords is non-null a blurred AI-photo background is used
     * (tweet_card_image); null produces the solid brand-color background (tweet_card).
     *
     * @param  array<int, string>|null  $imageKeywords
     * @param  array<int, string|Image>  $referenceImages
     * @return array<int, array<string, mixed>>
     */
    public function forTweetCard(Workspace $workspace, SocialAccount $account, string $tweetText, ?array $imageKeywords = null, ?ResolvedBrand $brand = null, array $referenceImages = [], array $referenceKinds = []): array
    {
        $rendered = $this->generator->renderTweetCard(
            workspace: $workspace,
            socialAccount: $account,
            tweetText: $tweetText,
            imageKeywords: $imageKeywords,
            brand: $brand,
            referenceImages: $referenceImages,
            referenceKinds: $referenceKinds,
        );

        if (! $rendered) {
            return [];
        }

        return [$this->buildAiMediaItem($workspace, $rendered)];
    }

    /**
     * Render one tweet-card image per slide and return the media-item array.
     * Slides that render nothing are skipped.
     *
     * Each entry in $slides is either a plain string (tweet text, solid background)
     * or an array with keys `tweet_text` and optionally `image_keywords` (image bg).
     *
     * @param  array<int, string|array<string, mixed>>  $slides
     * @param  array<int, string|Image>  $referenceImages
     * @return array<int, array<string, mixed>>
     */
    public function forTweetCardCarousel(Workspace $workspace, SocialAccount $account, array $slides, ?ResolvedBrand $brand = null, array $referenceImages = [], array $referenceKinds = []): array
    {
        $media = [];

        foreach ($slides as $slide) {
            if (is_string($slide)) {
                $tweetText = $slide;
                $imageKeywords = null;
            } else {
                $tweetText = (string) data_get($slide, 'tweet_text', '');
                $imageKeywords = data_get($slide, 'image_keywords');
            }

            $rendered = $this->generator->renderTweetCard(
                workspace: $workspace,
                socialAccount: $account,
                tweetText: $tweetText,
                imageKeywords: $imageKeywords,
                brand: $brand,
                referenceImages: $referenceImages,
                referenceKinds: $referenceKinds,
            );

            if ($rendered) {
                $media[] = $this->buildAiMediaItem($workspace, $rendered);
            }
        }

        return $media;
    }

    /**
     * Resolve the AI image dimensions for the given content type, falling back
     * to the generator defaults (4:5 portrait) when no content type is known.
     *
     * @return array{width: int, height: int}
     */
    private function dimensionsForContentType(?ContentType $contentType): array
    {
        return $contentType
            ? $contentType->aiImageDimensions()
            : ['width' => TemplateImageGenerator::DEFAULT_WIDTH, 'height' => TemplateImageGenerator::DEFAULT_HEIGHT];
    }

    /**
     * @param  array{path: string, source_meta: array<string, mixed>}  $rendered
     * @return array<string, mixed>
     */
    private function buildAiMediaItem(Workspace $workspace, array $rendered): array
    {
        $media = $workspace->media()->create([
            'collection' => 'ai-generated',
            'type' => MediaType::Image,
            'path' => $rendered['path'],
            'original_filename' => basename($rendered['path']),
            'mime_type' => 'image/webp',
            'size' => Storage::size($rendered['path']),
            'order' => 0,
        ]);

        return [
            'id' => $media->id,
            'path' => $media->path,
            'url' => $media->url,
            'type' => 'image',
            'mime_type' => 'image/webp',
            'source' => Source::Ai->value,
            'source_meta' => $rendered['source_meta'],
        ];
    }
}
