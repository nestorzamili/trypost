<?php

declare(strict_types=1);

use App\Enums\Media\Type;

test('media type has correct values', function () {
    expect(Type::Image->value)->toBe('image');
    expect(Type::Video->value)->toBe('video');
    expect(Type::Document->value)->toBe('document');
});

test('media type has labels', function () {
    expect(Type::Image->label())->toBe('Imagem');
    expect(Type::Video->label())->toBe('Vídeo');
    expect(Type::Document->label())->toBe('Documento');
});

test('media type has allowed mime types', function () {
    expect(Type::Image->allowedMimeTypes())->toContain('image/jpeg', 'image/png');
    expect(Type::Video->allowedMimeTypes())->toContain('video/mp4', 'video/quicktime');
    expect(Type::Document->allowedMimeTypes())->toBe(['application/pdf']);
});

test('media type document accepts only the pdf extension', function () {
    expect(Type::Document->extensions())->toBe(['pdf']);
});

test('media type max size in mb is read from config', function () {
    config(['trypost.media.max_size_mb.image' => 10]);
    config(['trypost.media.max_size_mb.video' => 1024]);

    expect(Type::Image->maxSizeInMb())->toBe(10);
    expect(Type::Video->maxSizeInMb())->toBe(1024);
});

test('media type exposes derived size units', function () {
    config(['trypost.media.max_size_mb.video' => 1024]);

    expect(Type::Video->maxSizeInKb())->toBe(1024 * 1024);
    expect(Type::Video->maxSizeInBytes())->toBe(1024 * 1024 * 1024);
});

test('media type resolves from mime', function () {
    expect(Type::fromMime('image/jpeg'))->toBe(Type::Image);
    expect(Type::fromMime('video/mp4'))->toBe(Type::Video);
    expect(Type::fromMime('application/pdf'))->toBe(Type::Document);
    expect(Type::fromMime('not-a-mime'))->toBeNull();
});

test('media type document max size in mb is read from config', function () {
    config(['trypost.media.max_size_mb.document' => 100]);

    expect(Type::Document->maxSizeInMb())->toBe(100);
});

test('classify resolves the type from any matching mime, broadly', function () {
    expect(Type::classify('image/jpeg'))->toBe(Type::Image);
    expect(Type::classify('image/heic'))->toBe(Type::Image); // not in the upload allow-list, but still an image
    expect(Type::classify('video/quicktime'))->toBe(Type::Video);
    expect(Type::classify('video/x-msvideo'))->toBe(Type::Video); // legacy avi, still a video
    expect(Type::classify('application/pdf'))->toBe(Type::Document);
    expect(Type::classify('application/zip'))->toBeNull();
});

test('classify falls back to the file extension when the mime is missing', function () {
    expect(Type::classify(null, 'photo.PNG'))->toBe(Type::Image);
    expect(Type::classify(null, 'clip.mkv'))->toBe(Type::Video);
    expect(Type::classify(null, 'deck.pdf'))->toBe(Type::Document);
    expect(Type::classify(null, 'archive.zip'))->toBeNull();
    expect(Type::classify(null, null))->toBeNull();
});

test('classify prefers the mime over the extension', function () {
    // A mismatched extension never overrides a present, recognized mime.
    expect(Type::classify('video/mp4', 'thing.png'))->toBe(Type::Video);
    // A present but unrecognized mime resolves to null without consulting the extension.
    expect(Type::classify('application/zip', 'clip.mp4'))->toBeNull();
});

test('fromExtension classifies broadly and is case-insensitive', function () {
    expect(Type::fromExtension('JPG'))->toBe(Type::Image);
    expect(Type::fromExtension('webm'))->toBe(Type::Video);
    expect(Type::fromExtension('pdf'))->toBe(Type::Document);
    expect(Type::fromExtension('txt'))->toBeNull();
    expect(Type::fromExtension(null))->toBeNull();
});

test('fromExtension covers every legacy image and video extension', function () {
    foreach (['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif'] as $ext) {
        expect(Type::fromExtension($ext))->toBe(Type::Image, "expected {$ext} to be an image");
    }

    foreach (['mp4', 'mov', 'avi', 'wmv', 'webm', 'mkv', 'm4v'] as $ext) {
        expect(Type::fromExtension($ext))->toBe(Type::Video, "expected {$ext} to be a video");
    }
});

test('isGif only matches the gif mime', function () {
    expect(Type::isGif('image/gif'))->toBeTrue();
    expect(Type::isGif('image/png'))->toBeFalse();
    expect(Type::isGif(null))->toBeFalse();
});

test('image allow-list and extensions include camera RAW formats', function () {
    expect(Type::Image->allowedMimeTypes())->toContain('image/x-adobe-dng', 'image/x-canon-cr2', 'image/x-nikon-nef');

    foreach (['dng', 'cr2', 'cr3', 'nef', 'arw', 'rw2', 'orf', 'raf', 'pef', 'srw'] as $ext) {
        expect(Type::Image->extensions())->toContain($ext);
    }
});

test('isRaw matches RAW by mime or bare extension only', function () {
    expect(Type::isRaw('image/x-adobe-dng'))->toBeTrue()
        ->and(Type::isRaw(null, 'CR2'))->toBeTrue()
        ->and(Type::isRaw(null, 'dng'))->toBeTrue()
        ->and(Type::isRaw('image/jpeg', 'jpg'))->toBeFalse()
        ->and(Type::isRaw(null, 'png'))->toBeFalse()
        ->and(Type::isRaw(null, null))->toBeFalse();
});

test('classify resolves a RAW upload reported as octet-stream to an image', function () {
    // Most RAW files arrive with a generic OS-reported MIME; the extension
    // disambiguates them into an image.
    expect(Type::classify('application/octet-stream', 'shot.dng'))->toBe(Type::Image)
        ->and(Type::classify('application/octet-stream', 'shot.nef'))->toBe(Type::Image)
        ->and(Type::classify('image/x-adobe-dng', 'shot.dng'))->toBe(Type::Image);
});

test('classify still returns null for a concrete unsupported mime even with a known extension', function () {
    expect(Type::classify('application/zip', 'shot.dng'))->toBeNull();
});

test('fromExtension classifies every RAW extension as an image', function () {
    foreach (Type::RAW_EXTENSIONS as $ext) {
        expect(Type::fromExtension($ext))->toBe(Type::Image, "expected {$ext} to be an image");
        expect(Type::fromExtension(strtoupper($ext)))->toBe(Type::Image, "expected {$ext} upper to be an image");
    }
});
