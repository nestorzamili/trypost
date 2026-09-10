<?php

declare(strict_types=1);

namespace App\Enums\Ai;

enum MediaRegenerationMode: string
{
    case TextOnly = 'text_only';
    case ImageOnly = 'image_only';
    case Both = 'both';

    public function regeneratesText(): bool
    {
        return $this !== self::ImageOnly;
    }

    public function regeneratesImage(): bool
    {
        return $this !== self::TextOnly;
    }
}
