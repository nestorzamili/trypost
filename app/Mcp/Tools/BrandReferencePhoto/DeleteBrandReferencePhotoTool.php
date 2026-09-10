<?php

declare(strict_types=1);

namespace App\Mcp\Tools\BrandReferencePhoto;

use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Permanently delete a Brand Reference Photo from the current workspace. The image will no longer be available to guide AI-generated visuals. This cannot be undone.')]
class DeleteBrandReferencePhotoTool extends Tool
{
    use AuthorizesMcpTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->authorizeCurrentWorkspace(
            $request,
            'update',
            'Not authorized to manage Brand Reference Photos.',
        );

        if (! $workspace instanceof Workspace) {
            return $workspace;
        }

        $validated = $request->validate([
            'media_id' => ['required', 'uuid'],
        ]);

        $media = Media::query()
            ->where('mediable_type', $workspace->getMorphClass())
            ->where('mediable_id', $workspace->id)
            ->where('collection', 'brand_references')
            ->find(data_get($validated, 'media_id'));

        if (! $media) {
            return Response::error('Brand Reference Photo not found.');
        }

        $media->delete();

        return Response::structured(['deleted' => true]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'media_id' => $schema->string()->required()->description('UUID of the Brand Reference Photo to permanently delete.'),
        ];
    }
}
