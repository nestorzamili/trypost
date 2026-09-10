<?php

declare(strict_types=1);

namespace App\Ai\Tools\Post;

use App\Actions\Post\CreatePost;
use App\Ai\Agents\PostBriefRefiner;
use App\Ai\Tools\WorkspaceWriteTool;
use App\Enums\Post\CreatedVia;
use App\Enums\Workspace\ContentLanguage;
use App\Services\Ai\RecordAiUsage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class CreatePostsFromBriefsTool extends WorkspaceWriteTool
{
    private const MAX_BRIEFS = 50;

    private const LABEL_NAME = 'Content Brief';

    private const LABEL_COLOR = '#7c3aed';

    public function name(): string
    {
        return 'create_posts_from_briefs';
    }

    public function description(): Stringable|string
    {
        return 'Turn a batch of content briefs into draft posts in one call. Use this when the user attaches or pastes a CSV (or list) of content briefs and asks to import them. For each brief, pass its full text with every field it carries (topic, key insight, target audience, tone, goal, CTA, and so on) as one string; the tool refines each brief into an on-brand draft and files it under a "Content Brief" label it creates if missing. Prefer this over calling create_post per row: it handles the whole batch in a single step. Up to '.self::MAX_BRIEFS.' briefs per call.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'briefs' => $schema->array()->required()->items(
                $schema->object([
                    'text' => $schema->string()->required()->description('The full content brief for one post: every field it has, as one string.'),
                    'language_code' => $schema->string()->enum(ContentLanguage::values())->description('Optional. The brand language variant this brief is written for.'),
                ])
            )->description('The content briefs to import, one object per post.'),
        ];
    }

    protected function run(Request $request): string
    {
        if (Gate::forUser($this->user)->denies('useAi', $this->workspace->account)) {
            return $this->error(__('chat.tools.forbidden'));
        }

        $briefs = $this->briefs($request);

        if ($briefs === []) {
            return $this->error('Provide at least one brief with non-empty text.');
        }

        $labelId = $this->resolveLabelId();
        $created = [];
        $failed = 0;

        foreach ($briefs as $brief) {
            try {
                $content = $this->refine($brief['text'], $brief['language_code']);

                $post = CreatePost::execute($this->workspace, $this->user, [
                    'content' => $content,
                    'created_via' => CreatedVia::Import,
                    'label_ids' => [$labelId],
                ]);

                $created[] = $post->id;
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return $this->json([
            'data' => [
                'created_count' => count($created),
                'failed_count' => $failed,
                'label' => self::LABEL_NAME,
                'post_ids' => $created,
            ],
        ]);
    }

    /**
     * @return array<int, array{text: string, language_code: ?string}>
     */
    private function briefs(Request $request): array
    {
        $raw = data_get($request->toArray(), 'briefs', []);

        if (! is_array($raw)) {
            return [];
        }

        $briefs = [];

        foreach (array_slice($raw, 0, self::MAX_BRIEFS) as $entry) {
            $text = trim((string) data_get($entry, 'text', ''));

            if ($text === '') {
                continue;
            }

            $language = data_get($entry, 'language_code');
            $language = is_string($language) && $language !== '' ? $language : null;

            $briefs[] = ['text' => $text, 'language_code' => $language];
        }

        return $briefs;
    }

    private function refine(string $brief, ?string $languageCode): string
    {
        $brand = $this->workspace->resolvedBrand($languageCode);

        $response = (new PostBriefRefiner($this->workspace, $brand))->prompt($brief);

        RecordAiUsage::recordText(
            workspace: $this->workspace,
            promptTokens: $response->usage?->promptTokens ?? 0,
            completionTokens: $response->usage?->completionTokens ?? 0,
            provider: (string) $response->meta?->provider,
            model: (string) $response->meta?->model,
            userId: $this->user->id,
            metadata: ['agent' => 'post_brief_refiner', 'content_language' => $brand->languageCode],
        );

        $refined = trim((string) $response->text);

        return $refined !== '' ? $refined : $brief;
    }

    private function resolveLabelId(): string
    {
        return $this->workspace->labels()->firstOrCreate(
            ['name' => self::LABEL_NAME],
            ['color' => self::LABEL_COLOR],
        )->id;
    }
}
