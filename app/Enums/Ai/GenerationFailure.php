<?php

declare(strict_types=1);

namespace App\Enums\Ai;

/**
 * Classified, user-safe reasons an AI generation can fail.
 *
 * Failures must never surface a raw provider/SDK exception message to the end
 * user (it can carry `cURL error 28…`, URLs, or internal detail — the same
 * leakage WorkspaceTool guards against for chat tools). Each case maps to a
 * translated `posts.ai.generate.errors.*` string; the raw exception is logged
 * only. `message()` resolves the copy in the given locale.
 */
enum GenerationFailure: string
{
    case Text = 'text';
    case ImagePartial = 'image_partial';
    case ImageNone = 'image_none';
    case Timeout = 'timeout';
    case Unknown = 'unknown';

    /**
     * The i18n key under `posts.ai.generate.errors` for this failure kind.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::Text => 'text_failed',
            self::ImagePartial => 'image_partial',
            self::ImageNone => 'image_none',
            self::Timeout => 'timeout',
            self::Unknown => 'generation_failed',
        };
    }

    /**
     * A sanitized, localized, user-facing message.
     *
     * @param  array<string, int|string>  $replace  e.g. ['done' => 3, 'expected' => 5]
     */
    public function message(?string $locale = null, array $replace = []): string
    {
        return (string) trans('posts.ai.generate.errors.'.$this->translationKey(), $replace, $locale);
    }

    /**
     * Best-effort classification of a thrown error into a failure kind, so the
     * log keeps the raw text while the user gets a safe message. A connection
     * timeout is the one case worth naming specifically.
     */
    public static function fromThrowable(\Throwable $e, self $default = self::Unknown): self
    {
        $message = $e->getMessage();

        if (str_contains($message, 'cURL error 28') || stripos($message, 'timed out') !== false) {
            return self::Timeout;
        }

        return $default;
    }
}
