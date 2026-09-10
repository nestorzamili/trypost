<?php

declare(strict_types=1);

use App\Ai\Agents\PostImageRegenerator;
use App\Enums\Ai\MediaRegenerationMode;
use App\Enums\SocialAccount\Platform;
use App\Jobs\Ai\RegeneratePostMediaImage;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;

test('job fallback source context uses post content when source_meta is missing', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'content' => "Headline with typo ECP\nBody line for fallback context.",
        'media' => [],
    ]);

    $job = new RegeneratePostMediaImage(
        workspaceId: $workspace->id,
        postId: $post->id,
        userId: $user->id,
        mediaId: 'media-ai-1',
        regenerationId: '0196f5ca-bf2e-7d15-9a22-5709ab10d6c9',
        instruction: 'Fix typo from ECP to ICP.',
        mode: MediaRegenerationMode::TextOnly,
    );

    $method = new ReflectionMethod(RegeneratePostMediaImage::class, 'buildSourceContext');
    $method->setAccessible(true);

    $context = $method->invoke($job, [], $post, $workspace);

    expect(data_get($context, 'title'))->toBe('Headline with typo ECP');
    expect((string) data_get($context, 'body'))->toContain('Body line for fallback context.');
    expect(data_get($context, 'keywords'))->toBeArray()->not->toBeEmpty();
    expect(data_get($context, 'width'))->toBe(1080);
    expect(data_get($context, 'height'))->toBe(1350);
});

test('merge structured copy keeps text unchanged for image_only mode', function () {
    $job = new RegeneratePostMediaImage(
        workspaceId: 'workspace-id',
        postId: 'post-id',
        userId: 'user-id',
        mediaId: 'media-id',
        regenerationId: 'regen-id',
        instruction: 'change image only',
        mode: MediaRegenerationMode::ImageOnly,
    );

    $method = new ReflectionMethod(RegeneratePostMediaImage::class, 'mergeStructuredCopy');
    $method->setAccessible(true);

    $baseContext = [
        'title' => 'Keep THIS Text',
        'body' => 'Body should remain exactly the same.',
        'keywords' => ['saas', 'dashboard'],
        'background_path' => 'ai-images/bg.webp',
        'language' => 'en',
        'width' => 1080,
        'height' => 1350,
    ];

    $structured = [
        'title' => 'Model tried to rewrite',
        'body' => 'Model tried to rewrite body',
        'keywords' => ['modern office', 'team'],
    ];

    $copy = $method->invoke($job, $baseContext, $structured, MediaRegenerationMode::ImageOnly);

    expect(data_get($copy, 'title'))->toBe($baseContext['title'])
        ->and(data_get($copy, 'body'))->toBe($baseContext['body'])
        ->and(data_get($copy, 'keywords'))->toBe(['modern office', 'team'])
        ->and(data_get($copy, 'regenerate_image'))->toBeTrue()
        ->and(data_get($copy, 'regenerate_text'))->toBeFalse()
        ->and(data_get($copy, 'change_mode'))->toBe('image_only');
});

test('regenerating an image keeps the current saved alt text, not a pre-render snapshot', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create([
        'user_id' => $user->id,
        'account_id' => $user->account_id,
    ]);

    Storage::fake();
    Storage::put('ai-images/regen.webp', 'fake-image-bytes');

    $targetMedia = Media::factory()->create([
        'mediable_type' => (new Workspace)->getMorphClass(),
        'mediable_id' => $workspace->id,
        'collection' => 'ai-generated',
    ]);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'media' => [[
            'id' => $targetMedia->id,
            'type' => 'image',
            'source' => 'ai',
            'path' => 'ai-images/old.webp',
            'url' => 'https://cdn.test/old.webp',
            'meta' => ['alt_text' => 'CURRENT alt saved during regen', 'slide_title' => 'Intro'],
        ]],
    ]);

    $job = new RegeneratePostMediaImage(
        workspaceId: $workspace->id,
        postId: $post->id,
        userId: $user->id,
        mediaId: $targetMedia->id,
        regenerationId: 'regen-id',
        instruction: 'image only tweak',
        mode: MediaRegenerationMode::ImageOnly,
    );

    // Snapshot captured before the async render carries a now-stale alt.
    $staleTarget = [
        'id' => $targetMedia->id,
        'type' => 'image',
        'source' => 'ai',
        'meta' => ['alt_text' => 'STALE alt from before regen'],
    ];

    $rendered = ['path' => 'ai-images/regen.webp', 'source_meta' => []];

    $method = new ReflectionMethod(RegeneratePostMediaImage::class, 'replaceMediaOnPost');
    $method->setAccessible(true);
    $method->invoke($job, $post, $staleTarget, $workspace, $rendered);

    expect(data_get($post->fresh()->media, '0.meta.alt_text'))->toBe('CURRENT alt saved during regen')
        ->and(data_get($post->fresh()->media, '0.meta.slide_title'))->toBe('Intro');
});

test('image_only regeneration generates a new visual while preserving image copy', function () {
    Storage::fake();
    $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    Image::fake([base64_encode($minimalPng)]);
    PostImageRegenerator::fake([[
        'title' => 'Ignored replacement title',
        'body' => 'Ignored replacement body',
        'keywords' => ['forest', 'sunrise'],
    ]]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id, 'account_id' => $user->account_id]);
    $account = SocialAccount::factory()->create(['workspace_id' => $workspace->id]);
    $targetMedia = Media::factory()->create(['mediable_type' => (new Workspace)->getMorphClass(), 'mediable_id' => $workspace->id]);
    Storage::put('ai-images/old-background.webp', $minimalPng);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'media' => [[
            'id' => $targetMedia->id,
            'path' => 'ai-images/old.webp',
            'url' => 'https://cdn.test/old.webp',
            'type' => 'image',
            'mime_type' => 'image/webp',
            'source' => 'ai',
            'source_meta' => [
                'title' => 'Keep this headline',
                'body' => 'Keep this body.',
                'keywords' => ['old', 'scene'],
                'background_path' => 'ai-images/old-background.webp',
                'width' => 1080,
                'height' => 1350,
            ],
        ]],
    ]);
    PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id, 'platform' => Platform::LinkedIn]);

    (new RegeneratePostMediaImage(
        workspaceId: $workspace->id,
        postId: $post->id,
        userId: $user->id,
        mediaId: $targetMedia->id,
        regenerationId: '0196f5ca-bf2e-7d15-9a22-5709ab10d6c9',
        instruction: 'Replace the background with a sunrise forest.',
        mode: MediaRegenerationMode::ImageOnly,
    ))->handle();

    $media = $post->fresh()->media[0];

    Image::assertGenerated(fn () => true);
    expect(data_get($media, 'id'))->not->toBe($targetMedia->id)
        ->and(data_get($media, 'source_meta.title'))->toBe('Keep this headline')
        ->and(data_get($media, 'source_meta.body'))->toBe('Keep this body.')
        ->and(data_get($media, 'source_meta.keywords'))->toBe(['forest', 'sunrise'])
        ->and(Storage::exists('ai-images/old-background.webp'))->toBeFalse()
        ->and(Media::query()->find($targetMedia->id))->toBeNull();
});

test('text_only regeneration reuses the current visual without generating an image', function () {
    Storage::fake();
    $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    Image::fake();
    PostImageRegenerator::fake([[
        'title' => 'Updated headline',
        'body' => 'Updated body.',
        'keywords' => ['ignored'],
    ]]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id, 'account_id' => $user->account_id]);
    $account = SocialAccount::factory()->create(['workspace_id' => $workspace->id]);
    $targetMedia = Media::factory()->create(['mediable_type' => (new Workspace)->getMorphClass(), 'mediable_id' => $workspace->id]);
    Storage::put('ai-images/current-background.webp', $minimalPng);

    $post = Post::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'media' => [[
            'id' => $targetMedia->id,
            'path' => 'ai-images/old.webp',
            'url' => 'https://cdn.test/old.webp',
            'type' => 'image',
            'mime_type' => 'image/webp',
            'source' => 'ai',
            'source_meta' => [
                'title' => 'Old headline',
                'body' => 'Old body.',
                'keywords' => ['current', 'scene'],
                'background_path' => 'ai-images/current-background.webp',
                'width' => 1080,
                'height' => 1350,
            ],
        ]],
    ]);
    PostPlatform::factory()->create(['post_id' => $post->id, 'social_account_id' => $account->id, 'platform' => Platform::LinkedIn]);

    (new RegeneratePostMediaImage(
        workspaceId: $workspace->id,
        postId: $post->id,
        userId: $user->id,
        mediaId: $targetMedia->id,
        regenerationId: '0196f5ca-bf2e-7d15-9a22-5709ab10d6c9',
        instruction: 'Update the text.',
        mode: MediaRegenerationMode::TextOnly,
    ))->handle();

    $media = $post->fresh()->media[0];

    Image::assertNothingGenerated();
    expect(data_get($media, 'source_meta.title'))->toBe('Updated headline')
        ->and(data_get($media, 'source_meta.body'))->toBe('Updated body.')
        ->and(data_get($media, 'source_meta.keywords'))->toBe(['current', 'scene'])
        ->and(data_get($media, 'source_meta.background_path'))->toBe('ai-images/current-background.webp')
        ->and(Storage::exists('ai-images/current-background.webp'))->toBeTrue();
});

test('merge structured copy keeps keywords unchanged for text_only mode', function () {
    $job = new RegeneratePostMediaImage(
        workspaceId: 'workspace-id',
        postId: 'post-id',
        userId: 'user-id',
        mediaId: 'media-id',
        regenerationId: 'regen-id',
        instruction: 'change text only',
        mode: MediaRegenerationMode::TextOnly,
    );

    $method = new ReflectionMethod(RegeneratePostMediaImage::class, 'mergeStructuredCopy');
    $method->setAccessible(true);

    $baseContext = [
        'title' => 'Old title',
        'body' => 'Old body',
        'keywords' => ['saas', 'dashboard'],
        'background_path' => 'ai-images/bg.webp',
        'language' => 'en',
        'width' => 1080,
        'height' => 1350,
    ];

    $structured = [
        'title' => 'New title',
        'body' => 'New body',
        'keywords' => ['ignored', 'for', 'text-only'],
    ];

    $copy = $method->invoke($job, $baseContext, $structured, MediaRegenerationMode::TextOnly);

    expect(data_get($copy, 'title'))->toBe('New title')
        ->and(data_get($copy, 'body'))->toBe('New body')
        ->and(data_get($copy, 'keywords'))->toBe($baseContext['keywords'])
        ->and(data_get($copy, 'regenerate_image'))->toBeFalse()
        ->and(data_get($copy, 'regenerate_text'))->toBeTrue()
        ->and(data_get($copy, 'change_mode'))->toBe('text_only');
});
