<?php

declare(strict_types=1);

namespace App\Ai\Tools\Label;

use App\Actions\Label\UpdateLabel;
use App\Ai\Tools\WorkspaceWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateLabelTool extends WorkspaceWriteTool
{
    public function name(): string
    {
        return 'update_label';
    }

    public function description(): Stringable|string
    {
        return 'Rename or recolor a label in the current workspace. Call list_labels first and pass a real id — never guess one from the name. The change applies everywhere the label is shown.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'label_id' => $schema->string()->required()->description('The id of the label to update, as returned by list_labels.'),
            'name' => $schema->string()->description('The new label name.'),
            'color' => $schema->string()->description('The new hex color code, e.g. #FF5733.'),
        ];
    }

    protected function run(Request $request): string
    {
        $label = $this->resolveLabel($request->string('label_id')->value());

        if ($label === null) {
            return $this->error(__('chat.tools.label_not_found'));
        }

        $data = [];

        if ($request->filled('name')) {
            $data['name'] = trim($request->string('name')->value());
        }

        if ($request->filled('color')) {
            $data['color'] = $request->string('color')->value();
        }

        if ($data === []) {
            return $this->error('Nothing to update. Pass a new name, a new color, or both.');
        }

        // $data is already trimmed, so min:1 rejects a whitespace-only name
        // that `filled()` let through.
        $validator = Validator::make($data, [
            'name' => ['string', 'min:1', 'max:255'],
            'color' => ['string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        if ($validator->fails()) {
            return $this->error((string) $validator->errors()->first());
        }

        // UpdateLabel::execute() replaces both columns, so fields the caller
        // did not name are carried over from the current row — passing a
        // partial array straight through would null the other column.
        UpdateLabel::execute($label, [
            'name' => $label->name,
            'color' => $label->color,
            ...$validator->validated(),
        ]);

        return $this->json(['data' => [
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color,
        ]]);
    }
}
