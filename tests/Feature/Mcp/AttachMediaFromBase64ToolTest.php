<?php

declare(strict_types=1);

use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Mcp\Servers\TryPostServer;
use App\Mcp\Tools\Post\AttachMediaFromBase64Tool;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    Storage::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->socialAccount = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $this->post = Post::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
    ]);
});

test('agent can attach image to post via raw base64 string', function () {
    $file = UploadedFile::fake()->image('chart.png', 100, 100);
    $base64 = base64_encode(file_get_contents($file->getPathname()));

    TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromBase64Tool::class, [
            'post_id' => $this->post->id,
            'data' => $base64,
            'filename' => 'chart.png',
            'alt' => 'Growth chart',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('post.id', $this->post->id)
            ->has('post.media', 1)
            ->where('post.media.0.original_filename', 'chart.png')
            ->where('post.media.0.meta.alt_text', 'Growth chart')
            ->etc()
        );

    $this->post->refresh();
    expect($this->post->media)->toHaveCount(1);
});

test('agent can attach image to post via Data URI format', function () {
    $file = UploadedFile::fake()->image('infographic.jpg', 120, 120);
    $base64 = base64_encode(file_get_contents($file->getPathname()));
    $dataUri = "data:image/jpeg;base64,{$base64}";

    TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromBase64Tool::class, [
            'post_id' => $this->post->id,
            'data' => $dataUri,
            'filename' => 'infographic.jpg',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('post.id', $this->post->id)
            ->has('post.media', 1)
            ->where('post.media.0.original_filename', 'infographic.jpg')
            ->etc()
        );

    $this->post->refresh();
    expect($this->post->media)->toHaveCount(1);
});

test('rejects invalid base64 data', function () {
    TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromBase64Tool::class, [
            'post_id' => $this->post->id,
            'data' => '!!!not-valid-base64!!!',
        ])
        ->assertHasErrors(['Invalid base64 encoded media data.']);
});

test('rejects unsupported media MIME type', function () {
    $plainTextBase64 = base64_encode('Hello world plain text file');

    TryPostServer::actingAs($this->user)
        ->tool(AttachMediaFromBase64Tool::class, [
            'post_id' => $this->post->id,
            'data' => $plainTextBase64,
            'filename' => 'test.txt',
        ])
        ->assertHasErrors();
});

test('viewer role cannot attach media via base64', function () {
    $viewer = User::factory()->create(['account_id' => $this->user->account_id]);
    $this->workspace->members()->attach($viewer->id, ['role' => Role::Viewer->value]);
    $viewer->update(['current_workspace_id' => $this->workspace->id]);

    $file = UploadedFile::fake()->image('chart.png', 100, 100);
    $base64 = base64_encode(file_get_contents($file->getPathname()));

    TryPostServer::actingAs($viewer)
        ->tool(AttachMediaFromBase64Tool::class, [
            'post_id' => $this->post->id,
            'data' => $base64,
        ])
        ->assertHasErrors(['Not authorized to update this post.']);
});
