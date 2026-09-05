<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Media;

/**
 * Base for chat tools that change workspace-level settings: brand identity,
 * brand variants and brand reference photos.
 *
 * These need the workspace `update` ability (owner or workspace Admin),
 * which is stricter than the `createPost` ability {@see WorkspaceWriteTool}
 * gates on — the web controllers enforce the same split
 * (App\Http\Controllers\App\BrandVariantController,
 * App\Http\Controllers\App\BrandReferencePhotoController). A Member who may
 * create posts must still be refused here, so the gate is overridden rather
 * than inherited, with its own denial message.
 */
abstract class WorkspaceAdminTool extends WorkspaceWriteTool
{
    final protected function writeDenied(): bool
    {
        return $this->authorizesWrites() && $this->user->cannot('update', $this->workspace);
    }

    final protected function forbiddenMessage(): string
    {
        return __('chat.tools.admin_forbidden');
    }

    /**
     * Resolve a brand reference photo inside this tool's workspace. Null for
     * a missing id, another collection (e.g. a logo or asset), or another
     * workspace — the three are indistinguishable to the model on purpose,
     * mirroring BrandReferencePhotoController::destroy().
     */
    protected function resolveBrandReference(?string $photoId): ?Media
    {
        if (blank($photoId)) {
            return null;
        }

        return $this->workspace->getMedia('brand_references')->find($photoId);
    }
}
