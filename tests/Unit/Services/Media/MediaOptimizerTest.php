<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Services\Media\MediaOptimizer;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\Process\ExecutableFinder;

function createTestImage(int $width, int $height, string $format = 'image/jpeg', string $fill = 'cccccc'): string
{
    $manager = new ImageManager(Driver::class);
    $image = $manager->createImage($width, $height)->fill($fill);
    $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
    $encoded = $image->encodeUsingMediaType($format);
    file_put_contents($tempFile, (string) $encoded);

    return $tempFile;
}

function createNoisyJpeg(int $width, int $height): string
{
    $gd = imagecreatetruecolor($width, $height);
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            imagesetpixel($gd, $x, $y, imagecolorallocate($gd, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
    }
    $tempFile = tempnam(sys_get_temp_dir(), 'noisy_img_');
    imagejpeg($gd, $tempFile, 100);
    imagedestroy($gd);

    return $tempFile;
}

/**
 * A valid PNG header declaring huge dimensions with no pixel data: getimagesize
 * reads the size cheaply, so the memory-budget guard fires without allocating a
 * multi-gigabyte GD buffer.
 */
function createHugeHeaderImage(int $width, int $height): string
{
    $ihdr = pack('N', $width).pack('N', $height)."\x08\x02\x00\x00\x00";
    $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', 0);
    $tempFile = tempnam(sys_get_temp_dir(), 'huge_hdr_');
    file_put_contents($tempFile, $png);

    return $tempFile;
}

/**
 * A wide image split into a light top half and a dark bottom half, for asserting
 * the story background mirrors the top onto the bottom.
 */
function createTwoToneImage(int $width, int $height, string $topHex, string $bottomHex): string
{
    $manager = new ImageManager(Driver::class);
    $image = $manager->createImage($width, $height)->fill($topHex);
    $bottom = $manager->createImage($width, intdiv($height, 2))->fill($bottomHex);
    $image->insert($bottom, 0, intdiv($height, 2), 'top-left');
    $tempFile = tempnam(sys_get_temp_dir(), 'twotone_');
    file_put_contents($tempFile, (string) $image->encodeUsingMediaType('image/jpeg'));

    return $tempFile;
}

$tempFiles = [];

afterEach(function () use (&$tempFiles) {
    foreach ($tempFiles as $file) {
        @unlink($file);
    }
    $tempFiles = [];
});

it('resizes wide image for instagram', function () use (&$tempFiles) {
    $source = createTestImage(3000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1440);

    $bytes = file_get_contents($result);
    expect(ord($bytes[0]))->toBe(0xFF)
        ->and(ord($bytes[1]))->toBe(0xD8);
});

it('resizes for bluesky under 1mb', function () use (&$tempFiles) {
    $source = createTestImage(2000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Bluesky);
    $tempFiles[] = $result;

    expect(filesize($result))->toBeLessThan(976 * 1024);
});

it('does not upscale small images', function () use (&$tempFiles) {
    $source = createTestImage(500, 500);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBe(500);
});

it('converts png to jpeg for instagram', function () use (&$tempFiles) {
    $source = createTestImage(800, 600, 'image/png');
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Instagram);
    $tempFiles[] = $result;

    $bytes = file_get_contents($result);
    expect(ord($bytes[0]))->toBe(0xFF)
        ->and(ord($bytes[1]))->toBe(0xD8);
});

it('resizes for tiktok max 1080', function () use (&$tempFiles) {
    $source = createTestImage(2000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::TikTok);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1080);
});

it('resizes for pinterest max 1000', function () use (&$tempFiles) {
    $source = createTestImage(2000, 3000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Pinterest);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(1000);
});

it('exposes the configured max width per platform', function () {
    $optimizer = new MediaOptimizer;

    expect($optimizer->maxWidthForPlatform(Platform::TikTok))->toBe(1080)
        ->and($optimizer->maxWidthForPlatform(Platform::Instagram))->toBe(1440)
        ->and($optimizer->maxWidthForPlatform(Platform::Pinterest))->toBe(1000);
});

it('reports a max width for every platform', function () {
    $optimizer = new MediaOptimizer;

    foreach (Platform::cases() as $platform) {
        expect($optimizer->maxWidthForPlatform($platform))->toBeInt()->toBeGreaterThan(0);
    }
});

it('handles all platforms without error', function () use (&$tempFiles) {
    $source = createTestImage(1000, 800);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;

    foreach (Platform::cases() as $platform) {
        $result = $optimizer->optimizeImage($source, $platform);
        $tempFiles[] = $result;

        expect(file_exists($result))->toBeTrue()
            ->and(filesize($result))->toBeGreaterThan(0);
    }
});

it('returns valid temp file path', function () use (&$tempFiles) {
    $source = createTestImage(800, 600);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeImage($source, Platform::Facebook);
    $tempFiles[] = $result;

    expect(file_exists($result))->toBeTrue()
        ->and(filesize($result))->toBeGreaterThan(0);
});

it('crops a wide image to a 1:1 square', function () use (&$tempFiles) {
    $source = createTestImage(1920, 1080);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 1.0);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    expect($cropped->width())->toBe($cropped->height());
    expect($cropped->height())->toBe(1080);
});

it('crops a tall image to a 4:5 portrait', function () use (&$tempFiles) {
    $source = createTestImage(1000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 4 / 5);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    $ratio = $cropped->width() / $cropped->height();
    expect(abs($ratio - 0.8))->toBeLessThan(0.01);
});

it('crops a square image to a 16:9 landscape', function () use (&$tempFiles) {
    $source = createTestImage(1080, 1080);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 16 / 9);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    $ratio = $cropped->width() / $cropped->height();
    expect(abs($ratio - 16 / 9))->toBeLessThan(0.01);
});

it('returns a copy when image already matches the target ratio', function () use (&$tempFiles) {
    $source = createTestImage(800, 800);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->cropToAspectRatio($source, 1.0);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $cropped = $manager->decodePath($result);

    expect($cropped->width())->toBe(800);
    expect($cropped->height())->toBe(800);
});

it('fits an off-ratio image over a lightened, image-derived blurred background', function () use (&$tempFiles) {
    $source = createTestImage(1200, 400, 'image/jpeg', '3366cc'); // wide, solid blue
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->fitToCanvas($source, 1080, 1920);
    $tempFiles[] = $result;

    $out = (new ImageManager(Driver::class))->decodePath($result);

    expect($out->width())->toBe(1080)
        ->and($out->height())->toBe(1920);

    // The background is derived from the image (blue), never a black letterbox,
    // and lightened via gamma — the corner's blue channel exceeds the source's 0xcc.
    $corner = $out->colorAt(20, 20);

    expect($corner->blue()->value())->toBeGreaterThan($corner->red()->value())
        ->and($corner->blue()->value())->toBeGreaterThan(0xCC);
});

it('mirrors the blurred background so the bottom reads like the top', function () use (&$tempFiles) {
    $source = createTwoToneImage(1200, 400, 'ffffff', '000000'); // light top, dark bottom
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->fitToCanvas($source, 1080, 1920);
    $tempFiles[] = $result;

    $out = (new ImageManager(Driver::class))->decodePath($result);

    // The top half is mirrored onto the bottom, so the bottom band shows the
    // image's light top — not the dark bottom a plain cover would surface.
    expect($out->colorAt(540, 1900)->red()->value())->toBeGreaterThan(150);
})->skip(! extension_loaded('imagick'), 'Blurred-background mirror requires ext-imagick');

it('produces a valid canvas through the gd fallback for hosts without imagick', function () use (&$tempFiles) {
    $source = createTestImage(1200, 400, 'image/jpeg', '3366cc');
    $tempFiles[] = $source;

    // The GD fallback never runs when imagick is loaded (as it is in CI), so
    // exercise it directly to guard against it breaking undetected.
    $method = new ReflectionMethod(MediaOptimizer::class, 'fitOntoBlurredBackgroundGd');
    $method->setAccessible(true);
    $canvas = $method->invoke(new MediaOptimizer, $source, 1080, 1920);

    expect($canvas->width())->toBe(1080)
        ->and($canvas->height())->toBe(1920);
});

it('scales an already-9:16 image down to the canvas with no letterbox band', function () use (&$tempFiles) {
    $source = createTestImage(2160, 3840, 'image/jpeg', 'ff0000'); // already 9:16, larger than canvas
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->fitToCanvas($source, 1080, 1920);
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $out = $manager->decodePath($result);

    // Scaled down to the exact canvas, and no darkened band was added: a corner
    // (background region for the fit path) matches the centre.
    $corner = $out->colorAt(0, 0);
    $center = $out->colorAt(540, 960);

    expect($out->width())->toBe(1080)
        ->and($out->height())->toBe(1920)
        ->and(abs($corner->red()->value() - $center->red()->value()))->toBeLessThan(10);
});

it('throws when the source bytes are not a decodable image', function () use (&$tempFiles) {
    $bad = tempnam(sys_get_temp_dir(), 'bad_fit_');
    file_put_contents($bad, '<html>404 not found</html>');
    $tempFiles[] = $bad;

    $optimizer = new MediaOptimizer;

    expect(fn () => $optimizer->fitToCanvas($bad, 1080, 1920))->toThrow(Exception::class);
});

it('refuses to fit an image whose dimensions exceed the memory budget', function () use (&$tempFiles) {
    $huge = createHugeHeaderImage(20000, 20000);
    $tempFiles[] = $huge;

    $optimizer = new MediaOptimizer;

    expect(fn () => $optimizer->fitToCanvas($huge, 1080, 1920))
        ->toThrow(RuntimeException::class, 'exceed the safe processing budget');
});

it('refuses to crop an image whose dimensions exceed the memory budget', function () use (&$tempFiles) {
    $huge = createHugeHeaderImage(20000, 20000);
    $tempFiles[] = $huge;

    $optimizer = new MediaOptimizer;

    expect(fn () => $optimizer->cropToAspectRatio($huge, 0.8))
        ->toThrow(RuntimeException::class, 'exceed the safe processing budget');
});

it('optimizes an oversized asset image down to the profile width and byte budget', function () use (&$tempFiles) {
    $source = createTestImage(4000, 3000, 'image/jpeg', 'aabbcc');
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeForCollection($source, 'assets');
    $tempFiles[] = $result;

    expect($result)->not->toBeNull();

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBeLessThanOrEqual(config('trypost.media.upload_optimization.assets.max_width'))
        ->and(filesize($result))->toBeLessThanOrEqual(config('trypost.media.upload_optimization.assets.max_bytes'));

    $bytes = file_get_contents($result);
    expect(ord($bytes[0]))->toBe(0xFF)
        ->and(ord($bytes[1]))->toBe(0xD8); // JPEG SOI
});

it('does not upscale an asset image smaller than the profile width', function () use (&$tempFiles) {
    $source = createTestImage(800, 600, 'image/jpeg');
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeForCollection($source, 'assets');
    $tempFiles[] = $result;

    $manager = new ImageManager(Driver::class);
    $optimized = $manager->decodePath($result);

    expect($optimized->width())->toBe(800);
});

it('stores an in-budget jpeg byte-for-byte without re-encoding', function () use (&$tempFiles) {
    // A source already JPEG, within the width cap and under the byte budget is
    // never re-encoded — its bytes are preserved exactly, for every collection.
    $source = createNoisyJpeg(1200, 900);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;

    $assetResult = $optimizer->optimizeForCollection($source, 'assets');
    $tempFiles[] = $assetResult;
    $referenceResult = $optimizer->optimizeForCollection($source, 'brand_references');
    $tempFiles[] = $referenceResult;

    expect(file_get_contents($assetResult))->toBe(file_get_contents($source))
        ->and(file_get_contents($referenceResult))->toBe(file_get_contents($source));
});

it('returns null for a collection without an optimization profile', function () use (&$tempFiles) {
    $source = createTestImage(3000, 2000);
    $tempFiles[] = $source;

    $optimizer = new MediaOptimizer;

    expect($optimizer->optimizeForCollection($source, 'logo'))->toBeNull()
        ->and($optimizer->optimizeForCollection($source, 'avatar'))->toBeNull();
});

it('stores a huge-dimension asset image as-is rather than exhausting memory', function () use (&$tempFiles) {
    $huge = createHugeHeaderImage(20000, 20000);
    $tempFiles[] = $huge;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->optimizeForCollection($huge, 'assets');
    $tempFiles[] = $result;

    expect($result)->not->toBeNull()
        ->and(file_exists($result))->toBeTrue()
        ->and(filesize($result))->toBe(filesize($huge));
});

it('reports RAW decode capability from the imagick extension', function () {
    expect((new MediaOptimizer)->canDecodeRaw())->toBe(extension_loaded('imagick'));
});

it('reports embedded-preview capability from the exiftool binary', function () {
    $hasExiftool = (new ExecutableFinder)->find('exiftool') !== null;

    expect((new MediaOptimizer)->canExtractRawPreview())->toBe($hasExiftool);
});

it('refuses to render RAW when the imagick extension is unavailable', function () use (&$tempFiles) {
    $source = createTestImage(100, 100);
    $tempFiles[] = $source;

    expect(fn () => (new MediaOptimizer)->renderRawToJpeg($source))
        ->toThrow(RuntimeException::class, 'imagick extension is not available');
})->skip(extension_loaded('imagick'), 'Runs only on hosts without ext-imagick.');

it('surfaces a clear error when the source is not decodable RAW', function () use (&$tempFiles) {
    // The garbage bytes are named with no extension, so this also proves the
    // decoder does not silently succeed on a non-RAW file via format sniffing.
    $bad = tempnam(sys_get_temp_dir(), 'not_raw_');
    file_put_contents($bad, 'this is definitely not a camera raw file');
    $tempFiles[] = $bad;

    expect(fn () => (new MediaOptimizer)->renderRawToJpeg($bad))
        ->toThrow(RuntimeException::class, 'Failed to decode RAW image');
})->skip(! extension_loaded('imagick'), 'RAW decoding requires ext-imagick.');

it('renders a real RAW file to a JPEG regardless of filename, using the bright embedded preview', function () use (&$tempFiles) {
    // Regression guards, both proven against a real Pixel DNG:
    //  1. Assembled chunk uploads land in an EXTENSIONLESS temp file, so the
    //     converter must not rely on the filename to pick the decoder.
    //  2. Computational RAW (Pixel HDR+) stores dark linear sensor data; a plain
    //     demosaic comes out ~13% brightness and colour-shifted. renderRawToJpeg
    //     must prefer the camera-rendered embedded preview, which is bright and
    //     correct. We assert brightness well above the dark-demosaic floor.
    // Needs a real camera RAW fixture — synthetic DNGs are not decodable.
    $fixture = __DIR__.'/../../../fixtures/sample.dng';

    if (! is_file($fixture)) {
        $this->markTestSkipped('Provide tests/fixtures/sample.dng (a small real camera RAW) to run this.');
    }

    $extensionless = tempnam(sys_get_temp_dir(), 'raw_noext_');
    copy($fixture, $extensionless);
    $tempFiles[] = $extensionless;

    $optimizer = new MediaOptimizer;
    $result = $optimizer->renderRawToJpeg($extensionless, maxWidth: 2048);
    $tempFiles[] = $result;

    $bytes = file_get_contents($result);
    $decoded = (new Imagick);
    $decoded->readImageBlob($bytes);
    $brightness = ($decoded->getImageChannelMean(Imagick::CHANNEL_ALL)['mean'] ?? 0) / 65535;

    expect(ord($bytes[0]))->toBe(0xFF)
        ->and(ord($bytes[1]))->toBe(0xD8) // JPEG SOI
        ->and($decoded->getImageWidth())->toBeLessThanOrEqual(2048)
        ->and($brightness)->toBeGreaterThan(0.20); // preview-bright, not dark demosaic
})->skip(! extension_loaded('imagick'), 'RAW rendering requires ext-imagick.');
