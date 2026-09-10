<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum GenerationStatus: string
{
    case PendingText = 'pending_text';
    case TextReady = 'text_ready';
    case ImageRunning = 'image_running';
    case Ready = 'ready';
    case FailedText = 'failed_text';
    case FailedImage = 'failed_image';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Ready, self::FailedText, self::FailedImage => true,
            default => false,
        };
    }

    public function failed(): bool
    {
        return match ($this) {
            self::FailedText, self::FailedImage => true,
            default => false,
        };
    }
}
