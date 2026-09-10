<?php

declare(strict_types=1);

namespace App\Ai\Tools\Signature;

use App\Actions\Signature\CreateSignature;
use App\Ai\Tools\WorkspaceWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateSignatureTool extends WorkspaceWriteTool
{
    public function name(): string
    {
        return 'create_signature';
    }

    public function description(): Stringable|string
    {
        return 'Create a signature (a reusable text block such as hashtags, links or custom text appended to posts) in the current workspace. Call list_signatures first: when a signature with the same name already exists it is returned instead of creating a duplicate, so never guess — check.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The signature name.'),
            'content' => $schema->string()->required()->description('The signature text that gets appended to posts.'),
        ];
    }

    protected function run(Request $request): string
    {
        // The name is validated after trimming: `required` alone lets a
        // whitespace-only name through. Content keeps its raw formatting
        // (leading newlines can be intentional) but may not be blank.
        $data = [
            'name' => trim($request->string('name')->value()),
            'content' => $request->string('content')->value(),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        if ($validator->fails() || trim($data['content']) === '') {
            return $this->error((string) ($validator->errors()->first() ?? 'The content field is required.'));
        }

        $existing = $this->workspace->signatures()
            ->whereLike('name', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $data['name']))
            ->first();

        if ($existing !== null) {
            return $this->json(['data' => [
                'id' => $existing->id,
                'name' => $existing->name,
                'content' => $existing->content,
                'already_existed' => true,
            ]]);
        }

        $signature = CreateSignature::execute($this->workspace, $validator->validated());

        return $this->json(['data' => [
            'id' => $signature->id,
            'name' => $signature->name,
            'content' => $signature->content,
            'already_existed' => false,
        ]]);
    }
}
