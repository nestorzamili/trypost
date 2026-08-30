<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Brand;

use App\Enums\Workspace\BrandFont;
use App\Enums\Workspace\BrandVoiceTrait;
use App\Enums\Workspace\ContentLanguage;
use App\Enums\Workspace\ImageStyle;
use App\Http\Resources\Api\WorkspaceBrandResource;
use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Update core workspace brand settings such as brand description, guidelines, voice traits, default visual colors, font, image style, and default content language.')]
class UpdateBrandTool extends Tool
{
    use AuthorizesMcpTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->authorizeCurrentWorkspace(
            $request,
            'update',
            'Not authorized to update workspace brand settings.',
        );

        if (! $workspace instanceof Workspace) {
            return $workspace;
        }

        $hex = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];

        $validated = $request->validate([
            'brand_website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'brand_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'brand_guidelines' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'brand_voice_traits' => ['sometimes', 'nullable', 'array'],
            'brand_voice_traits.*' => ['string', Rule::enum(BrandVoiceTrait::class)],
            'brand_color' => $hex,
            'background_color' => $hex,
            'text_color' => $hex,
            'brand_font' => ['sometimes', 'string', Rule::in(BrandFont::values())],
            'image_style' => ['sometimes', 'string', Rule::in(ImageStyle::values())],
            'content_language' => ['sometimes', 'string', Rule::in(ContentLanguage::values())],
        ]);

        $workspace->update($validated);

        return Response::structured((new WorkspaceBrandResource($workspace->fresh()))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'brand_website' => $schema->string()->description('Official brand website URL.'),
            'brand_description' => $schema->string()->description('Summary of the brand, products, mission, and value proposition.'),
            'brand_guidelines' => $schema->string()->description('Specific editorial/content instructions, rules, or do-not-mention guidelines.'),
            'brand_voice_traits' => $schema->array()
                ->items($schema->string())
                ->description('List of brand voice trait keys (e.g. "style_witty", "formality_casual", "perspective_we").'),
            'brand_color' => $schema->string()->description('Primary brand accent color in hex format (e.g. "#7c3aed").'),
            'background_color' => $schema->string()->description('Default background color in hex format (e.g. "#faf8f5").'),
            'text_color' => $schema->string()->description('Default primary text color in hex format (e.g. "#0a0a0a").'),
            'brand_font' => $schema->string()->description('Default primary font family name (e.g. "Inter", "Roboto", "Poppins").'),
            'image_style' => $schema->string()->description('Default AI image generation style (e.g. "cinematic", "photographic", "minimal").'),
            'content_language' => $schema->string()->description('Default content language ISO code (e.g. "en", "zh", "es", "id").'),
        ];
    }
}
