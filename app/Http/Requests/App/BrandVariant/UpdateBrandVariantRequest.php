<?php

declare(strict_types=1);

namespace App\Http\Requests\App\BrandVariant;

use App\Enums\Workspace\BrandFont;
use App\Models\BrandVariant;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBrandVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;

        return $workspace instanceof Workspace && $this->user()->can('update', $workspace);
    }

    public function rules(): array
    {
        $workspaceId = $this->user()?->currentWorkspace?->id;
        $brandVariant = $this->route('brandVariant');
        $hex = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];
        $language = ['required', 'string', Rule::in(['en', 'zh'])];

        if ($workspaceId !== null) {
            $language[] = Rule::unique('brand_variants', 'language_code')
                ->where(fn ($query) => $query->where('workspace_id', $workspaceId))
                ->ignore($brandVariant instanceof BrandVariant ? $brandVariant->getKey() : $brandVariant);
        }

        return [
            'language_code' => $language,
            'label' => ['required', 'string', 'max:100'],
            'colors' => ['nullable', 'array', 'max:20'],
            'colors.*' => ['required', 'string', 'max:100', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'brand_color' => $hex,
            'background_color' => $hex,
            'text_color' => $hex,
            'headline_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'body_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'label_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'accent_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'visual_notes' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $colors = $this->input('colors');

        if (! is_array($colors)) {
            return;
        }

        $normalized = [];
        $duplicate = false;
        foreach ($colors as $name => $hex) {
            $normalizedName = is_string($name) ? trim($name) : $name;
            if (is_string($normalizedName) && array_key_exists($normalizedName, $normalized)) {
                $duplicate = true;
            }
            $normalized[$normalizedName] = is_string($hex) ? trim($hex) : $hex;
        }

        $this->merge(['colors' => $normalized, '_duplicate_color_names' => $duplicate]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $colors = $this->input('colors');
            if (! is_array($colors)) {
                return;
            }

            if ($this->boolean('_duplicate_color_names')) {
                $validator->errors()->add('colors', 'Color names must be unique.');
            }

            foreach (array_keys($colors) as $name) {
                if (! is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 100) {
                    $validator->errors()->add('colors', 'Color names must be non-empty strings of 100 characters or fewer.');
                }
            }
        }];
    }
}
