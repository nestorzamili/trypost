<?php

declare(strict_types=1);

use App\Ai\Tools\Post\RetryPostImagesTool;
use App\Enums\Ai\GenerationStatus;
use App\Enums\UserWorkspace\Role;
use App\Jobs\Ai\RenderPostImages;
use App\Models\AiGeneration;
use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Bus;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    Bus::fake();

    [$this->user, $this->workspace] = workspaceUserWithRole(Role::Member);

    $this->account = SocialAccount::factory()->for($this->workspace)->create([
        'platform' => 'telegram',
    ]);

    $this->post = Post::factory()->for($this->workspace)->create([
        'content' => 'Saved draft text',
        'creation_id' => 'call_retry',
    ]);

    $this->generation = AiGeneration::factory()->for($this->workspace)->for($this->user)->create([
        'creation_id' => 'call_retry',
        'status' => GenerationStatus::FailedImage,
        'format' => 'telegram_post',
        'template' => 'image_card',
        'social_account_id' => $this->account->id,
        'image_expected' => 1,
        'image_done' => 0,
        'post_id' => $this->post->id,
        'structured' => ['content' => 'Saved draft text'],
        'apply_brand_visuals' => false,
        'reference_media_ids' => [],
        'use_brand_references' => false,
        'error_phase' => 'image',
        'error' => 'Only 0 of 1 images could be rendered.',
    ]);

    $this->tool = new RetryPostImagesTool($this->workspace, $this->user);
});

test('retry resumes the same draft with its stored image settings', function (): void {
    $output = json_decode($this->tool->handle(new Request(['creation_id' => 'call_retry'])), true);

    expect($output['data']['creation_id'])->toBe('call_retry')
        ->and($output['data']['channel'])->toContain('call_retry')
        ->and($this->generation->fresh()->status)->toBe(GenerationStatus::TextReady)
        ->and($this->generation->fresh()->post_id)->toBe($this->post->id)
        ->and($this->generation->fresh()->error)->toBeNull();

    Bus::assertDispatched(RenderPostImages::class, function (RenderPostImages $job): bool {
        return $job->creationId === 'call_retry'
            && $job->workspaceId === $this->workspace->id
            && $job->applyBrandVisuals === false
            && $job->useBrandReferences === false;
    });

    expect(Post::query()->where('creation_id', 'call_retry')->count())->toBe(1);
});

test('retry refuses generations that are not failed on images', function (): void {
    foreach (
        [
            GenerationStatus::TextReady,
            GenerationStatus::ImageRunning,
            GenerationStatus::Ready,
            GenerationStatus::FailedText,
        ] as $status
    ) {
        $this->generation->update(['status' => $status]);

        $output = json_decode($this->tool->handle(new Request(['creation_id' => 'call_retry'])), true);

        expect($output)->toHaveKey('error');
    }

    Bus::assertNotDispatched(RenderPostImages::class);
});

test('retry resumes a generation stalled mid-flight past its window', function (): void {
    $this->generation->update([
        'status' => GenerationStatus::ImageRunning,
        'error' => null,
        'error_phase' => null,
    ]);
    $this->generation->forceFill(['updated_at' => now()->subMinutes(25)])->saveQuietly();

    $output = json_decode($this->tool->handle(new Request(['creation_id' => 'call_retry'])), true);

    expect($output['data']['creation_id'])->toBe('call_retry')
        ->and($this->generation->fresh()->status)->toBe(GenerationStatus::TextReady);

    Bus::assertDispatched(RenderPostImages::class);
});

test('retry still waits on a recently active generation', function (): void {
    $this->generation->update(['status' => GenerationStatus::ImageRunning]);

    $output = json_decode($this->tool->handle(new Request(['creation_id' => 'call_retry'])), true);

    expect($output)->toHaveKey('error')
        ->and($output['error'])->toContain('still running');

    Bus::assertNotDispatched(RenderPostImages::class);
});

test('retry refuses unknown generations and missing drafts', function (): void {
    $unknown = json_decode($this->tool->handle(new Request(['creation_id' => 'call_missing'])), true);

    expect($unknown)->toHaveKey('error');

    $this->post->delete();

    $gone = json_decode($this->tool->handle(new Request(['creation_id' => 'call_retry'])), true);

    expect($gone)->toHaveKey('error')
        ->and($gone['error'])->toContain('no longer exists');

    Bus::assertNotDispatched(RenderPostImages::class);
});
