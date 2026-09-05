<?php

declare(strict_types=1);

namespace App\Ai\Tools\Label;

use App\Ai\Tools\WorkspaceTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListLabelsTool extends WorkspaceTool
{
    public function name(): string
    {
        return 'list_labels';
    }

    public function description(): Stringable|string
    {
        return 'List the labels (colored tags that categorize posts) in the current workspace. Pass a returned id as label_ids when calling create_post, update_post or generate_post.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional. Case-insensitive substring matched against the label name.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Optional. Maximum number of labels to return. Defaults to 25.'),
        ];
    }

    protected function run(Request $request): string
    {
        $search = $request->filled('search') ? trim($request->string('search')->value()) : '';
        $limit = (int) $request->clamp('limit', 1, 50, 25);

        $labels = $this->workspace->labels()
            ->when($search !== '', fn ($query) => $query->whereLike('name', "%{$search}%"))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn ($label): array => [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
            ])
            ->all();

        return $this->json(['data' => $labels]);
    }
}
