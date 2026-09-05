<?php

declare(strict_types=1);

namespace App\Ai\Tools\Asset;

use App\Actions\Media\ListWorkspaceAssets;
use App\Ai\Tools\WorkspaceTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListAssetsTool extends WorkspaceTool
{
    public function name(): string
    {
        return 'list_assets';
    }

    public function description(): Stringable|string
    {
        return 'List Asset Library media in the current workspace (the workspace "assets" collection only — not logos or brand references), newest first. Use get_asset for one item by id, or attach_existing_asset to reuse an item on a draft or scheduled post.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional. Case-insensitive substring matched against the original filename.'),
            'type' => $schema->string()->enum(['image', 'video', 'document'])->description('Optional. Keep only this media type.'),
            'limit' => $schema->integer()->min(1)->max(25)->description('Optional. Maximum number of items to return. Defaults to 10.'),
        ];
    }

    protected function run(Request $request): string
    {
        $search = $request->filled('search') ? trim($request->string('search')->value()) : null;
        $type = $request->filled('type') ? $request->string('type')->value() : null;
        $limit = (int) $request->clamp('limit', 1, 25, 10);

        if (! in_array($type, ['image', 'video', 'document'], true)) {
            $type = null;
        }

        $assets = ListWorkspaceAssets::query($this->workspace, $search, $type)
            ->limit($limit)
            ->get()
            ->map(fn ($media): array => [
                'id' => $media->id,
                'original_filename' => $media->original_filename,
                'type' => $media->type->value,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->url,
            ])
            ->all();

        return $this->json(['data' => $assets]);
    }
}
