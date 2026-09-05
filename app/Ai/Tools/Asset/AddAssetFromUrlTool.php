<?php

declare(strict_types=1);

namespace App\Ai\Tools\Asset;

use App\Ai\Tools\WorkspaceWriteTool;
use App\Enums\Media\Type as MediaType;
use App\Services\Brand\SafeHttpFetcher;
use App\Services\UnsplashService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * Chat has no file upload, so library additions arrive by URL — the same
 * shape as AssetController::storeFromUrl(), including its host allowlist
 * (Unsplash and Giphy only) and its Unsplash download tracking. The rules
 * are mirrored rather than reused because a FormRequest has no meaning
 * inside a tool; if the controller's rules change, this description and the
 * rules below must change with them. Every outbound fetch goes through
 * {@see SafeHttpFetcher} for the SSRF guard.
 */
class AddAssetFromUrlTool extends WorkspaceWriteTool
{
    public function name(): string
    {
        return 'add_asset_from_url';
    }

    public function description(): Stringable|string
    {
        return 'Add a file to the Asset Library by downloading it from a link. Only Unsplash (images.unsplash.com) and Giphy (media.giphy.com) links work — anything else is refused — and only images, videos and PDFs are accepted. Pass the filename to store it under and, for Unsplash images, the download_location the Unsplash listing gave you so the download is attributed.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required()->description('The https Unsplash or Giphy image URL.'),
            'filename' => $schema->string()->required()->description('The filename to store it under, e.g. campaign-hero.jpg.'),
            'download_location' => $schema->string()->description('Optional Unsplash download attribution URL.'),
        ];
    }

    protected function run(Request $request): string
    {
        $validator = Validator::make($request->toArray(), [
            'url' => ['required', 'url', 'regex:/^https:\/\/(images\.unsplash\.com|media[0-9]*\.giphy\.com)\//'],
            'filename' => ['required', 'string', 'max:255'],
            'download_location' => ['nullable', 'url', 'regex:/^https:\/\/api\.unsplash\.com\//'],
        ], [
            'url.regex' => 'Only Unsplash (images.unsplash.com) and Giphy (media.giphy.com) links can be added to the library.',
        ]);

        if ($validator->fails()) {
            return $this->error((string) $validator->errors()->first());
        }

        $validated = $validator->validated();

        if ($downloadLocation = data_get($validated, 'download_location')) {
            app(UnsplashService::class)->trackDownload($downloadLocation);
        }

        $url = data_get($validated, 'url');

        try {
            $response = app(SafeHttpFetcher::class)->guardedRequest($url)->timeout(30)->get($url);
        } catch (RuntimeException) {
            return $this->error('Failed to download image from URL');
        }

        if ($response->failed()) {
            return $this->error('Failed to download image from URL');
        }

        // Stripped of parameters (`image/jpeg; charset=binary` is stored as
        // `image/jpeg`) and checked against the same allow-list uploads go
        // through — a bare default plus no check would file a 200 HTML error
        // page from an allowlisted host as an image.
        $mimeType = strtok($response->header('Content-Type', ''), ';') ?: '';

        $type = MediaType::fromMime($mimeType);

        if ($type === null) {
            return $this->error('Only image, video or PDF links can be added to the library.');
        }

        $extension = match (true) {
            str_contains($mimeType, 'png') => 'png',
            str_contains($mimeType, 'gif') => 'gif',
            str_contains($mimeType, 'webp') => 'webp',
            str_contains($mimeType, 'mp4') => 'mp4',
            str_contains($mimeType, 'quicktime') => 'mov',
            str_contains($mimeType, 'pdf') => 'pdf',
            default => $type->extensions()[0],
        };

        $filename = Str::uuid().'.'.$extension;
        $path = "medias/{$filename}";

        Storage::put($path, $response->body());

        $media = $this->workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'assets',
            'type' => $type->value,
            'path' => $path,
            'original_filename' => data_get($validated, 'filename'),
            'mime_type' => $mimeType,
            'size' => strlen($response->body()),
            'order' => 0,
            'meta' => [],
        ]);

        return $this->json(['data' => [
            'id' => $media->id,
            'original_filename' => $media->original_filename,
            'type' => $media->type->value,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->url,
        ]]);
    }
}
