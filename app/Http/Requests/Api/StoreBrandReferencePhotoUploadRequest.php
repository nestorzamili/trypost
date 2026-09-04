<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Enums\Media\Type as MediaType;
use Illuminate\Foundation\Http\FormRequest;

class StoreBrandReferencePhotoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'media' => [
                'required',
                'file',
                'max:'.MediaType::Image->maxSizeInKb(),
                'mimetypes:'.implode(',', MediaType::Image->allowedMimeTypes()),
            ],
        ];
    }
}
