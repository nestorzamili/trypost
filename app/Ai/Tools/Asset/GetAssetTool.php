<?php

declare(strict_types=1);

namespace App\Ai\Tools\Asset;

use App\Actions\Media\FindWorkspaceAsset;
use App\Ai\Tools\WorkspaceTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetAssetTool extends WorkspaceTool
{
    public function name(): string
    {
        return 'get_asset';
    }

    public function description(): Stringable|string
    {
        return 'Get one Asset Library item by id from the current workspace. The id must belong to the workspace "assets" collection — other collections or workspaces return Asset not found.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'asset_id' => $schema->string()->required()->description('The id of the asset to fetch, as returned by list_assets.'),
        ];
    }

    protected function run(Request $request): string
    {
        $asset = FindWorkspaceAsset::execute($this->workspace, $request->string('asset_id')->value());

        if ($asset === null) {
            return $this->error(__('chat.tools.asset_not_found'));
        }

        return $this->json(['data' => [
            'id' => $asset->id,
            'original_filename' => $asset->original_filename,
            'type' => $asset->type->value,
            'mime_type' => $asset->mime_type,
            'size' => $asset->size,
            'url' => $asset->url,
            'meta' => $asset->meta,
        ]]);
    }
}
