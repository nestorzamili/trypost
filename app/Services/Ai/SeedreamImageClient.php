<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for BytePlus Ark Seedream image generation.
 *
 * Seedream uses a unified generate-edit architecture: text-to-image AND
 * image-to-image (single-image edit, multi-image fusion) all run through one
 * `POST /images/generations` JSON endpoint — the reference images are passed
 * as an `image` array of URLs / data-URIs, NOT as an OpenAI-style multipart
 * upload to `/images/edits` (which Seedream does not implement). laravel/ai's
 * OpenAI gateway only knows the multipart path, so Seedream is driven here
 * over plain HTTP instead of through the SDK.
 *
 * One request per call — the retry loop lives in {@see AiImageClient}. On any
 * non-2xx / unparsable response this throws so that loop can retry or fall back.
 *
 * @see https://docs.byteplus.com/en/docs/ModelArk (Seedream 4.x/5.x)
 */
class SeedreamImageClient
{
    /**
     * Generate one image from a prompt, optionally conditioned on reference
     * images (image-to-image). Returns the raw bytes plus provider/model, or
     * null when the response carried no usable image.
     *
     * @param  array<int, string>  $referenceImages  URLs or `data:image/...;base64,...` URIs
     * @return array{bytes: string, provider: string, model: string}|null
     */
    public function generate(
        string $prompt,
        string $size,
        array $referenceImages = [],
        int $timeout = 180,
    ): ?array {
        $url = rtrim((string) config('ai.providers.seedream.url'), '/');
        $key = (string) config('ai.providers.seedream.key');
        $model = (string) config('ai.providers.seedream.models.image.default');

        if ($url === '' || $key === '' || $model === '') {
            throw new RuntimeException('Seedream provider is not fully configured (url/key/model).');
        }

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
            'response_format' => 'b64_json',
            // Deterministic single-image output; Seedream ignores OpenAI's `n`.
            'sequential_image_generation' => 'disabled',
            // Default varies by version — force off for commercial output.
            'watermark' => (bool) config('ai.providers.seedream.watermark', false),
        ];

        // Reference images switch the same endpoint into image-to-image /
        // multi-image fusion. Up to 10; refer to them as "image 1/2/..." in the
        // prompt. Omitting the key entirely keeps it pure text-to-image.
        $references = array_values(array_filter(
            $referenceImages,
            fn ($ref): bool => is_string($ref) && trim($ref) !== '',
        ));

        if ($references !== []) {
            $payload['image'] = array_slice($references, 0, 10);
        }

        $response = Http::withToken($key)
            ->acceptJson()
            ->timeout($timeout)
            ->post($url.'/images/generations', $payload);

        // A non-2xx (including the empty-body responses Seedream returns for a
        // bad request) must throw so AiImageClient's retry/fallback engages,
        // rather than being read as "no image".
        $response->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Seedream returned a non-JSON response body.');
        }

        $b64 = data_get($data, 'data.0.b64_json');

        if (! is_string($b64) || $b64 === '') {
            return null;
        }

        $bytes = base64_decode($b64, true);

        if ($bytes === false || $bytes === '') {
            return null;
        }

        return [
            'bytes' => $bytes,
            'provider' => 'seedream',
            'model' => $model,
        ];
    }
}
