<?php

declare(strict_types=1);

namespace App\Ai\Tools\Brand;

use App\Ai\Tools\WorkspaceAdminTool;
use App\Enums\Media\Type as MediaType;
use App\Services\Brand\SafeHttpFetcher;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

/**
 * Chat has no file upload, so reference photos arrive by URL — the same
 * download-then-register shape as AssetController::storeFromUrl(), but for
 * the `brand_references` collection and restricted to images (anything else
 * would fail brand-visual generation downstream). Every outbound fetch goes
 * through {@see SafeHttpFetcher} for the SSRF guard.
 */
class AddBrandReferenceFromUrlTool extends WorkspaceAdminTool
{
    public function name(): string
    {
        return 'add_brand_reference_from_url';
    }

    public function description(): Stringable|string
    {
        return 'Add a brand reference photo to the current workspace by downloading it from a URL. Only image URLs work. The photo guides AI-generated brand visuals from then on.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()->required()->description('The https URL of the image to add.'),
            'label' => $schema->string()->description('Optional short label describing what the photo shows, e.g. "storefront".'),
            'filename' => $schema->string()->description('Optional filename to store it under. Defaults to the last URL segment.'),
        ];
    }

    protected function run(Request $request): string
    {
        $validator = Validator::make($request->toArray(), [
            'url' => ['required', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->error((string) $validator->errors()->first());
        }

        $url = app(SafeHttpFetcher::class)->normalizeUrl($request->string('url')->value());

        try {
            $response = app(SafeHttpFetcher::class)->get($url);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage());
        }

        $mimeType = strtok($response->header('Content-Type', ''), ';') ?: '';

        if (MediaType::classify($mimeType) !== MediaType::Image) {
            return $this->error('Only image URLs can be added as brand reference photos.');
        }

        $extension = match (true) {
            str_contains($mimeType, 'png') => 'png',
            str_contains($mimeType, 'gif') => 'gif',
            str_contains($mimeType, 'webp') => 'webp',
            default => 'jpg',
        };

        $path = 'medias/'.Str::uuid().'.'.$extension;
        Storage::put($path, $response->body());

        $meta = [];

        if ($request->filled('label')) {
            $meta['label'] = trim($request->string('label')->value());
        }

        $filename = $request->filled('filename')
            ? $request->string('filename')->value()
            : (basename((string) parse_url($url, PHP_URL_PATH)) ?: 'reference.'.$extension);

        $media = $this->workspace->media()->create([
            'group_id' => Str::uuid()->toString(),
            'collection' => 'brand_references',
            'type' => MediaType::Image->value,
            'path' => $path,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'size' => strlen($response->body()),
            'order' => 0,
            'meta' => $meta,
        ]);

        return $this->json(['data' => [
            'id' => $media->id,
            'original_filename' => $media->original_filename,
            'label' => data_get($media->meta, 'label'),
            'mime_type' => $media->mime_type,
            'url' => $media->url,
        ]]);
    }
}
