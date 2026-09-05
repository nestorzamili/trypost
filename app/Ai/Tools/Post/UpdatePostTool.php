<?php

declare(strict_types=1);

namespace App\Ai\Tools\Post;

use App\Actions\Post\UpdatePost;
use App\Ai\Tools\WorkspaceWriteTool;
use App\Enums\Post\Action as PostAction;
use App\Http\Resources\Chat\ChatPostResource;
use App\Support\PostStatusRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdatePostTool extends WorkspaceWriteTool
{
    public function name(): string
    {
        return 'update_post';
    }

    public function description(): Stringable|string
    {
        return 'Update an existing post in the current workspace. Only draft or scheduled posts can be edited — a post that has already been published, is currently publishing, or failed cannot be changed. Pass label_ids from list_labels to retag it; to add a signature, append its exact content from list_signatures to the text you send as content.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->string()->required()->description('The id of the post to update.'),
            'content' => $schema->string()->description('The new text content for the post.'),
            'label_ids' => $schema->array()->items($schema->string())->description('Optional. Label ids from list_labels to tag the post with. Replaces the post tags.'),
        ];
    }

    protected function run(Request $request): string
    {
        $post = $this->resolvePost($request->string('post_id')->value());

        if ($post === null) {
            return $this->error(__('chat.tools.post_not_found'));
        }

        $data = [];

        if ($request->filled('content')) {
            $data['content'] = $request->string('content')->value();
        }

        if (data_get($request->toArray(), 'label_ids') !== null) {
            $labelIds = $this->validLabelIds($request);

            if (is_string($labelIds)) {
                return $this->error($labelIds);
            }

            $data['label_ids'] = $labelIds;
        }

        $result = UpdatePost::execute($this->workspace, $post, $data);

        if (data_get($result, 'action') === PostAction::Finalized) {
            return $this->error(PostStatusRules::editBlockedMessage());
        }

        return $this->json([
            'data' => (new ChatPostResource($post->fresh()->load(['postPlatforms.socialAccount', 'labels'])))->withFullContent()->resolve(),
        ]);
    }
}
