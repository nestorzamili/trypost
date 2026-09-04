<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Ai;

use Illuminate\Foundation\Http\FormRequest;

class RegeneratePostCaptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'instruction' => ['nullable', 'string', 'max:1000'],
            'regeneration_id' => ['required', 'uuid'],
        ];
    }
}
