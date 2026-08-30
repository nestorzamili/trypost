<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Brand;

use App\Http\Resources\Api\BrandVariantResource;
use App\Models\BrandVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get details for a specific brand variant / language variant by ID.')]
class GetBrandVariantTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $request->user()->currentWorkspace;

        $validated = $request->validate([
            'id' => ['required', 'string', 'uuid'],
        ]);

        $variant = $workspace->brandVariants()->find($validated['id']);

        if (! $variant instanceof BrandVariant) {
            return Response::error('Brand variant not found.');
        }

        return Response::structured((new BrandVariantResource($variant))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required()->description('The UUID of the brand variant.'),
        ];
    }
}
