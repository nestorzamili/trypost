<?php

declare(strict_types=1);

use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\BrandReferencePhoto\DeleteBrandReferencePhotoTool;
use App\Mcp\Tools\BrandReferencePhoto\ListBrandReferencePhotosTool;
use App\Mcp\Tools\BrandReferencePhoto\RequestBrandReferencePhotoUploadTool;
use App\Mcp\Tools\BrandReferencePhoto\UpdateBrandReferencePhotoTool;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    Storage::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

test('lists only brand reference photos from the current workspace', function () {
    $reference = $this->workspace->addMedia(
        UploadedFile::fake()->image('reference.jpg'),
        'brand_references',
        ['label' => 'Warm editorial lighting'],
    );
    $this->workspace->addMedia(UploadedFile::fake()->image('asset.jpg'), 'assets');
    $otherWorkspace = Workspace::factory()->create();
    $otherWorkspace->addMedia(UploadedFile::fake()->image('other.jpg'), 'brand_references');

    TryPostServer::actingAs($this->user)
        ->tool(ListBrandReferencePhotosTool::class, [])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($reference) {
            $json->has('brand_reference_photos', 1, function (AssertableJson $photo) use ($reference) {
                $photo->where('id', $reference->id)
                    ->where('label', 'Warm editorial lighting')
                    ->hasAll(['original_filename', 'mime_type', 'size', 'url', 'created_at', 'updated_at'])
                    ->missing('path');
            });
        });
});

test('requests a signed upload URL that creates a brand reference photo', function () {
    $uploadUrl = null;

    TryPostServer::actingAs($this->user)
        ->tool(RequestBrandReferencePhotoUploadTool::class, ['label' => 'Product close-up'])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use (&$uploadUrl) {
            $json->where('field_name', 'media')
                ->hasAll(['upload_token', 'upload_url', 'expires_at', 'max_bytes']);
            $uploadUrl = $json->toArray()['upload_url'];
        });

    $this->post($uploadUrl, ['media' => UploadedFile::fake()->image('product.png')])
        ->assertCreated()
        ->assertJsonPath('label', 'Product close-up');

    $media = Media::query()->sole();

    expect($media->collection)->toBe('brand_references')
        ->and($media->mediable_id)->toBe($this->workspace->id)
        ->and($media->meta)->toMatchArray(['label' => 'Product close-up']);
});

test('rejects non-image Brand Reference Photo uploads', function () {
    $uploadUrl = null;

    TryPostServer::actingAs($this->user)
        ->tool(RequestBrandReferencePhotoUploadTool::class, [])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use (&$uploadUrl) {
            $json->etc();
            $uploadUrl = $json->toArray()['upload_url'];
        });

    $this->postJson($uploadUrl, [
        'media' => UploadedFile::fake()->create('reference.pdf', 10, 'application/pdf'),
    ])->assertUnprocessable();

    expect(Media::query()->exists())->toBeFalse();
});

test('updates a Brand Reference Photo label', function () {
    $reference = $this->workspace->addMedia(UploadedFile::fake()->image('reference.jpg'), 'brand_references');

    TryPostServer::actingAs($this->user)
        ->tool(UpdateBrandReferencePhotoTool::class, [
            'media_id' => $reference->id,
            'label' => 'Use the blue background',
        ])
        ->assertOk()
        ->assertStructuredContent(function (AssertableJson $json) use ($reference) {
            $json->where('id', $reference->id)
                ->where('label', 'Use the blue background')
                ->etc();
        });

    expect($reference->fresh()->meta)->toMatchArray(['label' => 'Use the blue background']);
});

test('cannot update or delete Brand Reference Photos from another workspace', function () {
    $otherWorkspace = Workspace::factory()->create();
    $reference = $otherWorkspace->addMedia(UploadedFile::fake()->image('other.jpg'), 'brand_references');

    TryPostServer::actingAs($this->user)
        ->tool(UpdateBrandReferencePhotoTool::class, [
            'media_id' => $reference->id,
            'label' => 'Hacked',
        ])
        ->assertHasErrors(['Brand Reference Photo not found.']);

    TryPostServer::actingAs($this->user)
        ->tool(DeleteBrandReferencePhotoTool::class, ['media_id' => $reference->id])
        ->assertHasErrors(['Brand Reference Photo not found.']);
});

test('deletes a Brand Reference Photo', function () {
    $reference = $this->workspace->addMedia(UploadedFile::fake()->image('reference.jpg'), 'brand_references');

    TryPostServer::actingAs($this->user)
        ->tool(DeleteBrandReferencePhotoTool::class, ['media_id' => $reference->id])
        ->assertOk()
        ->assertStructuredContent(['deleted' => true]);

    expect(Media::find($reference->id))->toBeNull();
});
