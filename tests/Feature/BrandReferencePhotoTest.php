<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
    $this->user->refresh();
});

test('unauthenticated users cannot manage brand reference photos', function () {
    $this->getJson(route('app.workspace.brand-references.index'))
        ->assertUnauthorized();

    $this->postJson(route('app.workspace.brand-references.store'), [
        'photo' => UploadedFile::fake()->image('sara.jpg'),
    ])->assertUnauthorized();
});

test('an admin can upload list and delete brand reference photos', function () {
    $file = UploadedFile::fake()->image('sara_portrait.jpg', 600, 600);

    $response = $this->actingAs($this->user)
        ->postJson(route('app.workspace.brand-references.store'), [
            'photo' => $file,
            'label' => 'Sara Portrait',
        ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'id', 'path', 'url', 'type', 'created_at',
    ]);

    $mediaId = $response->json('id');
    $media = Media::find($mediaId);

    expect($media)->not->toBeNull()
        ->and($media->collection)->toBe('brand_references')
        ->and($media->meta['label'] ?? null)->toBe('Sara Portrait');

    // List references
    $listResponse = $this->actingAs($this->user)
        ->getJson(route('app.workspace.brand-references.index'));

    $listResponse->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $mediaId);

    // Delete reference
    $deleteResponse = $this->actingAs($this->user)
        ->deleteJson(route('app.workspace.brand-references.destroy', ['media' => $mediaId]));

    $deleteResponse->assertNoContent();
    expect(Media::find($mediaId))->toBeNull();
});

test('users cannot delete reference photos belonging to other workspaces', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherMedia = $otherWorkspace->addMedia(UploadedFile::fake()->image('other.jpg'), 'brand_references');

    $this->actingAs($this->user)
        ->deleteJson(route('app.workspace.brand-references.destroy', $otherMedia))
        ->assertNotFound();

    expect(Media::find($otherMedia->id))->not->toBeNull();
});
