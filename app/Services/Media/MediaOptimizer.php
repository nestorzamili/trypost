<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Enums\SocialAccount\Platform;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Direction;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class MediaOptimizer
{
    private const MAX_DECODE_MEMORY_BYTES = 256 * 1024 * 1024;

    /**
     * Minimum byte size for an embedded RAW preview to be treated as the
     * full-size camera render rather than a tiny thumbnail (~160×120). Real
     * previews are hundreds of KB; a few-KB blob is a thumbnail we skip.
     */
    private const MIN_PREVIEW_BYTES = 30 * 1024;

    private const FIT_BLUR_SIGMA = 55;

    private const FIT_BLUR_GAMMA = 1.3;

    private const FIT_QUALITY = 90;

    private const FIT_GD_DOWNSCALE = 8;

    private const FIT_GD_BLUR = 45;

    private const FIT_GD_BRIGHTNESS = 12;

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(Driver::class);
    }

    /**
     * Optimize an image for a specific platform.
     * Returns path to optimized temp file (caller must clean up).
     */
    public function optimizeImage(string $filePath, Platform $platform): string
    {
        $config = $this->getImageConfig($platform);

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo !== false && $this->estimatedDecodeMemory($imageInfo) > self::MAX_DECODE_MEMORY_BYTES) {
            Log::warning('MediaOptimizer: Image too large for GD processing', [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'platform' => $platform->value,
            ]);

            $tempFile = tempnam(sys_get_temp_dir(), 'media_opt_');
            copy($filePath, $tempFile);

            return $tempFile;
        }

        $image = $this->manager->decodePath($filePath);

        $maxWidth = data_get($config, 'max_width');
        $maxSize = data_get($config, 'max_size');
        $format = data_get($config, 'format');
        $quality = data_get($config, 'quality');

        // Resize if needed (maintain aspect ratio, never upscale)
        if ($maxWidth && $image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'media_opt_');
        $this->encodeWithinByteBudget($image, $tempFile, $format, $quality, $maxSize, $platform->value);

        return $tempFile;
    }

    /**
     * Optimize a still image for a storage collection at upload time. Returns a
     * temp file path (caller must clean up), or null when the collection has no
     * optimization profile.
     */
    public function optimizeForCollection(string $filePath, string $collection): ?string
    {
        $config = config("trypost.media.upload_optimization.{$collection}");

        if (! is_array($config)) {
            return null;
        }

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo !== false && $this->estimatedDecodeMemory($imageInfo) > self::MAX_DECODE_MEMORY_BYTES) {
            Log::warning('MediaOptimizer: image too large for GD processing, storing as-is', [
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'collection' => $collection,
            ]);

            return $this->copyToTempFile($filePath);
        }

        $maxWidth = (int) data_get($config, 'max_width');
        $maxBytes = (int) data_get($config, 'max_bytes');
        $quality = (int) data_get($config, 'quality');

        // Minimal-touch: a source that is already JPEG, within the width cap and
        // under the byte budget is stored byte-for-byte. Decoding + re-encoding
        // it would inflict generational JPEG loss for zero benefit, so skip it
        // entirely — no pixel is ever altered unless a conversion or downscale
        // is genuinely required.
        $isJpeg = $imageInfo !== false && ($imageInfo[2] ?? null) === IMAGETYPE_JPEG;
        $withinWidth = $maxWidth <= 0 || ($imageInfo !== false && $imageInfo[0] <= $maxWidth);
        $withinBytes = @filesize($filePath) <= $maxBytes;

        if ($isJpeg && $withinWidth && $withinBytes) {
            return $this->copyToTempFile($filePath);
        }

        $image = $this->manager->decodePath($filePath)->orient();

        if ($maxWidth > 0 && $image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'media_opt_');
        $this->encodeWithinByteBudget($image, $tempFile, 'image/jpeg', $quality, $maxBytes, $collection);

        return $tempFile;
    }

    /**
     * Whether this host can decode camera RAW / DNG. Requires ext-imagick,
     * which is built against ImageMagick + libraw in the project image. GD (the
     * default driver) cannot read RAW, so callers must gate on this and reject
     * RAW uploads cleanly when it is false rather than storing a broken file.
     */
    public function canDecodeRaw(): bool
    {
        return extension_loaded('imagick');
    }

    /**
     * Render a camera RAW / DNG source to a JPEG temp file (caller must clean
     * up). RAW is a negative no social platform accepts, so it is always
     * converted at ingest. Oriented from EXIF, downscaled to $maxWidth when
     * given, and encoded at a near-lossless quality.
     *
     * Source selection, in order:
     *  1. The full-size camera-rendered preview embedded in the RAW (extracted
     *     with exiftool). This is what the phone/camera actually produced —
     *     tone-mapped, white-balanced, the image the user sees. For
     *     computational RAW (e.g. Pixel HDR+) this is the ONLY way to match the
     *     expected look; a raw demosaic of the linear sensor data comes out
     *     dark and colour-shifted because the vendor's tone pipeline can't be
     *     reproduced.
     *  2. Fallback: a libraw demosaic via the `dng:` decoder. The `dng:` prefix
     *     forces the RAW decoder regardless of filename — assembled chunk
     *     uploads have no extension, so a bare path would be sniffed as TIFF and
     *     fail. Used only when no embedded preview exists.
     *
     * @throws RuntimeException when the host cannot decode RAW (no ext-imagick).
     */
    public function renderRawToJpeg(string $filePath, ?int $maxWidth = null, int $quality = 95): string
    {
        if (! $this->canDecodeRaw()) {
            throw new RuntimeException('Cannot decode RAW image: the imagick extension is not available on this host.');
        }

        if (! is_file($filePath)) {
            throw new RuntimeException("Unable to read RAW image for decoding: {$filePath}");
        }

        $preview = $this->extractEmbeddedPreview($filePath);

        try {
            $imagick = new \Imagick;

            if ($preview !== null) {
                $imagick->readImageBlob($preview);
            } else {
                $imagick->readImage('dng:'.$filePath);
            }

            $imagick->setImageFormat('jpeg');
            $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            $this->applyExifOrientation($imagick);

            if ($maxWidth !== null && $maxWidth > 0 && $imagick->getImageWidth() > $maxWidth) {
                $imagick->scaleImage($maxWidth, 0);
            }

            $imagick->setImageCompressionQuality($quality);
            $imagick->stripImage();

            $tempFile = tempnam(sys_get_temp_dir(), 'media_raw_');
            $imagick->writeImage($tempFile);
            $imagick->clear();

            return $tempFile;
        } catch (\ImagickException $e) {
            throw new RuntimeException("Failed to decode RAW image: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * Extract the largest camera-rendered JPEG preview embedded in a RAW file
     * via exiftool, or null when exiftool is unavailable or the file carries no
     * usable preview. Cameras store the preview under different tags —
     * PreviewImage (DNG/Nikon), JpgFromRaw (Canon CR2), OtherImage — so each is
     * tried in turn and the first that yields real JPEG bytes wins.
     */
    private function extractEmbeddedPreview(string $filePath): ?string
    {
        if (! $this->canExtractRawPreview()) {
            return null;
        }

        foreach (['JpgFromRaw', 'PreviewImage', 'OtherImage'] as $tag) {
            $process = new Process(['exiftool', '-b', "-{$tag}", $filePath]);
            $process->run();

            if (! $process->isSuccessful()) {
                continue;
            }

            $bytes = $process->getOutput();

            // A real embedded preview is a JPEG (starts with the SOI marker).
            // Guard against empty output and against tiny thumbnails masquerading
            // as previews.
            if (strlen($bytes) >= self::MIN_PREVIEW_BYTES && str_starts_with($bytes, "\xFF\xD8\xFF")) {
                return $bytes;
            }
        }

        return null;
    }

    /**
     * Whether the exiftool binary is on PATH for embedded-preview extraction.
     */
    public function canExtractRawPreview(): bool
    {
        return (new ExecutableFinder)->find('exiftool') !== null;
    }

    /**
     * Bake the EXIF orientation into the pixels and reset the flag, so the JPEG
     * renders upright everywhere. Uses Imagick::autoOrient() when the binding
     * exposes it, otherwise applies the rotation/flip for the orientation tag
     * by hand — the method name has moved across Imagick versions.
     */
    private function applyExifOrientation(\Imagick $imagick): void
    {
        if (method_exists($imagick, 'autoOrient')) {
            $imagick->autoOrient();

            return;
        }

        $orientation = $imagick->getImageOrientation();
        $white = new \ImagickPixel('#ffffff');

        match ($orientation) {
            \Imagick::ORIENTATION_TOPRIGHT => $imagick->flopImage(),
            \Imagick::ORIENTATION_BOTTOMRIGHT => $imagick->rotateImage($white, 180),
            \Imagick::ORIENTATION_BOTTOMLEFT => $imagick->flipImage(),
            \Imagick::ORIENTATION_LEFTTOP => (function () use ($imagick, $white): void {
                $imagick->flopImage();
                $imagick->rotateImage($white, 90);
            })(),
            \Imagick::ORIENTATION_RIGHTTOP => $imagick->rotateImage($white, 90),
            \Imagick::ORIENTATION_RIGHTBOTTOM => (function () use ($imagick, $white): void {
                $imagick->flopImage();
                $imagick->rotateImage($white, 270);
            })(),
            \Imagick::ORIENTATION_LEFTBOTTOM => $imagick->rotateImage($white, 270),
            default => null,
        };

        $imagick->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
    }

    /**
     * Copy a source file to a fresh temp file (caller must clean up), used when
     * the source is stored unchanged.
     */
    private function copyToTempFile(string $filePath): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'media_opt_');
        copy($filePath, $tempFile);

        return $tempFile;
    }

    /**
     * Encode to a temp file at the given quality, then iteratively shrink
     * dimensions by 10% per step until the result fits the byte budget, down to
     * a 100px safety floor.
     */
    private function encodeWithinByteBudget(ImageInterface $image, string $tempFile, string $format, int $quality, int $maxSize, string $context): void
    {
        $encoded = $image->encodeUsingMediaType($format, quality: $quality);
        file_put_contents($tempFile, (string) $encoded);

        while (filesize($tempFile) > $maxSize) {
            $newWidth = (int) ($image->width() * 0.9);
            $newHeight = (int) ($image->height() * 0.9);

            if ($newWidth < 100 || $newHeight < 100) {
                Log::warning('MediaOptimizer: image cannot fit size budget', [
                    'context' => $context,
                    'final_width' => $image->width(),
                    'final_height' => $image->height(),
                    'final_bytes' => filesize($tempFile),
                    'budget_bytes' => $maxSize,
                ]);
                break;
            }

            $image->scale(width: $newWidth, height: $newHeight);
            $encoded = $image->encodeUsingMediaType($format, quality: $quality);
            file_put_contents($tempFile, (string) $encoded);
        }
    }

    /**
     * The maximum image width (px) enforced for a platform. Pull-from-URL
     * publishers (e.g. TikTok) use this to decide whether a source image needs
     * a resized, spec-compliant derivative before the platform fetches it.
     */
    public function maxWidthForPlatform(Platform $platform): ?int
    {
        $maxWidth = data_get($this->getImageConfig($platform), 'max_width');

        return is_int($maxWidth) ? $maxWidth : null;
    }

    /**
     * Center-crop an image to the given aspect ratio (width / height).
     * Returns path to a temp file (caller must clean up).
     */
    public function cropToAspectRatio(string $filePath, float $ratio): string
    {
        $this->assertWithinMemoryBudget($filePath);

        $image = $this->manager->decodePath($filePath);

        $width = $image->width();
        $height = $image->height();
        $current = $width / $height;

        if (abs($current - $ratio) < 0.001) {
            $tempFile = tempnam(sys_get_temp_dir(), 'media_crop_');
            copy($filePath, $tempFile);

            return $tempFile;
        }

        if ($current > $ratio) {
            // Wider than target: keep height, shrink width.
            $newWidth = (int) round($height * $ratio);
            $newHeight = $height;
        } else {
            // Taller than target: keep width, shrink height.
            $newWidth = $width;
            $newHeight = (int) round($width / $ratio);
        }

        $offsetX = (int) round(($width - $newWidth) / 2);
        $offsetY = (int) round(($height - $newHeight) / 2);

        $image->crop($newWidth, $newHeight, $offsetX, $offsetY);

        $tempFile = tempnam(sys_get_temp_dir(), 'media_crop_');
        $encoded = $image->encodeUsingMediaType('image/jpeg', quality: 100);
        file_put_contents($tempFile, (string) $encoded);

        return $tempFile;
    }

    /**
     * Fit an image inside a width×height canvas without cropping: the image is
     * scaled to fit and centered, and the empty space is filled with a blurred,
     * slightly darkened copy of the image. When the image already matches the
     * canvas ratio it's just scaled down (no background). Returns a temp file.
     */
    public function fitToCanvas(string $filePath, int $width, int $height): string
    {
        $this->assertWithinMemoryBudget($filePath);

        $foreground = $this->manager->decodePath($filePath);
        $canvasRatio = $width / $height;
        $imageRatio = $foreground->width() / $foreground->height();

        $tempFile = tempnam(sys_get_temp_dir(), 'media_fit_');

        try {
            if (abs($imageRatio - $canvasRatio) < 0.01) {
                $sized = $foreground->scaleDown($width, $height);
                file_put_contents($tempFile, (string) $sized->encodeUsingMediaType('image/jpeg', quality: self::FIT_QUALITY));

                return $tempFile;
            }

            $canvas = extension_loaded('imagick')
                ? $this->fitOntoBlurredBackground($filePath, $width, $height)
                : $this->fitOntoBlurredBackgroundGd($filePath, $width, $height);

            file_put_contents($tempFile, (string) $canvas->encodeUsingMediaType('image/jpeg', quality: self::FIT_QUALITY));

            return $tempFile;
        } catch (\Throwable $e) {
            @unlink($tempFile);

            throw $e;
        }
    }

    /**
     * Fit the image onto the canvas over a soft, lightened blurred background
     * built from the image itself: the image is scaled to fill the width, heavily
     * gaussian-blurred so shapes dissolve into a colour wash, lightened, and the
     * top half is mirrored onto the bottom so the background reads symmetrically.
     * Imagick only — blurring at full width before stretching avoids the streaks
     * and posterisation the GD path is prone to.
     */
    private function fitOntoBlurredBackground(string $filePath, int $width, int $height): ImageInterface
    {
        $manager = new ImageManager(new ImagickDriver);

        $foreground = $manager->decodePath($filePath);
        $sourceWidth = $foreground->width();
        $sourceHeight = $foreground->height();
        $scale = min($width / $sourceWidth, $height / $sourceHeight);
        $foreground->resize(max(1, (int) round($sourceWidth * $scale)), max(1, (int) round($sourceHeight * $scale)));

        $scaledHeight = max(1, (int) round($sourceHeight * ($width / $sourceWidth)));
        $topHalf = $manager->decodePath($filePath)->resize($width, $scaledHeight);
        $core = $topHalf->core()->native();
        $core->blurImage(0, self::FIT_BLUR_SIGMA);
        $core->gammaImage(self::FIT_BLUR_GAMMA);
        $topHalf->resize($width, intdiv($height, 2));

        $canvas = $manager->createImage($width, $height)->fill('000000');
        $canvas->insert($topHalf, 0, 0, 'top-left');
        $topHalf->flip(Direction::VERTICAL);
        $canvas->insert($topHalf, 0, intdiv($height, 2), 'top-left');
        $canvas->insert($foreground, 0, 0, 'center');

        return $canvas;
    }

    /**
     * GD fallback for hosts without ext-imagick: a downscale→blur→upscale
     * background. Less refined than the Imagick path (no large smooth gaussian),
     * but artefact-free enough for a story background.
     */
    private function fitOntoBlurredBackgroundGd(string $filePath, int $width, int $height): ImageInterface
    {
        $foreground = $this->manager->decodePath($filePath);
        $scale = min($width / $foreground->width(), $height / $foreground->height());
        $foreground->resize(max(1, (int) round($foreground->width() * $scale)), max(1, (int) round($foreground->height() * $scale)));

        $canvas = $this->manager->decodePath($filePath)
            ->cover($width, $height)
            ->resize(intdiv($width, self::FIT_GD_DOWNSCALE), intdiv($height, self::FIT_GD_DOWNSCALE))
            ->blur(self::FIT_GD_BLUR)
            ->resize($width, $height)
            ->brightness(self::FIT_GD_BRIGHTNESS);

        $canvas->insert($foreground, 0, 0, 'center');

        return $canvas;
    }

    /**
     * Estimated GD memory (bytes) needed to decode an image, from its
     * getimagesize() metadata.
     *
     * @param  array{0: int, 1: int, channels?: int}  $imageInfo
     */
    private function estimatedDecodeMemory(array $imageInfo): float
    {
        return $imageInfo[0] * $imageInfo[1] * ($imageInfo['channels'] ?? 4) * 1.5;
    }

    /**
     * Reject a source whose pixel dimensions would blow the GD memory budget,
     * before it is decoded — a small-byte, huge-dimension image would otherwise
     * exhaust memory with an uncatchable fatal. Transforms that can't fall back
     * to the original (crop, fit) call this; `optimizeImage` skips instead.
     */
    private function assertWithinMemoryBudget(string $filePath): void
    {
        $imageInfo = @getimagesize($filePath);

        if ($imageInfo === false) {
            return;
        }

        if ($this->estimatedDecodeMemory($imageInfo) > self::MAX_DECODE_MEMORY_BYTES) {
            throw new RuntimeException("Image dimensions ({$imageInfo[0]}x{$imageInfo[1]}) exceed the safe processing budget.");
        }
    }

    /**
     * @return array{max_width: int, max_size: int, format: string, quality: int}
     */
    private function getImageConfig(Platform $platform): array
    {
        return match ($platform) {
            Platform::Instagram, Platform::InstagramFacebook, Platform::Threads => [
                'max_width' => 1440,
                'max_size' => 8 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::Facebook => [
                'max_width' => 2048,
                'max_size' => 4 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::X => [
                'max_width' => 2048,
                'max_size' => 5 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::TikTok => [
                'max_width' => 1080,
                'max_size' => 20 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::LinkedIn, Platform::LinkedInPage => [
                'max_width' => 2048,
                'max_size' => 10 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::Pinterest => [
                'max_width' => 1000,
                'max_size' => 20 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::Bluesky => [
                'max_width' => 2048,
                'max_size' => 976 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::Mastodon => [
                'max_width' => 2048,
                'max_size' => 10 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::YouTube => [
                'max_width' => 1920,
                'max_size' => 2 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::Telegram => [
                'max_width' => 2048,
                'max_size' => 10 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
            Platform::Discord => [
                'max_width' => 2048,
                'max_size' => 8 * 1024 * 1024,
                'format' => 'image/jpeg',
                'quality' => 100,
            ],
        };
    }
}
