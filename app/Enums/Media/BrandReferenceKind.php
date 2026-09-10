<?php

declare(strict_types=1);

namespace App\Enums\Media;

enum BrandReferenceKind: string
{
    case FaceCloseup = 'face_closeup';
    case FullBody = 'full_body';
    case Logo = 'logo';
    case Product = 'product';
    case Style = 'style';
    case Other = 'other';

    public const MAX_REFERENCES = 10;
}
