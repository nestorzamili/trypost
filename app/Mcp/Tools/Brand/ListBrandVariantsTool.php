<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Brand;

use App\Http\Resources\Api\BrandVariantResource;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List all language/brand variants configured for the current workspace.')]
class ListBrandVariantsTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $variants = $request->user()->currentWorkspace
            ->brandVariants()
            ->get();

        return Response::structured([
            'variants' => BrandVariantResource::collection($variants)->resolve(),
        ]);
    }
}
