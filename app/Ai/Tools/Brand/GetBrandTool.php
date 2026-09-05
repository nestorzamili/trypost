<?php

declare(strict_types=1);

namespace App\Ai\Tools\Brand;

use App\Ai\Tools\WorkspaceTool;
use App\Http\Resources\Chat\ChatBrandResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetBrandTool extends WorkspaceTool
{
    public function name(): string
    {
        return 'get_brand';
    }

    public function description(): Stringable|string
    {
        return 'Get the current workspace brand identity: name, description, voice traits, guidelines, colors and fonts, plus its language variants and photo references. Generation already follows this brand (variant plus photo references) on its own.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function run(Request $request): string
    {
        return $this->json(['data' => (new ChatBrandResource($this->workspace))->resolve()]);
    }
}
