<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

beforeEach(function () {
    // Fake the default ('public') disk that HasMedia writes to. Faking it fresh
    // each test resets the backing directory, so stored files from a prior test
    // in the same process can't leak in — the source of the intermittent
    // Storage::assertExists flake when this file ran alongside others.
    Storage::fake('public');
});

test('model can get media relationship', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->media())->toBeInstanceOf(MorphMany::class);
});

test('model can add media from uploaded file', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

    $media = $workspace->addMedia($file, 'logo');

    expect($media)->toBeInstanceOf(Media::class);
    expect($media->collection)->toBe('logo');
    expect($media->mime_type)->toBe('image/jpeg');
    expect($media->original_filename)->toBe('logo.jpg');
    Storage::assertExists($media->path);
});

test('model can get media by collection after adding', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $workspace->addMedia($file, 'logo');

    expect($workspace->getMedia('logo')->count())->toBe(1);
});

test('model can get first media from collection', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $media = $workspace->addMedia($file, 'logo');

    expect($workspace->getFirstMedia('logo')->id)->toBe($media->id);
});

test('get first media returns null when no media exists', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->getFirstMedia('logo'))->toBeNull();
});

test('model can get first media url', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);
    $workspace->addMedia($file, 'logo');

    $url = $workspace->getFirstMediaUrl('logo');

    expect($url)->not->toBeNull();
});

test('get first media url returns default when no media exists', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->getFirstMediaUrl('logo', 'default-url'))->toBe('default-url');
});

test('get fallback avatar url returns dicebear url', function () {
    $user = User::factory()->create(['name' => 'John Doe']);

    $url = $user->getFallbackAvatarUrl('John Doe');

    expect($url)->toContain('api.dicebear.com');
    expect($url)->toContain('John+Doe');
});

test('adding media to single collection clears existing media', function () {
    $workspace = Workspace::factory()->create();
    $file1 = UploadedFile::fake()->image('logo1.jpg', 100, 100);
    $file2 = UploadedFile::fake()->image('logo2.jpg', 100, 100);

    $media1 = $workspace->addMedia($file1, 'logo');
    $media2 = $workspace->addMedia($file2, 'logo');

    expect($workspace->getMedia('logo')->count())->toBe(1);
    expect($workspace->getFirstMedia('logo')->id)->toBe($media2->id);
    expect(Media::find($media1->id))->toBeNull();
});

test('adding media to multiple collection does not clear existing', function () {
    $workspace = Workspace::factory()->create();
    $file1 = UploadedFile::fake()->image('image1.jpg', 100, 100);
    $file2 = UploadedFile::fake()->image('image2.jpg', 100, 100);

    $workspace->addMedia($file1, 'assets');
    $workspace->addMedia($file2, 'assets');

    expect($workspace->getMedia('assets')->count())->toBe(2);
});

test('model can add media from file path', function () {
    $workspace = Workspace::factory()->create();

    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, file_get_contents(__DIR__.'/../../fixtures/1x1.png'));

    $media = $workspace->addMediaFromPath($tempFile, 'uploaded.png', 'logo');

    expect($media)->toBeInstanceOf(Media::class);
    expect($media->original_filename)->toBe('uploaded.png');
    Storage::assertExists($media->path);

    unlink($tempFile);
});

test('model can add video media from file path via stream', function () {
    $workspace = Workspace::factory()->create();

    $tempFile = tempnam(sys_get_temp_dir(), 'vid');
    $bytes = "\0\0\0\x18ftypmp42\0\0\0\0mp42isom".str_repeat("\0", 64);
    file_put_contents($tempFile, $bytes);

    $media = $workspace->addMediaFromPath($tempFile, 'clip.mp4', 'assets');

    expect($media->type->value)->toBe('video');
    expect($media->size)->toBe(strlen($bytes));
    expect($media->mime_type)->toBe('video/mp4');
    Storage::assertExists($media->path);

    unlink($tempFile);
});

test('model can clear media collection', function () {
    $workspace = Workspace::factory()->create();
    $file1 = UploadedFile::fake()->image('logo1.jpg', 100, 100);
    $file2 = UploadedFile::fake()->image('logo2.jpg', 100, 100);
    $file3 = UploadedFile::fake()->image('logo3.jpg', 100, 100);

    // Add to 'logo' collection (single, will only keep last one)
    $workspace->addMedia($file1, 'logo');

    // Need to use a 'multiple' collection model
    $ws = Workspace::factory()->create();
    $ws->addMedia($file2, 'assets');
    $ws->addMedia($file3, 'assets');

    expect($ws->getMedia('assets')->count())->toBe(2);

    $ws->clearMediaCollection('assets');

    expect($ws->getMedia('assets')->count())->toBe(0);
});

test('is single media collection returns true for single collections', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->isSingleMediaCollection('logo'))->toBeTrue();
});

test('is single media collection returns false for multiple collections', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->isSingleMediaCollection('assets'))->toBeFalse();
});

test('is single media collection returns false for undefined collections', function () {
    $workspace = Workspace::factory()->create();

    expect($workspace->isSingleMediaCollection('undefined'))->toBeFalse();
});

test('add media detects video type', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->create('video.mp4', 1000, 'video/mp4');

    $media = $workspace->addMedia($file, 'logo');

    expect($media->type->value)->toBe('video');
});

test('add media detects document type for a pdf', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->create('deck.pdf', 1000, 'application/pdf');

    $media = $workspace->addMedia($file, 'logo');

    expect($media->type->value)->toBe('document');
});

test('add media throws on unsupported MIME type', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->create('archive.zip', 1000, 'application/zip');

    expect(fn () => $workspace->addMedia($file, 'logo'))
        ->toThrow(InvalidArgumentException::class);
});

test('add media includes custom meta', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg', 100, 100);

    $media = $workspace->addMedia($file, 'logo', ['custom_key' => 'custom_value']);

    expect($media->meta)->toHaveKey('custom_key');
    expect($media->meta['custom_key'])->toBe('custom_value');
});

test('add media from path detects image dimensions', function () {
    $workspace = Workspace::factory()->create();

    // Create a real image file
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    $image = imagecreatetruecolor(200, 150);
    imagejpeg($image, $tempFile);
    imagedestroy($image);

    $media = $workspace->addMediaFromPath($tempFile, 'test.jpg', 'logo');

    expect($media->meta)->toHaveKey('width');
    expect($media->meta)->toHaveKey('height');
    expect($media->meta['width'])->toBe(200);
    expect($media->meta['height'])->toBe(150);

    unlink($tempFile);
});

test('user has_photo returns false when no media', function () {
    $user = User::factory()->create(['name' => 'Test User']);

    expect($user->has_photo)->toBeFalse();
    expect($user->photo_url)->toBeNull();
});

test('user has_photo returns true when avatar exists', function () {
    $user = User::factory()->create(['name' => 'Test User']);
    $file = UploadedFile::fake()->image('avatar.jpg', 100, 100);
    $user->addMedia($file, 'avatar');

    $user->refresh();

    expect($user->has_photo)->toBeTrue();
    expect($user->photo_url)->not->toBeNull();
});

test('isVideo detects mp4 files', function () {
    $media = new Media(['path' => 'medias/test.mp4']);
    expect($media->isVideo())->toBeTrue();
    expect($media->isImage())->toBeFalse();
});

test('isVideo detects mov files', function () {
    $media = new Media(['path' => 'medias/test.mov']);
    expect($media->isVideo())->toBeTrue();
});

test('isImage detects jpg files', function () {
    $media = new Media(['path' => 'medias/test.jpg']);
    expect($media->isImage())->toBeTrue();
    expect($media->isVideo())->toBeFalse();
});

test('isImage detects png files', function () {
    $media = new Media(['path' => 'medias/test.png']);
    expect($media->isImage())->toBeTrue();
});

test('isVideo returns false for image files', function () {
    $media = new Media(['path' => 'medias/test.webp']);
    expect($media->isVideo())->toBeFalse();
    expect($media->isImage())->toBeTrue();
});

test('mp4 with quicktime mime still detected as video by path', function () {
    $media = new Media(['path' => 'medias/test.mp4', 'mime_type' => 'video/quicktime']);
    expect($media->isVideo())->toBeTrue();
});

test('PNG upload is converted to JPEG at upload time', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('photo.png', 200, 150);

    $media = $workspace->addMedia($file, 'assets');

    expect($media->mime_type)->toBe('image/jpeg')
        ->and($media->path)->toEndWith('.jpg')
        ->and(pathinfo($media->path, PATHINFO_EXTENSION))->toBe('jpg');

    $bytes = Storage::get($media->path);
    expect(substr($bytes, 0, 3))->toBe("\xFF\xD8\xFF"); // JPEG SOI marker
});

test('JPEG upload stays as JPEG (no-op)', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg', 200, 150);

    $media = $workspace->addMedia($file, 'assets');

    expect($media->mime_type)->toBe('image/jpeg')
        ->and(pathinfo($media->path, PATHINFO_EXTENSION))->toBe('jpg');
});

test('GIF upload preserves GIF format for animation', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('anim.gif', 200, 150);

    $media = $workspace->addMedia($file, 'assets');

    expect($media->mime_type)->toBe('image/gif')
        ->and(pathinfo($media->path, PATHINFO_EXTENSION))->toBe('gif');
});

test('WebP upload is converted to JPEG', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('photo.webp', 200, 150);

    $media = $workspace->addMedia($file, 'assets');

    expect($media->mime_type)->toBe('image/jpeg')
        ->and(pathinfo($media->path, PATHINFO_EXTENSION))->toBe('jpg');
});

test('model can add media from stored path', function () {
    $workspace = Workspace::factory()->create();
    $content = file_get_contents(__DIR__.'/../../fixtures/1x1.png');
    Storage::put('medias/existing.png', $content);

    $media = $workspace->addMediaFromStoredPath('medias/existing.png', 'existing.png', 'image/png', strlen($content), 'assets');

    expect($media)->toBeInstanceOf(Media::class);
    expect($media->original_filename)->toBe('existing.png');
    expect($media->path)->toBe('medias/existing.png');
    expect($media->mime_type)->toBe('image/png');
    expect($media->size)->toBe(strlen($content));
});

test('add media from stored path sanitizes invalid UTF-8 bytes in the original filename', function () {
    $workspace = Workspace::factory()->create();
    $content = file_get_contents(__DIR__.'/../../fixtures/1x1.png');
    Storage::put('medias/existing.png', $content);
    $invalidName = "earnings \x97 report.png";

    $media = $workspace->addMediaFromStoredPath('medias/existing.png', $invalidName, 'image/png', strlen($content), 'assets');

    expect(mb_check_encoding($media->original_filename, 'UTF-8'))->toBeTrue();
    expect($media->original_filename)->toBe('earnings ? report.png');
});

test('add media sanitizes invalid UTF-8 bytes in the original filename', function () {
    $workspace = Workspace::factory()->create();
    $invalidName = "earnings \x97 report.jpg";
    $file = UploadedFile::fake()->image($invalidName, 100, 100);

    $media = $workspace->addMedia($file, 'assets');

    expect(mb_check_encoding($media->original_filename, 'UTF-8'))->toBeTrue();
    expect($media->original_filename)->toBe('earnings ? report.jpg');
});

test('add media from path sanitizes invalid UTF-8 bytes in the original filename', function () {
    $workspace = Workspace::factory()->create();
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, file_get_contents(__DIR__.'/../../fixtures/1x1.png'));
    $invalidName = "earnings \x97 report.png";

    $media = $workspace->addMediaFromPath($tempFile, $invalidName, 'assets');

    expect(mb_check_encoding($media->original_filename, 'UTF-8'))->toBeTrue();
    expect($media->original_filename)->toBe('earnings ? report.png');

    unlink($tempFile);
});

test('client meta is merged into media meta', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('photo.jpg', 640, 480);

    $media = $workspace->addMedia($file, 'assets', ['duration' => 12.5]);

    expect($media->meta)->toHaveKey('duration', 12.5)
        ->and($media->meta)->toHaveKey('width')
        ->and($media->meta)->toHaveKey('height');
});

function makeLargeJpeg(int $width, int $height, string $fill = 'aabbcc'): string
{
    $manager = new ImageManager(Driver::class);
    $image = $manager->createImage($width, $height)->fill($fill);
    $tempFile = tempnam(sys_get_temp_dir(), 'hasmedia_');
    file_put_contents($tempFile, (string) $image->encodeUsingMediaType('image/jpeg'));

    return $tempFile;
}

function makeNoisyJpeg(int $width, int $height): string
{
    $gd = imagecreatetruecolor($width, $height);
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            imagesetpixel($gd, $x, $y, imagecolorallocate($gd, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
    }
    $tempFile = tempnam(sys_get_temp_dir(), 'hasmedia_noisy_');
    imagejpeg($gd, $tempFile, 100);
    imagedestroy($gd);

    return $tempFile;
}

test('large asset image is downscaled to the profile width on addMediaFromPath', function () {
    $workspace = Workspace::factory()->create();
    $temp = makeLargeJpeg(4000, 3000);

    try {
        $media = $workspace->addMediaFromPath($temp, 'photo.jpg', 'assets', mimeType: 'image/jpeg');
    } finally {
        @unlink($temp);
    }

    $maxWidth = config('trypost.media.upload_optimization.assets.max_width');
    $maxBytes = config('trypost.media.upload_optimization.assets.max_bytes');

    expect($media->mime_type)->toBe('image/jpeg')
        ->and($media->meta['width'])->toBeLessThanOrEqual($maxWidth)
        ->and($media->size)->toBeLessThanOrEqual($maxBytes);
});

test('large logo image is NOT resized (collection has no optimization profile)', function () {
    $workspace = Workspace::factory()->create();
    $temp = makeLargeJpeg(4000, 3000);

    try {
        $media = $workspace->addMediaFromPath($temp, 'logo.jpg', 'logo', mimeType: 'image/jpeg');
    } finally {
        @unlink($temp);
    }

    expect($media->meta['width'])->toBe(4000);
});

test('an in-budget jpeg is stored losslessly for both assets and brand references', function () {
    $workspace = Workspace::factory()->create();

    $source = makeNoisyJpeg(1200, 900);
    $sourceBytes = file_get_contents($source);
    $assetTemp = tempnam(sys_get_temp_dir(), 'hasmedia_a_');
    $referenceTemp = tempnam(sys_get_temp_dir(), 'hasmedia_r_');
    copy($source, $assetTemp);
    copy($source, $referenceTemp);

    try {
        $asset = $workspace->addMediaFromPath($assetTemp, 'a.jpg', 'assets', mimeType: 'image/jpeg');
        $reference = $workspace->addMediaFromPath($referenceTemp, 'r.jpg', 'brand_references', mimeType: 'image/jpeg');
    } finally {
        @unlink($source);
        @unlink($assetTemp);
        @unlink($referenceTemp);
    }

    // Neither collection re-encodes an already-in-budget JPEG: the stored bytes
    // match the source exactly, so no generational quality loss occurs.
    expect($asset->size)->toBe(strlen($sourceBytes))
        ->and($reference->size)->toBe(strlen($sourceBytes))
        ->and(Storage::get($asset->path))->toBe($sourceBytes)
        ->and(Storage::get($reference->path))->toBe($sourceBytes);
});

test('GIF in assets is preserved as GIF (never optimized)', function () {
    $workspace = Workspace::factory()->create();
    $file = UploadedFile::fake()->image('anim.gif', 200, 150);

    $media = $workspace->addMedia($file, 'assets');

    expect($media->mime_type)->toBe('image/gif')
        ->and(pathinfo($media->path, PATHINFO_EXTENSION))->toBe('gif');
});

test('large PNG in assets becomes JPEG and is downscaled', function () {
    $workspace = Workspace::factory()->create();

    $manager = new ImageManager(Driver::class);
    $image = $manager->createImage(3000, 2000)->fill('cc8844');
    $temp = tempnam(sys_get_temp_dir(), 'hasmedia_png_');
    file_put_contents($temp, (string) $image->encodeUsingMediaType('image/png'));

    try {
        $media = $workspace->addMediaFromPath($temp, 'photo.png', 'assets', mimeType: 'image/png');
    } finally {
        @unlink($temp);
    }

    expect($media->mime_type)->toBe('image/jpeg')
        ->and(pathinfo($media->path, PATHINFO_EXTENSION))->toBe('jpg')
        ->and($media->meta['width'])->toBeLessThanOrEqual(config('trypost.media.upload_optimization.assets.max_width'));
});
