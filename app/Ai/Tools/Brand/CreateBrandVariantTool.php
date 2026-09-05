<?php

declare(strict_types=1);

namespace App\Ai\Tools\Brand;

use App\Ai\Tools\WorkspaceAdminTool;
use App\Enums\Workspace\BrandFont;
use App\Enums\Workspace\ContentLanguage;
use App\Http\Resources\Chat\ChatBrandResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateBrandVariantTool extends WorkspaceAdminTool
{
    public function name(): string
    {
        return 'create_brand_variant';
    }

    public function description(): Stringable|string
    {
        return 'Create a language variant of the workspace brand in the current workspace. Generation in that language follows the variant automatically. One variant per language: when one already exists for the language, update it instead. The color palette map is not editable here and stays in the settings UI.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'language_code' => $schema->string()->enum(ContentLanguage::values())->required()->description('The language this variant is for, e.g. en.'),
            'label' => $schema->string()->required()->description('The variant label, e.g. English.'),
            'brand_color' => $schema->string()->description('Hex color, e.g. #FF5733.'),
            'background_color' => $schema->string()->description('Hex color, e.g. #FFFFFF.'),
            'text_color' => $schema->string()->description('Hex color, e.g. #111111.'),
            'headline_font' => $schema->string()->enum(BrandFont::values())->description('The headline font.'),
            'body_font' => $schema->string()->enum(BrandFont::values())->description('The body font.'),
            'label_font' => $schema->string()->enum(BrandFont::values())->description('The label font.'),
            'accent_font' => $schema->string()->enum(BrandFont::values())->description('The accent font.'),
            'visual_notes' => $schema->string()->description('Notes guiding the generated visuals, up to 5000 characters.'),
        ];
    }

    protected function run(Request $request): string
    {
        $validator = Validator::make($request->toArray(), [
            'language_code' => ['required', 'string', Rule::in(ContentLanguage::values()), Rule::unique('brand_variants', 'language_code')->where(fn ($query) => $query->where('workspace_id', $this->workspace->id))],
            'label' => ['required', 'string', 'max:100'],
            'brand_color' => $this->hexRule(),
            'background_color' => $this->hexRule(),
            'text_color' => $this->hexRule(),
            'headline_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'body_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'label_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'accent_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'visual_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'language_code.unique' => 'This workspace already has a variant for that language. Update it instead of creating a duplicate.',
        ]);

        if ($validator->fails()) {
            return $this->error((string) $validator->errors()->first());
        }

        $variant = $this->workspace->brandVariants()->create([
            ...$validator->validated(),
            'sort_order' => (int) $this->workspace->brandVariants()->max('sort_order') + 1,
        ]);

        return $this->json(['data' => ChatBrandResource::variantData($variant)]);
    }

    /**
     * @return list<string>
     */
    private function hexRule(): array
    {
        return ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];
    }
}
