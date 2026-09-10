<?php

declare(strict_types=1);

use App\Enums\Ai\GenerationStatus;
use App\Enums\SocialAccount\Platform;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Ai\StreamPostCreation;
use App\Models\AiGeneration;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Bus::fake();

    [$this->user, $this->workspace] = actingAsWorkspaceUserWithRole(Role::Member);

    $this->account = SocialAccount::factory()->for($this->workspace)->create([
        'platform' => Platform::Threads,
    ]);
});

it('renders the create page with the workspace generation catalog and brand references', function (): void {
    $this->get(route('app.posts.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('posts/Create')
            ->has('catalog.formats')
            ->has('catalog.styles')
            ->has('catalog.languages')
            ->has('brandReferences')
            ->has('canManageBrandReferences'));
});

it('starts a generation and returns a creation id and channel', function (): void {
    $response = $this->postJson(route('app.posts.ai.start'), [
        'prompt' => 'Write a post about our new pricing page',
        'format' => 'threads_post',
        'style' => 'image_card',
        'image_count' => 1,
        'social_account_id' => $this->account->id,
    ]);

    $response->assertStatus(202);

    $creationId = $response->json('creation_id');

    expect($creationId)->not->toBeEmpty()
        ->and($response->json('channel'))->toBe("user.{$this->user->id}.ai-creation.{$creationId}");

    Bus::assertDispatched(StreamPostCreation::class);
});

it('rejects a format the workspace catalog does not offer', function (): void {
    $this->postJson(route('app.posts.ai.start'), [
        'prompt' => 'Write a post about our new pricing page',
        'format' => 'linkedin_post',
        'style' => 'image_card',
    ])->assertStatus(422)->assertJsonValidationErrors('format');
});

it('rejects a prompt shorter than the minimum', function (): void {
    $this->postJson(route('app.posts.ai.start'), [
        'prompt' => 'ab',
        'format' => 'threads_post',
        'style' => 'image_card',
    ])->assertStatus(422)->assertJsonValidationErrors('prompt');
});

it('resolves a generation by its creation id', function (): void {
    $creationId = (string) Str::uuid();

    $post = Post::factory()->for($this->workspace)->create();

    AiGeneration::query()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'creation_id' => $creationId,
        'status' => 'ready',
        'format' => 'threads_post',
        'template' => 'image_card',
        'image_expected' => 0,
        'image_done' => 0,
        'post_id' => $post->id,
    ]);

    $this->get(route('app.posts.ai.status', $creationId))
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        // The chat card's polling fallback resolves the ready post from these.
        ->assertJsonPath('post_id', $post->id)
        ->assertJsonPath('error', null);
});

it('does not resolve a generation from another workspace', function (): void {
    $creationId = (string) Str::uuid();

    AiGeneration::query()->create([
        'workspace_id' => Workspace::factory()->create()->id,
        'user_id' => $this->user->id,
        'creation_id' => $creationId,
        'status' => 'ready',
        'format' => 'threads_post',
        'template' => 'image_card',
        'image_expected' => 0,
        'image_done' => 0,
    ]);

    $this->get(route('app.posts.ai.status', $creationId))->assertNotFound();
});

it('starts a generation with a client-supplied creation_id', function (): void {
    $creationId = (string) Str::uuid();

    $response = $this->postJson(route('app.posts.ai.start'), [
        'creation_id' => $creationId,
        'prompt' => 'Write a post about our new pricing page',
        'format' => 'threads_post',
        'style' => 'image_card',
        'image_count' => 1,
        'social_account_id' => $this->account->id,
    ]);

    $response->assertStatus(202);
    expect($response->json('creation_id'))->toBe($creationId)
        ->and($response->json('channel'))->toBe("user.{$this->user->id}.ai-creation.{$creationId}");

    Bus::assertDispatched(StreamPostCreation::class, fn (StreamPostCreation $job) => $job->creationId === $creationId);
});

it('redirects to loading page if start receives X-Inertia header', function (): void {
    $response = $this->withHeader('X-Inertia', 'true')
        ->post(route('app.posts.ai.start'), [
            'prompt' => 'Write a post about our new pricing page',
            'format' => 'threads_post',
            'style' => 'image_card',
            'image_count' => 1,
            'social_account_id' => $this->account->id,
        ]);

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/posts/ai/');
});

it('loading page requires authentication', function (): void {
    auth()->logout();

    $this->get(route('app.posts.ai.loading', (string) Str::uuid()))
        ->assertRedirect(route('login'));
});

it('loading page renders the Inertia component with channel and query context', function (): void {
    $creationId = (string) Str::uuid();

    $this->get(route('app.posts.ai.loading', $creationId).'?images=5&format=instagram_carousel&prompt=Hello&style=tweet_card&apply_brand_visuals=0')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('posts/ai/Loading')
            ->where('creationId', $creationId)
            ->where('channel', "user.{$this->user->id}.ai-creation.{$creationId}")
            ->where('imageCount', 5)
            ->where('format', 'instagram_carousel')
            ->where('prompt', 'Hello')
            ->where('style', 'tweet_card')
            ->where('template', 'tweet_card')
            ->where('applyBrandVisuals', false)
            ->where('alreadyStarted', false)
        );
});

it('loading page rejects non-uuid creation ids', function (): void {
    $this->get('/posts/ai/not-a-uuid/loading')
        ->assertNotFound();
});

it('loading page redirects to edit if generation already completed', function (): void {
    $creationId = (string) Str::uuid();
    $post = Post::factory()->for($this->workspace)->create();

    AiGeneration::query()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->user->id,
        'creation_id' => $creationId,
        'status' => GenerationStatus::Ready,
        'post_id' => $post->id,
        'format' => 'threads_post',
        'template' => 'image_card',
        'image_expected' => 0,
        'image_done' => 0,
    ]);

    $this->get(route('app.posts.ai.loading', $creationId))
        ->assertRedirect(route('app.posts.edit', $post->id));
});
