<?php

declare(strict_types=1);

use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Enums\Post\CreatedVia;
use App\Enums\PostPlatform\ContentType;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Ai\RenderPostImages;
use App\Jobs\Ai\StreamPostCreation;
use App\Jobs\SendNotification;
use App\Models\AiGeneration;
use App\Models\BrandVariant;
use App\Models\PostPlatform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Image;

beforeEach(function () {
    Bus::fake();
    Storage::fake();
    Image::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['user_id' => $this->user->id]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Member->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);

    $this->account = SocialAccount::factory()->instagram()->create([
        'workspace_id' => $this->workspace->id,
    ]);
});

function runStreamPostCreation(string $format, SocialAccount $account, int $imageCount, string $template = 'image_card'): string
{
    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: $account->workspace->user_id,
        creationId: $creationId,
        workspaceId: $account->workspace_id,
        format: $format,
        socialAccountId: $account->id,
        imageCount: $imageCount,
        prompt: 'Five tips about productivity',
        template: $template,
    ))->handle();

    (new RenderPostImages(
        userId: $account->workspace->user_id,
        creationId: $creationId,
        workspaceId: $account->workspace_id,
    ))->handle();

    return $creationId;
}

test('AI carousel generation stores the post as an instagram feed, never instagram_carousel', function () {
    PostContentGenerator::fake([[
        'caption' => 'Swipe to see the tips',
        'slides' => [
            ['title' => 'Tip 1', 'body' => 'First tip', 'image_keywords' => []],
            ['title' => 'Tip 2', 'body' => 'Second tip', 'image_keywords' => []],
        ],
    ]]);
    PostContentHumanizer::fake([[
        'caption' => 'Swipe to see the tips',
        'slides' => [
            ['title' => 'Tip 1', 'body' => 'First tip'],
            ['title' => 'Tip 2', 'body' => 'Second tip'],
        ],
    ]]);

    runStreamPostCreation('instagram_carousel', $this->account, 2, 'image_card');

    $platform = PostPlatform::where('social_account_id', $this->account->id)->firstOrFail();

    expect($platform->content_type)->toBe(ContentType::InstagramFeed);
    expect($platform->meta['aspect_ratio'] ?? null)->toBe('4:5');
});

test('AI single feed generation stores the post as an instagram feed', function () {
    PostContentGenerator::fake([[
        'content' => 'A single productivity tip',
        'image_title' => 'Tip',
        'image_body' => 'Do less',
        'image_keywords' => [],
    ]]);
    PostContentHumanizer::fake([[
        'content' => 'A single productivity tip',
        'image_title' => 'Tip',
        'image_body' => 'Do less',
    ]]);

    runStreamPostCreation('instagram_feed', $this->account, 0);

    $platform = PostPlatform::where('social_account_id', $this->account->id)->firstOrFail();

    expect($platform->content_type)->toBe(ContentType::InstagramFeed);
});

test('tweet_card template stores the tweet_text as post content and attaches a media item', function () {
    PostContentGenerator::fake([[
        'tweet_text' => 'Hello world\n\nSecond para.',
    ]]);

    $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    Image::fake([base64_encode($minimalPng)]);

    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
        format: 'x_post',
        socialAccountId: $this->account->id,
        imageCount: 1,
        prompt: 'A punchy take on productivity',
        template: 'tweet_card',
    ))->handle();

    (new RenderPostImages(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
    ))->handle();

    $post = $this->user->currentWorkspace->posts()->latest()->first();

    expect($post->content)->toBe('Hello world\n\nSecond para.')
        ->and($post->media)->toHaveCount(1)
        ->and($post->created_via)->toBe(CreatedVia::Web);

    $platform = PostPlatform::where('social_account_id', $this->account->id)->firstOrFail();

    expect($platform->content_type)->toBe(ContentType::XPost);

    PostContentHumanizer::assertNeverPrompted();
});

test('tweet_card carousel produces caption as content and N media items without calling AI image client', function () {
    PostContentGenerator::fake([[
        'caption' => 'A carousel of punchy takes',
        'slides' => [
            ['tweet_text' => 'First take on productivity.'],
            ['tweet_text' => 'Second take on deep work.'],
        ],
    ]]);

    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
        format: 'instagram_carousel',
        socialAccountId: $this->account->id,
        imageCount: 2,
        prompt: 'Punchy productivity takes',
        template: 'tweet_card',
    ))->handle();

    (new RenderPostImages(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
    ))->handle();

    $post = $this->user->currentWorkspace->posts()->latest()->first();

    expect($post->content)->toBe('A carousel of punchy takes')
        ->and($post->media)->toHaveCount(2);

    $platform = PostPlatform::where('social_account_id', $this->account->id)->firstOrFail();

    expect($platform->content_type)->toBe(ContentType::InstagramFeed);

    Image::assertNothingGenerated();

    PostContentHumanizer::assertNeverPrompted();
});

test('tweet_card_image single stores tweet_text as content, attaches media, and calls AI image client', function () {
    PostContentGenerator::fake([[
        'tweet_text' => 'Productivity is a mindset.',
        'image_keywords' => ['laptop', 'desk', 'morning'],
    ]]);

    $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    Image::fake([base64_encode($minimalPng)]);

    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
        format: 'x_post',
        socialAccountId: $this->account->id,
        imageCount: 1,
        prompt: 'A punchy take on productivity',
        template: 'tweet_card_image',
    ))->handle();

    (new RenderPostImages(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
    ))->handle();

    $post = $this->user->currentWorkspace->posts()->latest()->first();

    expect($post->content)->toBe('Productivity is a mindset.')
        ->and($post->media)->toHaveCount(1);

    $platform = PostPlatform::where('social_account_id', $this->account->id)->firstOrFail();
    expect($platform->content_type)->toBe(ContentType::XPost);

    PostContentHumanizer::assertNeverPrompted();

    Image::assertGenerated(fn () => true);
});

test('tweet_card_image carousel stores caption and N media items each with an AI background', function () {
    PostContentGenerator::fake([[
        'caption' => 'Carousel about deep work',
        'slides' => [
            ['tweet_text' => 'First take.', 'image_keywords' => ['coffee', 'focus']],
            ['tweet_text' => 'Second take.', 'image_keywords' => ['notebook', 'pen']],
        ],
    ]]);

    $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    Image::fake([base64_encode($minimalPng), base64_encode($minimalPng)]);

    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
        format: 'instagram_carousel',
        socialAccountId: $this->account->id,
        imageCount: 2,
        prompt: 'Deep work carousel',
        template: 'tweet_card_image',
    ))->handle();

    (new RenderPostImages(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
    ))->handle();

    $post = $this->user->currentWorkspace->posts()->latest()->first();

    expect($post->content)->toBe('Carousel about deep work')
        ->and($post->media)->toHaveCount(2);

    $platform = PostPlatform::where('social_account_id', $this->account->id)->firstOrFail();
    expect($platform->content_type)->toBe(ContentType::InstagramFeed);

    PostContentHumanizer::assertNeverPrompted();

    Image::assertGenerated(fn () => true);
});

test('telegram_post text-only generation stores the caption with no media', function () {
    PostContentGenerator::fake([[
        'content' => 'Channel update for the week',
        'image_title' => 'Weekly update',
        'image_body' => 'Here is what shipped',
        'image_keywords' => [],
    ]]);
    PostContentHumanizer::fake([[
        'content' => 'Channel update for the week',
        'image_title' => 'Weekly update',
        'image_body' => 'Here is what shipped',
    ]]);

    $telegram = SocialAccount::factory()->telegram()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
        format: 'telegram_post',
        socialAccountId: $telegram->id,
        imageCount: 0,
        prompt: 'Weekly channel update',
        template: 'image_card',
    ))->handle();

    $post = $this->workspace->posts()->where('creation_id', $creationId)->firstOrFail();

    expect($post->content)->toBe('Channel update for the week')
        ->and($post->media)->toHaveCount(0);

    $platform = PostPlatform::where('social_account_id', $telegram->id)->firstOrFail();

    expect($platform->content_type)->toBe(ContentType::TelegramPost);
});

test('telegram_post single-image generation attaches one rendered image', function () {
    PostContentGenerator::fake([[
        'content' => 'Channel update with a visual',
        'image_title' => 'Big news',
        'image_body' => 'Read the update below',
        'image_keywords' => ['announcement', 'megaphone'],
    ]]);
    PostContentHumanizer::fake([[
        'content' => 'Channel update with a visual',
        'image_title' => 'Big news',
        'image_body' => 'Read the update below',
    ]]);

    $minimalPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    Image::fake([base64_encode($minimalPng)]);

    $telegram = SocialAccount::factory()->telegram()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
        format: 'telegram_post',
        socialAccountId: $telegram->id,
        imageCount: 1,
        prompt: 'Channel update with visual',
        template: 'image_card',
    ))->handle();

    (new RenderPostImages(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
    ))->handle();

    $post = $this->workspace->posts()->where('creation_id', $creationId)->firstOrFail();

    expect($post->content)->toBe('Channel update with a visual')
        ->and($post->media)->toHaveCount(1);

    $platform = PostPlatform::where('social_account_id', $telegram->id)->firstOrFail();

    expect($platform->content_type)->toBe(ContentType::TelegramPost);

    Image::assertGenerated(fn () => true);
});

test('the humanizer is given the same platform context as the generator so the rewrite honours the character cap', function () {
    PostContentGenerator::fake([[
        'content' => 'A single productivity tip',
        'image_title' => 'Tip',
        'image_body' => 'Do less',
        'image_keywords' => [],
    ]]);
    PostContentHumanizer::fake([[
        'content' => 'A single productivity tip',
        'image_title' => 'Tip',
        'image_body' => 'Do less',
    ]]);

    runStreamPostCreation('instagram_feed', $this->account, 0);

    PostContentGenerator::assertPrompted(fn ($prompt) => $prompt->agent->platformContext === 'instagram_feed');
    PostContentHumanizer::assertPrompted(fn ($prompt) => $prompt->agent->platformContext === 'instagram_feed');
});

test('the chosen language is persisted on the generation', function () {
    PostContentGenerator::fake([[
        'content' => 'Uma dica de produtividade',
        'image_title' => 'Dica',
        'image_body' => 'Faça menos',
        'image_keywords' => [],
    ]]);
    PostContentHumanizer::fake([[
        'content' => 'Uma dica de produtividade',
        'image_title' => 'Dica',
        'image_body' => 'Faça menos',
    ]]);

    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
        format: 'instagram_feed',
        socialAccountId: $this->account->id,
        imageCount: 0,
        prompt: 'Uma dica de produtividade',
        template: 'image_card',
        languageCode: 'pt-BR',
    ))->handle();

    $generation = AiGeneration::where('creation_id', $creationId)->firstOrFail();

    expect($generation->language_code)->toBe('pt-BR');
});

function ptBrTextOnlyGeneration(): string
{
    PostContentGenerator::fake([[
        'content' => 'Uma dica de produtividade',
        'image_title' => 'Dica',
        'image_body' => 'Faça menos',
        'image_keywords' => [],
    ]]);
    PostContentHumanizer::fake([[
        'content' => 'Uma dica de produtividade',
        'image_title' => 'Dica',
        'image_body' => 'Faça menos',
    ]]);

    test()->workspace->update(['content_language' => 'en']);

    BrandVariant::factory()->for(test()->workspace)->create([
        'language_code' => 'pt-BR',
        'label' => 'Português (Brasil)',
    ]);

    $creationId = (string) Str::uuid();

    (new StreamPostCreation(
        userId: test()->user->id,
        creationId: $creationId,
        workspaceId: test()->workspace->id,
        format: 'instagram_feed',
        socialAccountId: test()->account->id,
        imageCount: 0,
        prompt: 'Uma dica de produtividade',
        template: 'image_card',
        languageCode: 'pt-BR',
    ))->handle();

    return $creationId;
}

test('the ready notification is written in the language the post was generated in', function () {
    ptBrTextOnlyGeneration();

    Bus::assertDispatched(SendNotification::class, function (SendNotification $job): bool {
        return $job->title === trans('notifications.post_ready.title', [], 'pt-BR');
    });
});

test('the image phase keeps the generation language when the job carries none', function () {
    $creationId = ptBrTextOnlyGeneration();

    (new RenderPostImages(
        userId: $this->user->id,
        creationId: $creationId,
        workspaceId: $this->workspace->id,
    ))->handle();

    $notifications = Bus::dispatched(SendNotification::class);

    expect($notifications)->not->toBeEmpty()
        ->and($notifications->every(
            fn (SendNotification $job): bool => $job->title === trans('notifications.post_ready.title', [], 'pt-BR'),
        ))->toBeTrue();
});
