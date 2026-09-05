<?php

declare(strict_types=1);

namespace App\Ai\Tools\Signature;

use App\Ai\Tools\WorkspaceTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListSignaturesTool extends WorkspaceTool
{
    public function name(): string
    {
        return 'list_signatures';
    }

    public function description(): Stringable|string
    {
        return 'List the signatures (reusable text blocks such as hashtags, links or custom text) in the current workspace. To add one to a post, append its exact content to the post text via update_post.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional. Case-insensitive substring matched against the signature name.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Optional. Maximum number of signatures to return. Defaults to 25.'),
        ];
    }

    protected function run(Request $request): string
    {
        $search = $request->filled('search') ? trim($request->string('search')->value()) : '';
        $limit = (int) $request->clamp('limit', 1, 50, 25);

        $signatures = $this->workspace->signatures()
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%"))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn ($signature): array => [
                'id' => $signature->id,
                'name' => $signature->name,
                'content' => $signature->content,
            ])
            ->all();

        return $this->json(['data' => $signatures]);
    }
}
