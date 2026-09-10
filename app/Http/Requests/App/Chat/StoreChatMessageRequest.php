<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Chat;

use App\Enums\Media\BrandReferenceKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A chat turn carries exactly one of two payloads: a new user message, or the
 * approval decisions that resume a run paused on a tool call. Sending both is
 * meaningless — the SDK would drop one — so they are mutually exclusive.
 */
class StoreChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:60000', 'required_without:decisions', 'prohibits:decisions'],
            'decisions' => ['nullable', 'array', 'required_without:message'],
            'decisions.*' => ['array'],
            'decisions.*.action' => ['required', Rule::in(['approve', 'reject'])],
            'decisions.*.result' => ['nullable', 'string', 'max:1000'],
            // The caller's IANA timezone (e.g. "Asia/Jakarta"), so the agent can
            // resolve relative dates like "tomorrow 10am" against the user's
            // clock rather than UTC. Validated against PHP's known zones.
            'timezone' => ['nullable', 'string', 'timezone'],
            // Brand reference photo ids the user picked in the generation card.
            // Structured side-channel for generate_post — the tool reads them
            // and drops any that do not belong to the workspace.
            'reference_media_ids' => ['nullable', 'array', 'max:'.BrandReferenceKind::MAX_REFERENCES],
            'reference_media_ids.*' => ['string'],
        ];
    }
}
