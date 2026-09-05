<?php

declare(strict_types=1);

namespace App\Ai\Tools\Signature;

use App\Actions\Signature\UpdateSignature;
use App\Ai\Tools\WorkspaceWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateSignatureTool extends WorkspaceWriteTool
{
    public function name(): string
    {
        return 'update_signature';
    }

    public function description(): Stringable|string
    {
        return 'Rename or rewrite a signature in the current workspace. Call list_signatures first and pass a real id — never guess one from the name. Already published posts keep the text they were published with; only future appends use the new content.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'signature_id' => $schema->string()->required()->description('The id of the signature to update, as returned by list_signatures.'),
            'name' => $schema->string()->description('The new signature name.'),
            'content' => $schema->string()->description('The new signature text.'),
        ];
    }

    protected function run(Request $request): string
    {
        $signature = $this->resolveSignature($request->string('signature_id')->value());

        if ($signature === null) {
            return $this->error(__('chat.tools.signature_not_found'));
        }

        $data = [];

        if ($request->filled('name')) {
            $data['name'] = trim($request->string('name')->value());
        }

        if ($request->filled('content')) {
            $data['content'] = $request->string('content')->value();
        }

        if ($data === []) {
            return $this->error('Nothing to update. Pass a new name, new content, or both.');
        }

        // The name is already trimmed, so min:1 rejects a whitespace-only
        // name that `filled()` let through. Content keeps its raw formatting
        // but may not be blank.
        $validator = Validator::make($data, [
            'name' => ['string', 'min:1', 'max:255'],
            'content' => ['string'],
        ]);

        if ($validator->fails()
            || (array_key_exists('content', $data) && trim($data['content']) === '')) {
            return $this->error((string) ($validator->errors()->first() ?? 'The content field is required.'));
        }

        // UpdateSignature::execute() replaces both columns, so fields the
        // caller did not name are carried over from the current row.
        UpdateSignature::execute($signature, [
            'name' => $signature->name,
            'content' => $signature->content,
            ...$validator->validated(),
        ]);

        return $this->json(['data' => [
            'id' => $signature->id,
            'name' => $signature->name,
            'content' => $signature->content,
        ]]);
    }
}
