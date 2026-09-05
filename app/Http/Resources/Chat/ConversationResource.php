<?php

declare(strict_types=1);

namespace App\Http\Resources\Chat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A conversation as listed in the history or opened for a full turn history.
 * `updated_at` is sent as ISO-8601 — the frontend groups conversations by
 * date locally, in the user's timezone.
 *
 * `title` falls back to the opening user message when background titling
 * hasn't produced one yet: the list must stay useful when the provider is
 * down, so null here means "no messages yet", never "hide this row".
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title ?? $this->fallbackTitle(),
            'status' => $this->status?->value,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function fallbackTitle(): ?string
    {
        $content = trim((string) $this->getAttribute('fallback_message'));

        if ($content === '') {
            return null;
        }

        return Str::limit($content, 60);
    }
}
