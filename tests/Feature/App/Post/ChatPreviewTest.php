<?php

declare(strict_types=1);

use App\Enums\PostPlatform\ContentType;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->telegram = SocialAccount::factory()->telegram()->create([
        'workspace_id' => $this->workspace->id,
    ]);
    $this->threads = SocialAccount::factory()->create([
        'workspace_id' => $this->workspace->id,
        'platform' => Platform::Threads,
    ]);

    $this->post = Post::factory()->for($this->workspace)->create([
        'user_id' => $this->user->id,
        'content' => 'Hello channel',
        'media' => [],
    ]);

    PostPlatform::factory()->for($this->post)->for($this->telegram, 'socialAccount')->create([
        'platform' => Platform::Telegram,
        'content_type' => ContentType::TelegramPost,
    ]);
    PostPlatform::factory()->for($this->post)->for($this->threads, 'socialAccount')->disabled()->create([
        'platform' => Platform::Threads,
        'content_type' => ContentType::ThreadsPost,
    ]);
});

test('chat preview returns content, media and every platform with sanitized text', function (): void {
    $response = $this->actingAs($this->user)->getJson(route('app.posts.chat-preview', $this->post));

    $response->assertOk();

    $platforms = $response->json('platforms');

    expect($response->json('content'))->toBe('Hello channel')
        ->and($response->json('media'))->toBeArray()
        ->and(collect($platforms)->pluck('platform')->all())->toContain('telegram', 'threads')
        ->and($response->json('contents'))->toBeArray()
        ->and($response->json('platform_content_types'))->toBeArray()
        ->and($response->json('platform_meta'))->toBeArray();

    $first = collect($platforms)->firstWhere('platform', 'telegram');

    expect($first['social_account']['username'])->toBe('mychannel')
        ->and($first)->toHaveKeys(['id', 'platform', 'platform_name', 'content_type', 'enabled'])
        ->and($first['enabled'])->toBeTrue()
        ->and(collect($platforms)->firstWhere('platform', 'threads')['enabled'])->toBeFalse();
});

test('chat preview sanitizes per platform without touching the stored post', function (): void {
    config()->set('trypost.platforms.x.defuse_links', true);

    $x = SocialAccount::factory()->x()->create(['workspace_id' => $this->workspace->id]);

    PostPlatform::factory()->for($this->post)->for($x, 'socialAccount')->x()->create();

    $this->post->update(['content' => 'Read https://example.com/post']);

    $response = $this->actingAs($this->user)->getJson(route('app.posts.chat-preview', $this->post));

    $response->assertOk();

    $platforms = collect($response->json('platforms'));
    $xPlatform = $platforms->firstWhere('platform', 'x');
    $contents = $response->json('contents');

    expect($xPlatform)->not->toBeNull()
        ->and($contents[$xPlatform['id']])->not->toContain('https://example.com/post')
        ->and($this->post->fresh()->content)->toBe('Read https://example.com/post');
});

test('chat preview is unavailable to guests and foreign workspaces', function (): void {
    $this->getJson(route('app.posts.chat-preview', $this->post))->assertUnauthorized();

    $other = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['user_id' => $other->id]);
    $other->update(['current_workspace_id' => $otherWorkspace->id]);

    $this->actingAs($other)->getJson(route('app.posts.chat-preview', $this->post))->assertNotFound();
});
