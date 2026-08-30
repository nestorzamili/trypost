<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Brand;

use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\BrandVariant;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Delete a brand variant / language variant from the current workspace by ID.')]
class DeleteBrandVariantTool extends Tool
{
    use AuthorizesMcpTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->authorizeCurrentWorkspace(
            $request,
            'update',
            'Not authorized to manage brand variants.',
        );

        if (! $workspace instanceof Workspace) {
            return $workspace;
        }

        $validated = $request->validate([
            'id' => ['required', 'string', 'uuid'],
        ]);

        $variant = $workspace->brandVariants()->find($validated['id']);

        if (! $variant instanceof BrandVariant) {
            return Response::error('Brand variant not found.');
        }

        $variant->delete();

        return Response::text('Brand variant deleted successfully.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required()->description('The UUID of the brand variant to delete.'),
        ];
    }
}
