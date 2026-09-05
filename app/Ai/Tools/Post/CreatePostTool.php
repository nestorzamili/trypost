<?php

declare(strict_types=1);

namespace App\Ai\Tools\Post;

use App\Actions\Post\CreatePost;
use App\Ai\Tools\WorkspaceWriteTool;
use App\Enums\Post\CreatedVia;
use App\Http\Resources\Chat\ChatPostResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreatePostTool extends WorkspaceWriteTool
{
    public function name(): string
    {
        return 'create_post';
    }

    public function description(): Stringable|string
    {
        return 'Create a new draft post in the current workspace with the given content. The post starts as a draft with no platforms enabled — use update_post to attach platforms and schedule_post to schedule it. Pass label_ids from list_labels to tag it, and attach_existing_asset afterwards to reuse library media.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()->required()->description('The text content of the new draft post.'),
            'label_ids' => $schema->array()->items($schema->string())->description('Optional. Label ids from list_labels to tag the post with.'),
        ];
    }

    protected function run(Request $request): string
    {
        $labelIds = $this->validLabelIds($request);

        if (is_string($labelIds)) {
            return $this->error($labelIds);
        }

        $post = CreatePost::execute($this->workspace, $this->user, [
            'content' => $request->string('content')->value(),
            'created_via' => CreatedVia::Chat,
            'label_ids' => $labelIds,
        ]);

        return $this->json([
            'data' => (new ChatPostResource($post->load(['postPlatforms.socialAccount', 'labels'])))->withFullContent()->resolve(),
        ]);
    }
}
