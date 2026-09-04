<?php

declare(strict_types=1);

namespace App\Mcp\Tools\BrandReferencePhoto;

use App\Http\Resources\Api\BrandReferencePhotoResource;
use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List Brand Reference Photos for the current workspace. These images guide AI-generated brand visuals. Returns newest first. Use request-brand-reference-photo-upload-tool to add an image, update-brand-reference-photo-tool to change its label, or delete-brand-reference-photo-tool to remove one.')]
class ListBrandReferencePhotosTool extends Tool
{
    use AuthorizesMcpTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->authorizeCurrentWorkspace(
            $request,
            'view',
            'Not authorized to view Brand Reference Photos.',
        );

        if (! $workspace instanceof Workspace) {
            return $workspace;
        }

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $references = $workspace->getMedia('brand_references')
            ->latest()
            ->limit((int) data_get($validated, 'limit', 50))
            ->get();

        return Response::structured([
            'brand_reference_photos' => BrandReferencePhotoResource::collection($references)->resolve(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Optional. Maximum number of photos to return, from 1 to 100. Defaults to 50.'),
        ];
    }
}
