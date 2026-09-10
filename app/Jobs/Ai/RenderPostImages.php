<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Ai\Templates\AiTemplateRegistry;
use App\Ai\Templates\TemplateContext;
use App\Enums\Ai\GenerationFailure;
use App\Enums\Ai\GenerationStatus;
use App\Enums\Media\BrandReferenceKind;
use App\Enums\Media\Source;
use App\Enums\Notification\Channel as NotificationChannel;
use App\Enums\Notification\Type as NotificationType;
use App\Enums\PostPlatform\ContentType;
use App\Events\Ai\PostCreationProgress;
use App\Events\Ai\PostCreationReady;
use App\Jobs\SendNotification;
use App\Models\AiGeneration;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Image phase of AI post generation (image model only).
 *
 * Dispatched by {@see StreamPostCreation} after the text phase created the
 * draft post and persisted the structured slides on the generation. Renders
 * every image via the template, replaces the post media idempotently so a
 * retry never duplicates, and marks the generation ready only when the
 * expected image count was actually produced.
 */
class RenderPostImages implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 990;

    /**
     * Retry a transient image-phase failure. Safe: per-slide media is persisted
     * (slide_media) so a retry resumes instead of re-rendering/re-billing, and
     * replaceAiMedia merges idempotently without clobbering user edits.
     */
    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function __construct(
        public string $userId,
        public string $creationId,
        public string $workspaceId,
        public bool $applyBrandVisuals = true,
        public array $referenceMediaIds = [],
        public bool $useBrandReferences = true,
        public ?string $languageCode = null,
    ) {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return "{$this->userId}:{$this->creationId}:images";
    }

    public function failed(?Throwable $exception): void
    {
        $generation = AiGeneration::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('creation_id', $this->creationId)
            ->first();

        if ($generation === null || $generation->status->isTerminal()) {
            return;
        }

        $locale = $generation->language_code;
        $kind = $exception ? GenerationFailure::fromThrowable($exception, GenerationFailure::ImageNone) : GenerationFailure::ImageNone;

        $generation->update([
            'status' => GenerationStatus::FailedImage,
            'error_phase' => 'image',
            'error' => $kind->message($locale),
        ]);

        Log::error('RenderPostImages failed', [
            'creation_id' => $this->creationId,
            'error' => $exception?->getMessage(),
        ]);

        PostCreationReady::dispatch($this->userId, $this->creationId, $generation->post_id, $generation->error);
    }

    public function handle(): void
    {
        $generation = AiGeneration::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('creation_id', $this->creationId)
            ->firstOrFail();

        if ($generation->status->isTerminal()) {
            return;
        }

        $workspace = Workspace::findOrFail($this->workspaceId);
        $workspace->loadMissing('brandVariants');
        $post = $workspace->posts()->whereKey($generation->post_id)->firstOrFail();
        $socialAccount = $generation->social_account_id !== null
            ? SocialAccount::find($generation->social_account_id)
            : null;

        $structured = $generation->structured ?? [];
        $style = app(AiTemplateRegistry::class)->find($generation->template);
        $brand = $workspace->resolvedBrand($generation->language_code ?? $this->languageCode);

        $referenceImages = [];
        $referenceKinds = [];
        if ($this->referenceMediaIds !== []) {
            $refMedia = $workspace->media()
                ->whereIn('id', $this->referenceMediaIds)
                ->get();
        } elseif ($this->useBrandReferences) {
            $refMedia = $workspace->getMedia('brand_references')->get();
        } else {
            $refMedia = collect();
        }

        if ($refMedia->isNotEmpty()) {
            // Order faces/full-body first (identity matters most), then logo,
            // product, style; cap at the model's reference limit. Carry each
            // reference's kind so the image prompt treats a logo as a logo, not
            // a face to preserve.
            $kindPriority = [
                'face_closeup' => 0,
                'full_body' => 1,
                'logo' => 2,
                'product' => 3,
                'style' => 4,
                'other' => 5,
            ];

            $ordered = $refMedia
                ->map(fn ($item) => [
                    'path' => $item->path,
                    'kind' => (string) (data_get($item->meta, 'kind') ?? 'other'),
                ])
                ->sortBy(fn (array $ref) => $kindPriority[$ref['kind']] ?? 99)
                ->take(BrandReferenceKind::MAX_REFERENCES)
                ->values();

            $referenceImages = $ordered->pluck('path')->all();
            $referenceKinds = $ordered->pluck('kind')->all();
        }

        $isCarousel = $generation->format === ContentType::CAROUSEL_FORMAT;

        $context = new TemplateContext(
            workspace: $workspace,
            socialAccount: $socialAccount,
            format: $generation->format,
            imageCount: $generation->image_expected,
            isCarousel: $isCarousel,
            applyBrandVisuals: $this->applyBrandVisuals,
            languageCode: $brand->languageCode,
            brand: $brand,
            referenceImages: $referenceImages,
            referenceKinds: $referenceKinds,
        );

        // Resume: reuse slides already rendered on a prior attempt so a retry
        // never re-renders or re-bills them (and never overwrites the draft
        // with a fresh full set). Keyed by slide index.
        if ($isCarousel && is_array($generation->slide_media)) {
            $context->existingSlideMedia = array_filter(
                $generation->slide_media,
                fn ($item): bool => is_array($item) && $item !== [],
            );
        }

        $generation->update(['status' => GenerationStatus::ImageRunning]);

        PostCreationProgress::dispatch(
            userId: $this->userId,
            creationId: $this->creationId,
            phase: GenerationStatus::ImageRunning,
            postId: $post->id,
            imageDone: 0,
            imageExpected: $generation->image_expected,
        );

        try {
            $generated = $style->assemble($structured, $context);
            $media = $generated->media;
            $done = count($media);

            // Persist the per-slide map so a later retry resumes instead of
            // re-rendering. Only meaningful for carousels; single/tweet paths
            // leave it null.
            if ($isCarousel && $context->renderedSlideMedia !== []) {
                $generation->update(['slide_media' => $context->renderedSlideMedia]);
            }

            // Idempotent merge (locked read-modify-write): replace only the
            // AI-generated slice, preserving any media the user attached to the
            // draft between attempts, and delete AI media/files this run drops.
            $this->replaceAiMedia($post, $media);

            $generation->update(['image_done' => $done]);

            if ($generation->image_expected > 0 && $done < $generation->image_expected) {
                $generation->update([
                    'status' => GenerationStatus::FailedImage,
                    'error_phase' => 'image',
                    'error' => GenerationFailure::ImagePartial->message($brand->languageCode, [
                        'done' => $done,
                        'expected' => $generation->image_expected,
                    ]),
                ]);

                PostCreationReady::dispatch($this->userId, $this->creationId, $post->id, (string) $generation->error);

                return;
            }

            $generation->update([
                'status' => GenerationStatus::Ready,
                'error_phase' => null,
                'error' => null,
            ]);

            PostCreationReady::dispatch(
                userId: $this->userId,
                creationId: $this->creationId,
                postId: $post->id,
            );

            $user = User::findOrFail($this->userId);

            SendNotification::dispatch(
                user: $user,
                workspaceId: $workspace->id,
                type: NotificationType::PostReady,
                channel: NotificationChannel::InApp,
                title: trans('notifications.post_ready.title', [], $brand->languageCode),
                body: trans('notifications.post_ready.body', [], $brand->languageCode),
                data: ['post_id' => $post->id],
            );
        } catch (Throwable $e) {
            // FailedImage is terminal and would short-circuit a retry's handle().
            // Only write it on the final attempt; earlier attempts stay
            // non-terminal so the queued retry re-runs and resumes via
            // slide_media. failed() writes the terminal status after the last.
            if ($this->attempts() >= $this->tries) {
                $generation->update([
                    'status' => GenerationStatus::FailedImage,
                    'error_phase' => 'image',
                    'error' => GenerationFailure::fromThrowable($e, GenerationFailure::ImageNone)->message($brand->languageCode),
                ]);
            }

            Log::error('RenderPostImages failed', [
                'creation_id' => $this->creationId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Idempotently set the post's AI-generated media to $newAiMedia, preserving
     * any non-AI media the user attached to the draft between attempts, and
     * deleting the Media rows + storage files of AI media this run drops.
     *
     * A locked read-modify-write (mirroring RegeneratePostMediaImage) so a
     * concurrent edit is not clobbered and a retry never duplicates or leaks.
     *
     * @param  array<int, array<string, mixed>>  $newAiMedia
     */
    private function replaceAiMedia(Post $post, array $newAiMedia): void
    {
        $newIds = collect($newAiMedia)->pluck('id')->filter()->all();

        DB::transaction(function () use ($post, $newAiMedia, $newIds): void {
            $fresh = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();
            $current = collect($fresh->media ?? []);

            // AI media present before this run but NOT in the new set — orphaned.
            $orphans = $current
                ->filter(fn ($item): bool => data_get($item, 'source') === Source::Ai->value
                    && ! in_array(data_get($item, 'id'), $newIds, true));

            // Keep everything the user added (non-AI), then append the new AI set.
            $userMedia = $current
                ->reject(fn ($item): bool => data_get($item, 'source') === Source::Ai->value)
                ->values()
                ->all();

            $fresh->update(['media' => array_merge($userMedia, array_values($newAiMedia))]);

            foreach ($orphans as $item) {
                $this->discardMediaItem($item);
            }

            // Refresh the caller's instance so later reads see the merged set.
            $post->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    /**
     * Delete a dropped AI media item's DB row, its file, and any background.
     *
     * @param  array<string, mixed>  $item
     */
    private function discardMediaItem(array $item): void
    {
        $path = data_get($item, 'path');
        if (is_string($path) && $path !== '' && Storage::exists($path)) {
            Storage::delete($path);
        }

        $bg = data_get($item, 'source_meta.background_path');
        if (is_string($bg) && $bg !== '' && Storage::exists($bg)) {
            Storage::delete($bg);
        }

        $id = data_get($item, 'id');
        if ($id !== null) {
            Media::query()->whereKey($id)->first()?->delete();
        }
    }
}
