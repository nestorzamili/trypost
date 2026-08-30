<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Enums\Workspace\BrandFont;
use App\Enums\Workspace\BrandVoiceTrait;
use App\Enums\Workspace\ContentLanguage;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Brand\CreateBrandVariantTool;
use App\Mcp\Tools\Brand\DeleteBrandVariantTool;
use App\Mcp\Tools\Brand\GetBrandTool;
use App\Mcp\Tools\Brand\GetBrandVariantTool;
use App\Mcp\Tools\Brand\ListBrandVariantsTool;
use App\Mcp\Tools\Brand\UpdateBrandTool;
use App\Mcp\Tools\Brand\UpdateBrandVariantTool;
use App\Models\BrandVariant;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'user_id' => $this->owner->id,
        'brand_description' => 'A modern social media workspace',
        'brand_guidelines' => 'Keep posts authentic',
        'brand_voice_traits' => [BrandVoiceTrait::Witty->value],
        'brand_color' => '#7c3aed',
        'background_color' => '#ffffff',
        'text_color' => '#000000',
        'brand_font' => 'Inter',
        'content_language' => 'en',
    ]);
    $this->workspace->members()->attach($this->owner->id, ['role' => Role::Admin->value]);
    $this->owner->update(['current_workspace_id' => $this->workspace->id]);

    $this->viewer = User::factory()->create(['account_id' => $this->owner->account_id]);
    $this->workspace->members()->attach($this->viewer->id, ['role' => Role::Viewer->value]);
    $this->viewer->update(['current_workspace_id' => $this->workspace->id]);
});

test('workspace brand can be retrieved via mcp get brand tool', function () {
    BrandVariant::factory()->create([
        'workspace_id' => $this->workspace->id,
        'language_code' => 'zh',
        'label' => 'Chinese Content',
        'brand_color' => '#dc2626',
        'headline_font' => BrandFont::NotoSansTC->value,
    ]);

    TryPostServer::actingAs($this->owner)
        ->tool(GetBrandTool::class, [])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('workspace_id', $this->workspace->id)
            ->where('brand_description', 'A modern social media workspace')
            ->where('brand_color', '#7c3aed')
            ->where('content_language', 'en')
            ->has('variants', 1)
            ->where('variants.0.language_code', 'zh')
            ->where('variants.0.brand_color', '#dc2626')
            ->etc()
        );
});

test('get brand tool resolves language variant snapshot when language_code is passed', function () {
    BrandVariant::factory()->create([
        'workspace_id' => $this->workspace->id,
        'language_code' => 'zh',
        'label' => 'Chinese Content',
        'brand_color' => '#dc2626',
        'headline_font' => BrandFont::NotoSansTC->value,
    ]);

    TryPostServer::actingAs($this->owner)
        ->tool(GetBrandTool::class, ['language_code' => 'zh'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('resolved_brand.language', 'zh')
            ->where('resolved_brand.brand_color', '#dc2626')
            ->where('resolved_brand.headline_font', BrandFont::NotoSansTC->value)
            ->etc()
        );
});

test('admin can update workspace brand via mcp', function () {
    TryPostServer::actingAs($this->owner)
        ->tool(UpdateBrandTool::class, [
            'brand_description' => 'Updated brand description',
            'brand_color' => '#10b981',
            'brand_font' => 'Poppins',
            'content_language' => ContentLanguage::Spanish->value,
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('brand_description', 'Updated brand description')
            ->where('brand_color', '#10b981')
            ->where('brand_font', 'Poppins')
            ->where('content_language', 'es')
            ->etc()
        );

    $this->workspace->refresh();
    expect($this->workspace->brand_description)->toBe('Updated brand description')
        ->and($this->workspace->brand_color)->toBe('#10b981')
        ->and($this->workspace->brand_font)->toBe('Poppins')
        ->and($this->workspace->content_language)->toBe('es');
});

test('viewer cannot update workspace brand via mcp', function () {
    TryPostServer::actingAs($this->viewer)
        ->tool(UpdateBrandTool::class, [
            'brand_description' => 'Unauthorized update',
        ])
        ->assertHasErrors(['Not authorized to update workspace brand settings.']);
});

test('brand variants can be listed and retrieved via mcp', function () {
    $variant = BrandVariant::factory()->create([
        'workspace_id' => $this->workspace->id,
        'language_code' => 'zh',
        'label' => 'Chinese Content',
        'brand_color' => '#dc2626',
    ]);

    TryPostServer::actingAs($this->viewer)
        ->tool(ListBrandVariantsTool::class, [])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->has('variants', 1)
            ->where('variants.0.id', $variant->id)
            ->where('variants.0.language_code', 'zh')
            ->etc()
        );

    TryPostServer::actingAs($this->viewer)
        ->tool(GetBrandVariantTool::class, ['id' => $variant->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $variant->id)
            ->where('language_code', 'zh')
            ->where('label', 'Chinese Content')
            ->where('brand_color', '#dc2626')
            ->etc()
        );
});

test('admin can create, update, and delete brand variant via mcp', function () {
    TryPostServer::actingAs($this->owner)
        ->tool(CreateBrandVariantTool::class, [
            'language_code' => 'zh',
            'label' => 'Chinese Content',
            'brand_color' => '#dc2626',
            'headline_font' => BrandFont::NotoSansTC->value,
            'body_font' => BrandFont::NotoSansTC->value,
            'colors' => [
                'Primary' => '#dc2626',
                'Accent' => '#f59e0b',
            ],
            'visual_notes' => 'Use warm saturated red tones.',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('language_code', 'zh')
            ->where('label', 'Chinese Content')
            ->where('brand_color', '#dc2626')
            ->where('headline_font', BrandFont::NotoSansTC->value)
            ->where('visual_notes', 'Use warm saturated red tones.')
            ->etc()
        );

    $createdVariant = $this->workspace->brandVariants()->where('language_code', 'zh')->firstOrFail();
    expect($this->workspace->brandVariants()->count())->toBe(1)
        ->and($createdVariant->language_code)->toBe('zh')
        ->and($createdVariant->visual_notes)->toBe('Use warm saturated red tones.');

    // Update variant
    TryPostServer::actingAs($this->owner)
        ->tool(UpdateBrandVariantTool::class, [
            'id' => $createdVariant->id,
            'label' => 'Updated Chinese Content',
            'brand_color' => '#b91c1c',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $createdVariant->id)
            ->where('label', 'Updated Chinese Content')
            ->where('brand_color', '#b91c1c')
            ->etc()
        );

    // Delete variant
    TryPostServer::actingAs($this->owner)
        ->tool(DeleteBrandVariantTool::class, ['id' => $createdVariant->id])
        ->assertOk();

    expect($this->workspace->brandVariants()->count())->toBe(0);
});

test('viewer cannot create or delete brand variants via mcp', function () {
    TryPostServer::actingAs($this->viewer)
        ->tool(CreateBrandVariantTool::class, [
            'language_code' => 'zh',
            'label' => 'Chinese Content',
        ])
        ->assertHasErrors(['Not authorized to manage brand variants.']);
});
