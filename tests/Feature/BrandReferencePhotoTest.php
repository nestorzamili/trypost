<?php

declare(strict_types=1);

use App\Enums\Media\BrandReferenceKind;
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

test('an admin can upload a reference with a kind', function () {
    $this->actingAs($this->user)
        ->postJson(route('app.workspace.brand-references.store'), [
            'photo' => UploadedFile::fake()->image('logo.jpg'),
            'kind' => BrandReferenceKind::Logo->value,
            'label' => 'Company logo',
        ])
        ->assertCreated()
        ->assertJsonPath('meta.kind', BrandReferenceKind::Logo->value)
        ->assertJsonPath('meta.label', 'Company logo');
});

test('store rejects an invalid kind', function () {
    $this->actingAs($this->user)
        ->postJson(route('app.workspace.brand-references.store'), [
            'photo' => UploadedFile::fake()->image('logo.jpg'),
            'kind' => 'not-a-kind',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['kind']);
});

test('store enforces the maximum number of references', function () {
    for ($i = 0; $i < BrandReferenceKind::MAX_REFERENCES; $i++) {
        $this->workspace->addMedia(UploadedFile::fake()->image("ref-{$i}.jpg"), 'brand_references');
    }

    $this->actingAs($this->user)
        ->postJson(route('app.workspace.brand-references.store'), [
            'photo' => UploadedFile::fake()->image('overflow.jpg'),
        ])
        ->assertUnprocessable();
});

test('search returns paginated references filtered by filename', function () {
    $this->workspace->addMedia(UploadedFile::fake()->image('sara-portrait.jpg'), 'brand_references');
    $this->workspace->addMedia(UploadedFile::fake()->image('logo.jpg'), 'brand_references');

    $this->actingAs($this->user)
        ->getJson(route('app.workspace.brand-references.search', ['search' => 'sara']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.original_filename', 'sara-portrait.jpg');
});

test('search filters by kind', function () {
    $this->workspace->addMedia(UploadedFile::fake()->image('logo.jpg'), 'brand_references', ['kind' => 'logo']);
    $this->workspace->addMedia(UploadedFile::fake()->image('face.jpg'), 'brand_references', ['kind' => 'face_closeup']);

    $this->actingAs($this->user)
        ->getJson(route('app.workspace.brand-references.search', ['kind' => 'logo']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.meta.kind', 'logo')
        ->assertJsonPath('unfiltered_total', 2);
});

test('an admin can update a reference label and kind', function () {
    $reference = $this->workspace->addMedia(UploadedFile::fake()->image('face.jpg'), 'brand_references');

    $this->actingAs($this->user)
        ->patchJson(route('app.workspace.brand-references.update', $reference), [
            'label' => 'Sara portrait',
            'kind' => 'face_closeup',
        ])
        ->assertOk()
        ->assertJsonPath('meta.label', 'Sara portrait')
        ->assertJsonPath('meta.kind', 'face_closeup');

    expect($reference->fresh()->meta['label'])->toBe('Sara portrait');
});

test('an admin can promote an asset to a reference', function () {
    $asset = $this->workspace->addMedia(UploadedFile::fake()->image('team.jpg'), 'assets');

    $this->actingAs($this->user)
        ->postJson(route('app.workspace.brand-references.from-asset'), [
            'asset_id' => $asset->id,
            'label' => 'Team photo',
        ])
        ->assertCreated()
        ->assertJsonPath('meta.label', 'Team photo');

    expect($this->workspace->getMedia('brand_references')->count())->toBe(1)
        ->and($this->workspace->getMedia('assets')->count())->toBe(1);
});

test('from-asset rejects assets from other workspaces', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherAsset = $otherWorkspace->addMedia(UploadedFile::fake()->image('theirs.jpg'), 'assets');

    $this->actingAs($this->user)
        ->postJson(route('app.workspace.brand-references.from-asset'), [
            'asset_id' => $otherAsset->id,
        ])
        ->assertUnprocessable();

    expect($this->workspace->getMedia('brand_references')->count())->toBe(0);
});

test('members cannot upload or update references but can view them', function () {
    $member = User::factory()->create(['account_id' => $this->user->account_id]);
    $this->workspace->members()->attach($member->id, ['role' => Role::Member->value]);
    $member->update(['current_workspace_id' => $this->workspace->id]);

    $this->actingAs($member)
        ->postJson(route('app.workspace.brand-references.store'), [
            'photo' => UploadedFile::fake()->image('face.jpg'),
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->getJson(route('app.workspace.brand-references.search'))
        ->assertOk();
});

test('chunked upload targets the brand_references collection', function () {
    $content = file_get_contents(__DIR__.'/../fixtures/1x1.png');
    $size = strlen($content);

    $this->actingAs($this->user)->call(
        'POST',
        route('app.assets.store-chunked'),
        [], [], [],
        [
            'HTTP_CONTENT_RANGE' => 'bytes 0-'.($size - 1).'/'.$size,
            'HTTP_X_FILE_NAME' => 'test.png',
            'HTTP_X_UPLOAD_ID' => (string) \Illuminate\Support\Str::uuid(),
            'HTTP_X_COLLECTION' => 'brand_references',
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ],
        $content,
    )
        ->assertSuccessful()
        ->assertJson(['done' => true]);

    expect($this->workspace->getMedia('brand_references')->count())->toBe(1);
});

test('users cannot delete reference photos belonging to other workspaces', function () {
    $otherWorkspace = Workspace::factory()->create();
    $otherMedia = $otherWorkspace->addMedia(UploadedFile::fake()->image('other.jpg'), 'brand_references');

    $this->actingAs($this->user)
        ->deleteJson(route('app.workspace.brand-references.destroy', $otherMedia))
        ->assertNotFound();

    expect(Media::find($otherMedia->id))->not->toBeNull();
});
