<?php

declare(strict_types=1);

namespace App\Ai\Tools\Asset;

use App\Actions\Media\FindWorkspaceAsset;
use App\Actions\Post\AttachExistingAsset;
use App\Ai\Tools\WorkspaceWriteTool;
use App\Http\Resources\Chat\ChatPostResource;
use App\Support\PostStatusRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use Stringable;

class AttachExistingAssetTool extends WorkspaceWriteTool
{
    public function name(): string
    {
        return 'attach_existing_asset';
    }

    public function description(): Stringable|string
    {
        return 'Reuse an existing Asset Library item on a post in the current workspace. The post must be a draft or scheduled; published, failed or publishing posts are rejected. Discover ids with list_assets or get_asset.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->string()->required()->description('The id of the post to attach the asset to. Must be a draft or scheduled post in this workspace.'),
            'asset_id' => $schema->string()->required()->description('The id of the Asset Library item, as returned by list_assets.'),
            'alt' => $schema->string()->description('Optional accessibility alt text for images (ignored for video and document). Maximum 2000 characters.'),
        ];
    }

    protected function run(Request $request): string
    {
        $post = $this->resolvePost($request->string('post_id')->value());

        if ($post === null) {
            return $this->error(__('chat.tools.post_not_found'));
        }

        $asset = FindWorkspaceAsset::execute($this->workspace, $request->string('asset_id')->value());

        if ($asset === null) {
            return $this->error(__('chat.tools.asset_not_found'));
        }

        $alt = $request->filled('alt') ? $request->string('alt')->value() : null;

        if (is_string($alt) && mb_strlen($alt) > 2000) {
            return $this->error(__('chat.tools.error'));
        }

        try {
            AttachExistingAsset::execute($post, $asset, $alt);
        } catch (ValidationException $e) {
            return $this->error($e->validator->errors()->first() ?? PostStatusRules::editBlockedMessage());
        }

        return $this->json([
            'data' => (new ChatPostResource($post->fresh()->load(['postPlatforms.socialAccount', 'labels'])))->withFullContent()->resolve(),
        ]);
    }
}
