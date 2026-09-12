<?php

declare(strict_types=1);

namespace App\Enums\Media;

enum Type: string
{
    case Image = 'image';
    case Video = 'video';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Imagem',
            self::Video => 'Vídeo',
            self::Document => 'Documento',
        };
    }

    /**
     * Allow-list of MIME types we accept on upload / URL fetch.
     *
     * Video accepts MP4 plus QuickTime/MOV. Modern .mov files (iPhone
     * recordings, screen captures) are ISO BMFF containers — the same
     * format MP4 uses — so social platforms decode them like MP4 even
     * if PHP reports `video/quicktime`. Accepting MOV avoids forcing
     * iPhone users to transcode before uploading.
     *
     * WebM is rejected: X / IG / TikTok / FB / Pinterest / Bluesky /
     * Threads all reject the Matroska + VP8/VP9 stack. Without
     * server-side transcoding, accepting WebM would just produce
     * platform-specific publish failures down the line.
     *
     * Document accepts PDF only — the swipeable LinkedIn document
     * (carousel) format. PPTX/DOCX are also valid LinkedIn documents
     * but are converted server-side by LinkedIn and lose fonts, so we
     * keep the surface to PDF.
     *
     * @return array<int, string>
     */
    public function allowedMimeTypes(): array
    {
        return match ($this) {
            self::Image => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', ...self::RAW_MIME_TYPES],
            self::Video => ['video/mp4', 'video/quicktime'],
            self::Document => ['application/pdf'],
        };
    }

    /**
     * Camera RAW / DNG MIME types accepted on upload. RAW is never published
     * as-is (no social platform accepts it) — it is decoded and re-encoded to
     * JPEG at ingest via ImageMagick + libraw. Many RAW files are reported by
     * the OS as `application/octet-stream`, so extension-based validation
     * ({@see extensions()}) is the reliable gate; these cover the cases where a
     * concrete image MIME is available.
     *
     * @var array<int, string>
     */
    private const RAW_MIME_TYPES = [
        'image/x-adobe-dng',
        'image/x-canon-cr2',
        'image/x-canon-cr3',
        'image/x-canon-crw',
        'image/x-nikon-nef',
        'image/x-nikon-nrw',
        'image/x-sony-arw',
        'image/x-panasonic-rw2',
        'image/x-olympus-orf',
        'image/x-fuji-raf',
        'image/x-pentax-pef',
        'image/x-samsung-srw',
    ];

    /**
     * Filename extensions for camera RAW / DNG formats. Mirrors
     * {@see RAW_MIME_TYPES} and is the primary upload gate for RAW, since the OS
     * often cannot resolve a specific RAW MIME.
     *
     * @var array<int, string>
     */
    public const RAW_EXTENSIONS = [
        'dng', 'cr2', 'cr3', 'crw', 'nef', 'nrw', 'arw', 'rw2', 'orf', 'raf', 'pef', 'srw',
    ];

    /**
     * Filename extensions that match this type. Mirrors allowedMimeTypes
     * for callers that validate by name instead of MIME.
     *
     * @return array<int, string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png', 'gif', 'webp', ...self::RAW_EXTENSIONS],
            self::Video => ['mp4', 'mov'],
            self::Document => ['pdf'],
        };
    }

    public function maxSizeInMb(): int
    {
        return (int) config("trypost.media.max_size_mb.{$this->value}");
    }

    public function maxSizeInBytes(): int
    {
        return $this->maxSizeInMb() * 1024 * 1024;
    }

    public function maxSizeInKb(): int
    {
        return $this->maxSizeInMb() * 1024;
    }

    /**
     * Resolve a Type from a MIME string. Returns null when the MIME is
     * not in any type's allow-list.
     */
    public static function fromMime(string $mime): ?self
    {
        foreach (self::cases() as $type) {
            if (in_array($mime, $type->allowedMimeTypes(), true)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Classify media by what it *is* — for "is this an image/video/PDF?" checks,
     * as opposed to fromMime() which is the strict upload allow-list. Any
     * `image/*`, `video/*`, or `application/pdf` MIME maps to its type; when the
     * MIME is missing it falls back to the filename extension so already-stored
     * files still resolve.
     */
    public static function classify(?string $mimeType, ?string $path = null): ?self
    {
        $extension = $path ? pathinfo($path, PATHINFO_EXTENSION) : null;

        if (filled($mimeType)) {
            $byMime = match (true) {
                str_starts_with($mimeType, 'image/') => self::Image,
                str_starts_with($mimeType, 'video/') => self::Video,
                $mimeType === 'application/pdf' => self::Document,
                default => null,
            };

            if ($byMime !== null) {
                return $byMime;
            }

            // The OS commonly reports RAW as the generic octet-stream; only that
            // ambiguous case falls back to the extension, so a .dng / .cr2
            // upload still classifies as an image. A concrete-but-unsupported
            // MIME (e.g. application/zip) stays null — the extension is not
            // consulted, matching the strict upload contract.
            return $mimeType === 'application/octet-stream'
                ? self::fromExtension($extension)
                : null;
        }

        return self::fromExtension($extension);
    }

    /**
     * Classify by filename extension. Broader than extensions() (the upload
     * allow-list) so already-stored files in legacy formats still resolve.
     */
    public static function fromExtension(?string $extension): ?self
    {
        $extension = strtolower((string) $extension);

        return match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif', ...self::RAW_EXTENSIONS], true) => self::Image,
            in_array($extension, ['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv', 'm4v'], true) => self::Video,
            $extension === 'pdf' => self::Document,
            default => null,
        };
    }

    /**
     * Whether the MIME is an animated GIF — several publishers handle it
     * specially (skipped from optimization, or posted as video).
     */
    public static function isGif(?string $mimeType): bool
    {
        return $mimeType === 'image/gif';
    }

    /**
     * Whether a source is a camera RAW / DNG file, by MIME or by extension.
     * RAW must be decoded to JPEG at ingest (it is never publishable as-is), so
     * the format-normalization step routes these through ImageMagick + libraw
     * rather than the GD path, which cannot decode RAW.
     */
    public static function isRaw(?string $mimeType, ?string $extension = null): bool
    {
        if (filled($mimeType) && in_array($mimeType, self::RAW_MIME_TYPES, true)) {
            return true;
        }

        return in_array(strtolower((string) $extension), self::RAW_EXTENSIONS, true);
    }
}
