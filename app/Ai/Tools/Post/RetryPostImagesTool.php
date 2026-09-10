<?php

declare(strict_types=1);

namespace App\Ai\Tools\Post;

use App\Ai\Tools\WorkspaceWriteTool;
use App\Enums\Ai\GenerationStatus;
use App\Events\Ai\PostCreationReady;
use App\Jobs\Ai\RenderPostImages;
use App\Models\AiGeneration;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Re-renders the images of a failed image generation onto the SAME draft.
 *
 * A `generate_post` whose images failed (status `failed_image`) already left
 * a text draft behind. Calling `generate_post` again would bill a second text
 * generation and strand that draft; this tool resumes instead: it flips the
 * generation back to image-pending with its stored brand/reference settings
 * and dispatches {@see RenderPostImages} for the same creation id and private
 * channel, so the existing result card keeps following along.
 */
class RetryPostImagesTool extends WorkspaceWriteTool
{
    /**
     * A generation stuck mid-flight longer than any healthy job may run is
     * treated as stalled and becomes retryable: the `ai` supervisor kills jobs
     * past 930 seconds, so 20 minutes means the worker is gone for good, not
     * merely slow. Keep in step with the `ai-assistant` horizon timeout.
     */
    private const STALLED_MINUTES = 20;

    public function name(): string
    {
        return 'retry_post_images';
    }

    public function description(): Stringable|string
    {
        return 'Retry the images of a failed AI generation without regenerating its text. Use this when the user asks to retry images and a generate_post result in this conversation failed with only its draft text saved: pass that result\'s creation_id. The images render onto the SAME draft post — no new post, no new text billing. Returns the same creation id and channel the original result card listens on, so say nothing after the call: the card reports progress itself. Only failed-image generations (or ones stalled mid-flight long past their window) can be retried; anything still running, already ready, or failed in its text phase is refused with the reason.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'creation_id' => $schema->string()->required()->description('The creation_id of the failed generate_post result to retry, taken from that result\'s payload in this conversation.'),
        ];
    }

    protected function run(Request $request): string
    {
        $gate = Gate::forUser($this->user)->inspect('useAi', $this->workspace->account);

        if ($gate->denied()) {
            return $this->error((string) $gate->message());
        }

        $creationId = $request->filled('creation_id')
            ? $request->string('creation_id')->trim()->value()
            : '';

        if ($creationId === '') {
            return $this->error('The "creation_id" argument is required. Pass the creation_id of the failed generate_post result in this conversation.');
        }

        $generation = AiGeneration::query()
            ->where('workspace_id', $this->workspace->id)
            ->where('creation_id', $creationId)
            ->first();

        if (! $generation instanceof AiGeneration) {
            return $this->error("There is no AI generation with creation_id \"{$creationId}\" in this workspace. Only a generate_post result from this conversation can be retried.");
        }

        if (! $generation->status instanceof GenerationStatus) {
            return $this->error("The generation \"{$creationId}\" is in an unknown state. Ask for a fresh generate_post instead.");
        }

        if ($generation->status === GenerationStatus::Ready) {
            return $this->error("The generation \"{$creationId}\" already finished with its images. Nothing to retry.");
        }

        if ($generation->status === GenerationStatus::FailedText) {
            return $this->error("The generation \"{$creationId}\" failed while writing its text, so there is no draft to attach images to. Call generate_post again for a fresh post instead.");
        }

        if (! in_array($generation->status, [GenerationStatus::FailedImage], true)) {
            if ($this->isStalled($generation)) {
                $generation->update([
                    'status' => GenerationStatus::FailedImage,
                    'error_phase' => 'image',
                    'error' => 'The previous image attempt stalled and never finished.',
                ]);
                $generation->refresh();
            } else {
                return $this->error("The generation \"{$creationId}\" is still running. Wait for its card to settle before retrying.");
            }
        }

        if ($generation->image_expected <= 0) {
            return $this->error("The generation \"{$creationId}\" expected no images, so there is nothing to retry.");
        }

        $post = $generation->post_id !== null
            ? $this->workspace->posts()->find($generation->post_id)
            : null;

        if ($post === null) {
            return $this->error("The draft of generation \"{$creationId}\" no longer exists. Call generate_post again for a fresh post instead.");
        }

        $generation->update([
            'status' => GenerationStatus::TextReady,
            'image_done' => 0,
            'error_phase' => null,
            'error' => null,
        ]);

        RenderPostImages::dispatch(
            userId: $this->user->id,
            creationId: $generation->creation_id,
            workspaceId: $this->workspace->id,
            applyBrandVisuals: $generation->apply_brand_visuals ?? true,
            referenceMediaIds: $generation->reference_media_ids ?? [],
            useBrandReferences: $generation->use_brand_references ?? true,
            languageCode: $generation->language_code ?? null,
        );

        return $this->json([
            'data' => [
                'creation_id' => $generation->creation_id,
                'channel' => $this->channelFor($generation->creation_id),
            ],
        ]);
    }

    /**
     * A non-terminal generation whose worker died mid-flight (lost between
     * dispatch and completion, or SIGKILLed past `failed()` ever running) can
     * never settle on its own — and without this, retry would refuse it
     * forever while no job remains to finish it. Terminal rows are never
     * stalled: they already have their answer.
     */
    private function isStalled(AiGeneration $generation): bool
    {
        if ($generation->status->isTerminal()) {
            return false;
        }

        return $generation->updated_at !== null
            && $generation->updated_at->lt(now()->subMinutes(self::STALLED_MINUTES));
    }

    /**
     * The private channel the retried images are announced on — the same one
     * the original card listens to, taken from the event itself so the name
     * is never spelled out a second time.
     */
    private function channelFor(string $creationId): string
    {
        $channel = (new PostCreationReady($this->user->id, $creationId))->broadcastOn();

        return Str::after($channel->name, 'private-');
    }
}
