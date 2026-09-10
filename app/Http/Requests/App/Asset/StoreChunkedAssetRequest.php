<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Asset;

use App\Enums\Media\Type as MediaType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a chunked upload request. The chunk metadata (offset / total
 * size / filename) is encoded in the `Content-Range` and `X-File-Name`
 * headers, not in the body, so we lift it into the request bag via
 * `prepareForValidation` and then run standard rules against it.
 */
class StoreChunkedAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $parsed = sscanf((string) $this->header('Content-Range'), 'bytes %d-%d/%d') ?: [];

        $this->merge([
            'range_start' => $parsed[0] ?? null,
            'range_end' => $parsed[1] ?? null,
            'total_size' => $parsed[2] ?? null,
            'file_name' => strtolower(rawurldecode((string) $this->header('X-File-Name', 'upload'))),
            'upload_id' => $this->header('X-Upload-Id'),
            'collection' => (string) $this->header('X-Collection', 'assets'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $isReference = $this->input('collection') === 'brand_references';

        $types = $isReference ? [MediaType::Image] : [MediaType::Image, MediaType::Video, MediaType::Document];
        $allowedSuffixes = collect($types)
            ->flatMap(fn (MediaType $type) => $type->extensions())
            ->map(fn (string $ext) => '.'.$ext)
            ->all();

        $maxBytes = $isReference ? MediaType::Image->maxSizeInBytes() : MediaType::Video->maxSizeInBytes();

        return [
            'range_start' => ['required', 'integer', 'min:0'],
            'range_end' => ['required', 'integer', 'gte:range_start'],
            'total_size' => ['required', 'integer', 'min:1', 'max:'.$maxBytes],
            'file_name' => ['required', 'string', 'ends_with:'.implode(',', $allowedSuffixes)],
            'upload_id' => ['required', 'string', 'uuid'],
            'collection' => ['required', 'string', 'in:assets,brand_references'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $max = $this->input('collection') === 'brand_references'
            ? MediaType::Image->maxSizeInMb()
            : MediaType::Video->maxSizeInMb();

        return [
            'total_size.max' => __('assets.upload.file_too_large', ['max' => $max]),
        ];
    }
}
