<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Brand;

use App\Enums\Media\BrandReferenceKind;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBrandReferenceFromAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace instanceof Workspace && $this->user()->can('update', $workspace);
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'uuid', 'exists:medias,id'],
            'label' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', 'string', Rule::enum(BrandReferenceKind::class)],
        ];
    }
}
