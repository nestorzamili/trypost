<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Brand;

use App\Enums\Workspace\BrandFont;
use App\Http\Resources\Api\BrandVariantResource;
use App\Mcp\Concerns\AuthorizesMcpTool;
use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a new language/brand variant for the current workspace with custom fonts, colors, and visual notes.')]
class CreateBrandVariantTool extends Tool
{
    use AuthorizesMcpTool;

    public function handle(Request $request): Response|ResponseFactory
    {
        $workspace = $this->authorizeCurrentWorkspace(
            $request,
            'update',
            'Not authorized to manage brand variants.',
        );

        if (! $workspace instanceof Workspace) {
            return $workspace;
        }

        $hex = ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'];

        $validated = $request->validate([
            'language_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('brand_variants', 'language_code')->where(
                    fn ($q) => $q->where('workspace_id', $workspace->id)
                ),
            ],
            'label' => ['required', 'string', 'max:100'],
            'colors' => ['nullable', 'array', 'max:20'],
            'colors.*' => ['required', 'string', 'max:100', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'brand_color' => $hex,
            'background_color' => $hex,
            'text_color' => $hex,
            'headline_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'body_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'label_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'accent_font' => ['nullable', 'string', Rule::in(BrandFont::values())],
            'visual_notes' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        $variant = $workspace->brandVariants()->create($validated);

        return Response::structured((new BrandVariantResource($variant))->resolve());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'language_code' => $schema->string()->required()->description('Unique language code for this variant (e.g. "en", "zh", "es", "id").'),
            'label' => $schema->string()->required()->description('Display label for this variant (e.g. "English Content", "Chinese Market").'),
            'brand_color' => $schema->string()->description('Primary brand accent color in hex format (e.g. "#7c3aed").'),
            'background_color' => $schema->string()->description('Default background color in hex format (e.g. "#faf8f5").'),
            'text_color' => $schema->string()->description('Default text color in hex format (e.g. "#0a0a0a").'),
            'headline_font' => $schema->string()->description('Font family for main headlines in this language (e.g. "Inter", "Noto Sans TC").'),
            'body_font' => $schema->string()->description('Font family for body text in this language (e.g. "Inter", "Roboto").'),
            'label_font' => $schema->string()->description('Font family for UI labels/subheadings.'),
            'accent_font' => $schema->string()->description('Optional accent or script font family.'),
            'colors' => $schema->object()->description('Named color palette dictionary as key-value pairs (e.g. {"Primary": "#7c3aed", "Accent": "#f59e0b"}).'),
            'visual_notes' => $schema->string()->description('Styling notes or directions for image generation in this language.'),
            'sort_order' => $schema->integer()->description('Display order index.'),
        ];
    }
}
