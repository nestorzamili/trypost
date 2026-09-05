<?php

declare(strict_types=1);

namespace App\Ai\Tools\Asset;

use App\Actions\Media\FindWorkspaceAsset;
use App\Ai\Tools\WorkspaceWriteTool;
use App\Models\Post;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The file itself is removed (unless another record still references the
 * same path), so deletion always asks for confirmation first. Posts that
 * already embedded a copy keep their own snapshot — only the library item
 * and future reuse disappear.
 */
class DeleteAssetTool extends WorkspaceWriteTool implements Approvable
{
    use InteractsWithApprovals;

    public function name(): string
    {
        return 'delete_asset';
    }

    public function description(): Stringable|string
    {
        return 'Delete an Asset Library item from the current workspace. Always asks the user to confirm first. Call list_assets first and pass a real id.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'asset_id' => $schema->string()->required()->description('The id of the asset to delete, as returned by list_assets.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        if ($this->writeDenied()) {
            return false;
        }

        if (FindWorkspaceAsset::execute($this->workspace, $request->string('asset_id')->value()) === null) {
            return false;
        }

        return Approval::required(__('chat.approvals.delete_asset'));
    }

    protected function run(Request $request): string
    {
        $asset = FindWorkspaceAsset::execute($this->workspace, $request->string('asset_id')->value());

        if ($asset === null) {
            return $this->error(__('chat.tools.asset_not_found'));
        }

        // Counted in PHP rather than with whereJsonContains(): the media
        // column holds an ARRAY of objects, and Postgres evaluates
        // `array @> object` as false (only `array @> array` matches), so a
        // contains query silently counts zero there while MySQL counts
        // correctly — exactly the cross-engine split this app does not do.
        // The LIKE prefilter (safe: uuids carry no LIKE metacharacters) keeps
        // the hydration to candidate rows only; the PHP check stays the
        // source of truth so a substring coincidence can never inflate it.
        $usedByPosts = $this->workspace->posts()
            ->where('media', 'like', '%'.$asset->id.'%')
            ->cursor(['id', 'media'])
            ->filter(fn (Post $post): bool => collect($post->media ?? [])->contains(
                fn (mixed $row): bool => is_array($row) && (string) data_get($row, 'id') === (string) $asset->id,
            ))
            ->count();

        $filename = $asset->original_filename;
        $asset->delete();

        return $this->json(['data' => [
            'id' => $asset->id,
            'original_filename' => $filename,
            'deleted' => true,
            'used_by_posts' => $usedByPosts,
        ]]);
    }
}
