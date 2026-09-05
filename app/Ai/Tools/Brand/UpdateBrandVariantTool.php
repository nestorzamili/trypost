<?php

declare(strict_types=1);

namespace App\Ai\Tools\Brand;

use App\Ai\Tools\WorkspaceAdminTool;
use App\Enums\Workspace\BrandFont;
use App\Enums\Workspace\ContentLanguage;
use App\Http\Resources\Chat\ChatBrandResource;
use App\Models\BrandVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateBrandVariantTool extends WorkspaceAdminTool
{
    public function name(): string
    {
        return 'update_brand_variant';
    }

    public function description(): Stringable|string
    {
        return 'Update a language variant of the workspace brand. Only the fields you pass are changed — everything else is left alone. Call get_brand first and pass a real id. The color palette map is not editable here and stays in the settings UI.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'variant_id' => $schema->string()->required()->description('The id of the variant to update, as returned by get_brand.'),
            'language_code' => $schema->string()->enum(ContentLanguage::values())->description('The language this variant is for, e.g. en.'),
            'label' => $schema->string()->description('The variant label, e.g. English.'),
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
        $variant = $this->resolveVariant($request->string('variant_id')->value());

        if ($variant === null) {
            return $this->error(__('chat.tools.brand_variant_not_found'));
        }

        $updatable = ['language_code', 'label', 'brand_color', 'background_color', 'text_color', 'headline_font', 'body_font', 'label_font', 'accent_font', 'visual_notes'];
        $given = $request->toArray();

        // Unknown keys fail loudly instead of being silently dropped — see
        // UpdateBrandTool for why.
        $unknown = array_values(array_diff(array_keys($given), [...$updatable, 'variant_id']));

        if ($unknown !== []) {
            $message = 'Unknown field(s): '.implode(', ', $unknown).'. Updatable variant fields are: '.implode(', ', $updatable).'.';

            if (in_array('colors', $unknown, true)) {
                $message .= ' The color palette can only be changed in the settings UI.';
            }

            return $this->error($message);
        }

        if (array_filter($updatable, fn (string $field): bool => array_key_exists($field, $given) && $given[$field] !== null) === []) {
            return $this->error('Nothing to update. Pass at least one field besides variant_id.');
        }

        $validator = Validator::make($given, [
            'language_code' => ['string', Rule::in(ContentLanguage::values()), Rule::unique('brand_variants', 'language_code')->where(fn ($query) => $query->where('workspace_id', $this->workspace->id))->ignore($variant->getKey())],
            'label' => ['string', 'max:100'],
            'brand_color' => $this->hexRule(),
            'background_color' => $this->hexRule(),
            'text_color' => $this->hexRule(),
            'headline_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'body_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'label_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'accent_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'visual_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'language_code.unique' => 'This workspace already has another variant for that language.',
        ]);

        if ($validator->fails()) {
            return $this->error((string) $validator->errors()->first());
        }

        $variant->update($validator->validated());

        return $this->json(['data' => ChatBrandResource::variantData($variant->fresh())]);
    }

    private function resolveVariant(?string $variantId): ?BrandVariant
    {
        if (blank($variantId)) {
            return null;
        }

        return $this->workspace->brandVariants()->find($variantId);
    }

    /**
     * @return list<string>
     */
    private function hexRule(): array
    {
        return ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];
    }
}
