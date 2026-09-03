<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Enums\Workspace\ContentLanguage;
use App\Models\BrandVariant;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

function variantPayload(array $overrides = []): array
{
    return array_merge([
        'language_code' => 'en',
        'label' => 'English Content',
        'colors' => [
            'Warm Ivory' => '#F8F5ED',
            'Soft Mustard' => '#D9A62E',
        ],
        'brand_color' => '#D9A62E',
        'background_color' => '#F8F5ED',
        'text_color' => '#292824',
        'headline_font' => 'Playfair Display',
        'body_font' => 'Noto Sans',
        'label_font' => 'Inter',
        'accent_font' => 'Caveat',
        'visual_notes' => 'Warm editorial direction.',
    ], $overrides);
}

test('unauthenticated users cannot create a brand variant', function () {
    $this->post(route('app.workspace.brand-variants.store'), variantPayload())
        ->assertRedirect(route('login'));
});

test('an admin can create update and delete a brand variant', function () {
    $response = $this->actingAs($this->user)
        ->post(route('app.workspace.brand-variants.store'), variantPayload());

    $response->assertRedirect();
    $variant = BrandVariant::query()->firstOrFail();
    $this->assertModelExists($variant);

    $this->actingAs($this->user)
        ->put(route('app.workspace.brand-variants.update', $variant), variantPayload([
            'label' => 'Updated English',
        ]))
        ->assertRedirect();

    expect($variant->refresh()->label)->toBe('Updated English');

    $this->actingAs($this->user)
        ->delete(route('app.workspace.brand-variants.destroy', $variant))
        ->assertRedirect();

    expect(BrandVariant::find($variant->id))->toBeNull();
});

test('members and viewers cannot modify brand variants', function () {
    $member = User::factory()->create(['account_id' => $this->workspace->account_id]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member)
        ->post(route('app.workspace.brand-variants.store'), variantPayload())
        ->assertForbidden();
});

test('a variant from another workspace is not accessible', function () {
    $otherWorkspace = Workspace::factory()->create();
    $variant = BrandVariant::factory()->english()->create(['workspace_id' => $otherWorkspace->id]);

    $this->actingAs($this->user)
        ->put(route('app.workspace.brand-variants.update', $variant), variantPayload([
            'label' => 'Should not update',
        ]))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->delete(route('app.workspace.brand-variants.destroy', $variant))
        ->assertNotFound();
});

test('only one variant per language can exist in a workspace', function () {
    BrandVariant::factory()->english()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->post(route('app.workspace.brand-variants.store'), variantPayload())
        ->assertSessionHasErrors('language_code');
});

test('variant language can be updated without colliding with itself', function () {
    $variant = BrandVariant::factory()->english()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->put(route('app.workspace.brand-variants.update', $variant), variantPayload([
            'label' => $variant->label,
        ]))
        ->assertRedirect();
});

test('variant language update cannot collide with another variant', function () {
    $english = BrandVariant::factory()->english()->create(['workspace_id' => $this->workspace->id]);
    $chinese = BrandVariant::factory()->chinese()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->put(route('app.workspace.brand-variants.update', $chinese), variantPayload([
            'language_code' => $english->language_code,
            'label' => $chinese->label,
        ]))
        ->assertSessionHasErrors('language_code');
});

test('variants accept every supported content language', function () {
    foreach (ContentLanguage::cases() as $language) {
        $this->actingAs($this->user)
            ->post(route('app.workspace.brand-variants.store'), variantPayload([
                'language_code' => $language->value,
                'label' => $language->label(),
            ]))
            ->assertRedirect();
    }

    expect($this->workspace->brandVariants()->count())->toBe(count(ContentLanguage::cases()));
});

test('variant validation rejects invalid palettes and unsupported languages', function () {
    $this->actingAs($this->user)
        ->post(route('app.workspace.brand-variants.store'), variantPayload([
            'colors' => ['Bad Color' => 'not-a-hex'],
        ]))
        ->assertSessionHasErrors('colors.Bad Color');

    $this->actingAs($this->user)
        ->post(route('app.workspace.brand-variants.store'), variantPayload([
            'language_code' => 'invalid',
        ]))
        ->assertSessionHasErrors('language_code');
});

test('variant validation rejects more than twenty colors and oversized notes', function () {
    $colors = [];
    for ($index = 1; $index <= 21; $index++) {
        $colors["Color {$index}"] = '#000000';
    }

    $this->actingAs($this->user)
        ->post(route('app.workspace.brand-variants.store'), variantPayload([
            'colors' => $colors,
            'visual_notes' => str_repeat('x', 5001),
        ]))
        ->assertSessionHasErrors(['colors', 'visual_notes']);
});

test('brand settings includes variants and available languages', function () {
    BrandVariant::factory()->english()->create(['workspace_id' => $this->workspace->id]);

    $this->actingAs($this->user)
        ->get(route('app.workspace.brand'))
        ->assertInertia(fn ($page) => $page
            ->component('settings/workspace/Brand', false)
            ->has('workspace.brand_variants', 1)
            ->has('variantLanguages', count(ContentLanguage::cases()))
            ->where('variantLanguages.0.code', ContentLanguage::English->value)
            ->where('variantLanguages.0.available', false)
            ->where('variantLanguages.1.code', ContentLanguage::Ukrainian->value)
            ->where('variantLanguages.1.available', true)
        );
});
