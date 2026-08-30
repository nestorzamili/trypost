<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Brand;

use App\Enums\Media\Type as MediaType;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

class StoreBrandReferencePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace instanceof Workspace && $this->user()->can('update', $workspace);
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'max:'.MediaType::Image->maxSizeInKb(),
                'mimetypes:'.implode(',', MediaType::Image->allowedMimeTypes()),
            ],
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }
}
