<?php

declare(strict_types=1);

namespace App\Mcp\Tools\BrandReferencePhoto;

use App\Http\Resources\Api\BrandReferencePhotoResource;
use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update the label for a Brand Reference Photo in the current workspace. The image itself cannot be replaced; upload a new reference photo instead.')]
class UpdateBrandReferencePhotoTool extends Tool
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
            'label' => ['required', 'string', 'max:100'],
        ]);

        $media = Media::query()
            ->where('mediable_type', $workspace->getMorphClass())
            ->where('mediable_id', $workspace->id)
            ->where('collection', 'brand_references')
            ->find(data_get($validated, 'media_id'));

        if (! $media) {
            return Response::error('Brand Reference Photo not found.');
        }

        $media->update([
            'meta' => [
                ...($media->meta ?? []),
                'label' => data_get($validated, 'label'),
            ],
        ]);

        return Response::structured((new BrandReferencePhotoResource($media->fresh()))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'media_id' => $schema->string()->required()->description('UUID of the Brand Reference Photo to update.'),
            'label' => $schema->string()->required()->description('New description of how AI should use this image, maximum 100 characters.'),
        ];
    }
}
