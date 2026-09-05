<?php

declare(strict_types=1);

namespace App\Ai\Tools\Label;

use App\Actions\Label\CreateLabel;
use App\Ai\Tools\WorkspaceWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateLabelTool extends WorkspaceWriteTool
{
    public function name(): string
    {
        return 'create_label';
    }

    public function description(): Stringable|string
    {
        return 'Create a label (a colored tag that categorizes posts) in the current workspace. Call list_labels first: when a label with the same name already exists it is returned instead of creating a duplicate, so never guess — check.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('The label name.'),
            'color' => $schema->string()->required()->description('Hex color code, e.g. #FF5733.'),
        ];
    }

    protected function run(Request $request): string
    {
        // Validated after trimming: `required` alone lets a whitespace-only
        // name through, and the stored value is the trimmed one.
        $data = [
            'name' => trim($request->string('name')->value()),
            'color' => $request->string('color')->value(),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if ($validator->fails()) {
            return $this->error((string) $validator->errors()->first());
        }

        $existing = $this->workspace->labels()
            ->whereLike('name', str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $data['name']))
            ->first();

        if ($existing !== null) {
            return $this->json(['data' => [
                'id' => $existing->id,
                'name' => $existing->name,
                'color' => $existing->color,
                'already_existed' => true,
            ]]);
        }

        $label = CreateLabel::execute($this->workspace, $validator->validated());

        return $this->json(['data' => [
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color,
            'already_existed' => false,
        ]]);
    }
}
