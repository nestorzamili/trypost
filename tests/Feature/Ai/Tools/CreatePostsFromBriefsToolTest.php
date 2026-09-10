<?php

declare(strict_types=1);

use App\Ai\Agents\PostBriefRefiner;
use App\Ai\Tools\Post\CreatePostsFromBriefsTool;
use App\Enums\Post\CreatedVia;
use App\Enums\Post\Status as PostStatus;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\WorkspaceLabel;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    [$this->user, $this->workspace] = workspaceUserWithRole(Role::Member);
    $this->tool = new CreatePostsFromBriefsTool($this->workspace, $this->user);
});

it('is named create_posts_from_briefs', function (): void {
    expect($this->tool->name())->toBe('create_posts_from_briefs');
});

it('rejects when useAi is forbidden', function (): void {
    config()->set('trypost.self_hosted', false);
    $this->workspace->account->update([
        'trial_ends_at' => now()->subDay(),
    ]);

    $output = json_decode($this->tool->handle(new Request([
        'briefs' => [
            ['text' => 'Some content idea'],
        ],
    ])), true);

    expect($output)->toHaveKey('error')
        ->and($output['error'])->toBe(__('chat.tools.forbidden'));
});

it('requires at least one non-empty brief', function (): void {
    config()->set('trypost.self_hosted', true);

    $output = json_decode($this->tool->handle(new Request([
        'briefs' => [
            ['text' => '   '],
        ],
    ])), true);

    expect($output)->toHaveKey('error')
        ->and($output['error'])->toBe('Provide at least one brief with non-empty text.');
});

it('refines each brief and creates draft posts with the Content Brief label', function (): void {
    config()->set('trypost.self_hosted', true);
    PostBriefRefiner::fake(['Refined post one.', 'Refined post two.']);

    $output = json_decode($this->tool->handle(new Request([
        'briefs' => [
            ['text' => 'Topic: Product launch, Audience: Devs'],
            ['text' => 'Topic: New feature, Audience: Designers'],
        ],
    ])), true);

    expect($output)->toHaveKey('data')
        ->and($output['data']['created_count'])->toBe(2)
        ->and($output['data']['failed_count'])->toBe(0)
        ->and($output['data']['label'])->toBe('Content Brief')
        ->and($output['data']['post_ids'])->toHaveCount(2);

    $posts = Post::where('workspace_id', $this->workspace->id)->get();
    expect($posts)->toHaveCount(2);
    expect($posts->pluck('content')->all())->toEqualCanonicalizing([
        'Refined post one.',
        'Refined post two.',
    ]);
    expect($posts->every(fn (Post $post): bool => $post->created_via === CreatedVia::Import))->toBeTrue();
    expect($posts->every(fn (Post $post): bool => $post->status === PostStatus::Draft))->toBeTrue();

    $label = WorkspaceLabel::where('workspace_id', $this->workspace->id)
        ->where('name', 'Content Brief')
        ->first();
    expect($label)->not->toBeNull();
    expect($label->color)->toBe('#7c3aed');

    foreach ($posts as $post) {
        expect($post->labels()->where('workspace_labels.id', $label->id)->exists())->toBeTrue();
    }
});

it('reuses existing Content Brief label without duplicating', function (): void {
    config()->set('trypost.self_hosted', true);
    PostBriefRefiner::fake(['Refined post.']);

    $existingLabel = $this->workspace->labels()->create([
        'name' => 'Content Brief',
        'color' => '#10b981',
    ]);

    $output = json_decode($this->tool->handle(new Request([
        'briefs' => [
            ['text' => 'Some brief'],
        ],
    ])), true);

    expect($output['data']['created_count'])->toBe(1);
    expect($this->workspace->labels()->where('name', 'Content Brief')->count())->toBe(1);

    $post = Post::find($output['data']['post_ids'][0]);
    expect($post->labels()->where('workspace_labels.id', $existingLabel->id)->exists())->toBeTrue();
});

it('caps briefs at 50 per call', function (): void {
    config()->set('trypost.self_hosted', true);

    $fakes = [];
    $payloadBriefs = [];
    for ($i = 1; $i <= 55; $i++) {
        $fakes[] = "Refined {$i}";
        $payloadBriefs[] = ['text' => "Brief {$i}"];
    }
    PostBriefRefiner::fake($fakes);

    $output = json_decode($this->tool->handle(new Request([
        'briefs' => $payloadBriefs,
    ])), true);

    expect($output['data']['created_count'])->toBe(50);
    expect(Post::where('workspace_id', $this->workspace->id)->count())->toBe(50);
});

it('reports and counts failed briefs while still creating successful ones', function (): void {
    Exceptions::fake();
    config()->set('trypost.self_hosted', true);

    PostBriefRefiner::fake([
        'Refined post one.',
        fn () => throw new RuntimeException('AI service unavailable.'),
    ]);

    $output = json_decode($this->tool->handle(new Request([
        'briefs' => [
            ['text' => 'Valid brief one'],
            ['text' => 'Failing brief two'],
        ],
    ])), true);

    expect($output['data']['created_count'])->toBe(1)
        ->and($output['data']['failed_count'])->toBe(1);

    expect(Post::where('workspace_id', $this->workspace->id)->count())->toBe(1);
    Exceptions::assertReported(RuntimeException::class);
});
