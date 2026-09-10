<?php

declare(strict_types=1);

namespace App\Ai\Tools\Brand;

use App\Ai\Tools\WorkspaceAdminTool;
use App\Enums\Workspace\BrandFont;
use App\Enums\Workspace\BrandVoiceTrait;
use App\Enums\Workspace\ContentLanguage;
use App\Enums\Workspace\ImageStyle;
use App\Http\Resources\Chat\ChatBrandResource;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Every argument is optional and only the fields the user named are touched:
 * a full replace would force the model to restate — i.e. invent — every
 * field it did not mean to change. The workspace name, website and logo stay
 * in the settings UI on purpose: they identify the workspace everywhere and
 * are not generation input. Changing the brand always asks for confirmation
 * first, since every post generated afterwards follows the new brand.
 */
class UpdateBrandTool extends WorkspaceAdminTool implements Approvable
{
    use InteractsWithApprovals;

    /**
     * @return list<string>
     */
    public static function updatableFields(): array
    {
        return [
            'brand_description',
            'brand_guidelines',
            'brand_voice_traits',
            'brand_color',
            'background_color',
            'text_color',
            'brand_font',
            'image_style',
            'content_language',
        ];
    }

    public function name(): string
    {
        return 'update_brand';
    }

    public function description(): Stringable|string
    {
        return 'Update the workspace brand identity (description, guidelines, voice traits, colors, fonts, image style or content language) in the current workspace. Only the fields you pass are changed — everything else is left alone. Always asks the user to confirm first. The color palette map and language variants are not editable here: palettes stay in the settings UI, variants have their own tools.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'brand_description' => $schema->string()->description('What the brand does, up to 2000 characters.'),
            'brand_guidelines' => $schema->string()->description('Guidelines the generated content must follow, up to 5000 characters.'),
            'brand_voice_traits' => $schema->array()->items($schema->string()->enum(BrandVoiceTrait::values()))->description('Voice traits replacing the current set.'),
            'brand_color' => $schema->string()->description('Hex color, e.g. #FF5733.'),
            'background_color' => $schema->string()->description('Hex color, e.g. #FFFFFF.'),
            'text_color' => $schema->string()->description('Hex color, e.g. #111111.'),
            'brand_font' => $schema->string()->enum(BrandFont::values())->description('The brand font.'),
            'image_style' => $schema->string()->enum(ImageStyle::values())->description('The image style for generated visuals.'),
            'content_language' => $schema->string()->enum(ContentLanguage::values())->description('The default content language code, e.g. en.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        if ($this->writeDenied()) {
            return false;
        }

        if ($this->providedFields($request) === []) {
            return false;
        }

        return Approval::required(__('chat.approvals.update_brand'));
    }

    protected function run(Request $request): string
    {
        // Unknown keys fail loudly instead of being silently dropped: a
        // model that invents a field (notably the `colors` palette map, which
        // is not editable here) must learn it did nothing, not believe it
        // succeeded.
        $unknown = array_values(array_diff(array_keys($request->toArray()), self::updatableFields()));

        if ($unknown !== []) {
            $message = 'Unknown field(s): '.implode(', ', $unknown).'. Updatable brand fields are: '.implode(', ', self::updatableFields()).'.';

            if (in_array('colors', $unknown, true)) {
                $message .= ' The color palette can only be changed in the settings UI.';
            }

            return $this->error($message);
        }

        $fields = $this->providedFields($request);

        if ($fields === []) {
            return $this->error('Nothing to update. Pass at least one of: '.implode(', ', self::updatableFields()).'.');
        }

        $hex = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];

        $validator = Validator::make($request->toArray(), [
            'brand_description' => ['nullable', 'string', 'max:2000'],
            'brand_guidelines' => ['nullable', 'string', 'max:5000'],
            'brand_voice_traits' => ['nullable', 'array'],
            'brand_voice_traits.*' => ['string', Rule::enum(BrandVoiceTrait::class)],
            'brand_color' => $hex,
            'background_color' => $hex,
            'text_color' => $hex,
            'brand_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'image_style' => ['nullable', 'string', Rule::in(ImageStyle::values())],
            'content_language' => ['nullable', 'string', Rule::in(ContentLanguage::values())],
        ], [
            'brand_voice_traits.*.enum' => 'Invalid voice trait. Valid traits are: '.implode(', ', BrandVoiceTrait::values()).'.',
            'brand_font.in' => 'Invalid brand font. Valid fonts are: '.implode(', ', BrandFont::values()).'.',
            'image_style.in' => 'Invalid image style. Valid styles are: '.implode(', ', ImageStyle::values()).'.',
            'content_language.in' => 'Invalid content language. Valid codes are: '.implode(', ', ContentLanguage::values()).'.',
        ]);

        if ($validator->fails()) {
            return $this->error((string) $validator->errors()->first());
        }

        $this->workspace->update($validator->validated());

        return $this->json(['data' => (new ChatBrandResource($this->workspace->fresh()))->resolve()]);
    }

    /**
     * @return list<string>
     */
    private function providedFields(Request $request): array
    {
        $given = $request->toArray();

        return array_values(array_filter(
            self::updatableFields(),
            fn (string $field): bool => array_key_exists($field, $given) && $given[$field] !== null,
        ));
    }
}
