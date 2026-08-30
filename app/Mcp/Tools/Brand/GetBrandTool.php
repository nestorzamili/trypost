<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Brand;

use App\Http\Resources\Api\WorkspaceBrandResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the current workspace brand settings, voice traits, guidelines, visual styling, and all language/brand variants. Optionally pass language_code to get the resolved brand configuration for that specific language.')]
class GetBrandTool extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $workspace = $request->user()->currentWorkspace;

        $brand = (new WorkspaceBrandResource($workspace))->resolve();

        if ($languageCode = $request->get('language_code')) {
            $brand['resolved_brand'] = $workspace->resolvedBrand($languageCode)->toSnapshot();
        }

        return Response::structured($brand);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'language_code' => $schema->string()->description('Optional language code (e.g. "en", "zh", "es") to preview how brand settings and visual overrides resolve for that language.'),
        ];
    }
}
