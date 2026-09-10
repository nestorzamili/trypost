<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Actions\Post\CreatePost;
use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Ai\Templates\AiTemplateRegistry;
use App\Ai\Templates\GeneratedPost;
use App\Ai\Templates\TemplateContext;
use App\Enums\Ai\ContentStyle;
use App\Enums\Ai\GenerationFailure;
use App\Enums\Ai\GenerationStatus;
use App\Enums\Ai\GeneratorFormat;
use App\Enums\Notification\Channel as NotificationChannel;
use App\Enums\Notification\Type as NotificationType;
use App\Enums\Post\CreatedVia;
use App\Enums\PostPlatform\ContentType;
use App\Events\Ai\PostCreationProgress;
use App\Events\Ai\PostCreationReady;
use App\Jobs\SendNotification;
use App\Models\AiGeneration;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Ai\RecordAiUsage;
use App\Support\ResolvedBrand;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StreamPostCreation implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 990;

    /**
     * Retry a transient text-phase failure (provider 5xx / timeout). Safe to
     * retry: the row is firstOrCreate'd on creation_id, a terminal status
     * short-circuits handle(), createPostFromGenerated adopts any orphan draft,
     * and text usage is billed only after the draft is created — so a retry
     * neither duplicates the post nor double-bills.
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
        public string $format,
        public ?string $socialAccountId,
        public int $imageCount,
        public string $prompt,
        public ?string $date = null,
        public string $template = 'image_card',
        public bool $applyBrandVisuals = true,
        public array $referenceMediaIds = [],
        public bool $useBrandReferences = true,
        public array $labelIds = [],
        public ?string $languageCode = null,
    ) {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return "{$this->userId}:{$this->creationId}";
    }

    public function failed(?\Throwable $exception): void
    {
        $generation = AiGeneration::query()
            ->where('workspace_id', $this->workspaceId)
            ->where('creation_id', $this->creationId)
            ->first();

        if ($generation === null || $generation->status->isTerminal()) {
            return;
        }

        $generation->update([
            'status' => GenerationStatus::FailedText,
            'error_phase' => 'text',
            'error' => ($exception ? GenerationFailure::fromThrowable($exception, GenerationFailure::Text) : GenerationFailure::Text)
                ->message($generation->language_code),
        ]);

        PostCreationReady::dispatch($this->userId, $this->creationId, $generation->post_id, (string) $generation->error);
    }

    public function handle(): void
    {
        $generation = AiGeneration::query()->firstOrCreate(
            ['creation_id' => $this->creationId],
            [
                'workspace_id' => $this->workspaceId,
                'user_id' => $this->userId,
                'status' => GenerationStatus::PendingText,
                'format' => $this->format,
                'template' => $this->template,
                'apply_brand_visuals' => $this->applyBrandVisuals,
                'reference_media_ids' => $this->referenceMediaIds,
                'use_brand_references' => $this->useBrandReferences,
                'language_code' => $this->languageCode,
                'social_account_id' => $this->socialAccountId,
                'image_expected' => $this->imageCount,
            ],
        );

        if ($generation->status->isTerminal()) {
            return;
        }

        $workspace = Workspace::findOrFail($this->workspaceId);
        $workspace->loadMissing('brandVariants');
        $socialAccount = $this->socialAccountId ? SocialAccount::find($this->socialAccountId) : null;

        $style = app(AiTemplateRegistry::class)->find($this->template);

        $isCarousel = $this->format === ContentType::CAROUSEL_FORMAT;
        $agentFormat = $isCarousel ? GeneratorFormat::Carousel : GeneratorFormat::Single;
        $slideCount = $isCarousel && $this->imageCount > 0 ? $this->imageCount : 1;
        $brand = $workspace->resolvedBrand($this->languageCode);

        // Text phase never touches the image model: assembling with a null
        // account yields caption plus content type with empty media on every
        // template, so no image provider is billed here.
        $textContext = new TemplateContext(
            workspace: $workspace,
            socialAccount: null,
            format: $this->format,
            imageCount: $this->imageCount,
            isCarousel: $isCarousel,
            applyBrandVisuals: $this->applyBrandVisuals,
            languageCode: $brand->languageCode,
            brand: $brand,
            referenceImages: [],
        );

        $agent = new PostContentGenerator(
            workspace: $workspace,
            format: $agentFormat,
            slideCount: $slideCount,
            platformContext: $this->format,
            template: $style,
            templateContext: $textContext,
            brand: $brand,
        );

        try {
            $response = $agent->prompt($this->prompt);

            $structured = $response->structured ?? [];

            $structured = $this->humanize($workspace, $structured, $agentFormat, $style->style(), $brand);

            $textOnly = $style->assemble($structured, $textContext);
            $post = $this->createPostFromGenerated($workspace, $textOnly, $socialAccount);

            // Bill text usage only once the draft is durably created. Doing it
            // earlier meant a throw in humanize()/assemble()/createPost() spent
            // credits for nothing AND a retry (same creation_id) billed again.
            RecordAiUsage::recordText(
                workspace: $workspace,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                provider: (string) $response->meta->provider,
                model: (string) $response->meta->model,
                userId: $this->userId,
                postId: $post->id,
                metadata: [
                    'agent' => 'post_generator',
                    'format' => $this->format,
                    'content_language' => $brand->languageCode,
                    'brand_variant_id' => $brand->variantId,
                    'brand_variant_language' => $brand->hasVariant ? $brand->languageCode : null,
                    'has_brand_variant' => $brand->hasVariant,
                    'apply_brand_visuals' => $this->applyBrandVisuals,
                ],
            );

            $imageExpected = $this->expectedImageCount($isCarousel, $slideCount, $socialAccount);

            $generation->update([
                'workspace_id' => $workspace->id,
                'user_id' => $this->userId,
                'status' => GenerationStatus::TextReady,
                'format' => $this->format,
                'template' => $this->template,
                'apply_brand_visuals' => $this->applyBrandVisuals,
                'reference_media_ids' => $this->referenceMediaIds,
                'use_brand_references' => $this->useBrandReferences,
                'language_code' => $this->languageCode,
                'social_account_id' => $socialAccount?->id,
                'image_expected' => $imageExpected,
                'image_done' => 0,
                'post_id' => $post->id,
                'structured' => $structured,
                'error_phase' => null,
                'error' => null,
            ]);

            PostCreationProgress::dispatch(
                userId: $this->userId,
                creationId: $this->creationId,
                phase: GenerationStatus::TextReady,
                postId: $post->id,
                imageDone: 0,
                imageExpected: $imageExpected,
            );

            if ($imageExpected === 0) {
                $generation->update(['status' => GenerationStatus::Ready]);

                $this->notifyReady($workspace, $post, $brand);

                return;
            }

            RenderPostImages::dispatch(
                userId: $this->userId,
                creationId: $this->creationId,
                workspaceId: $this->workspaceId,
                applyBrandVisuals: $this->applyBrandVisuals,
                referenceMediaIds: $this->referenceMediaIds,
                useBrandReferences: $this->useBrandReferences,
                languageCode: $this->languageCode,
            );
        } catch (\Throwable $e) {
            // FailedText is terminal and would short-circuit a retry's handle().
            // Only write it on the final attempt; otherwise leave the row
            // non-terminal so the queued retry re-runs. failed() writes the
            // terminal status + fires the event after the last attempt.
            if ($this->attempts() >= $this->tries) {
                $generation->update([
                    'status' => GenerationStatus::FailedText,
                    'error_phase' => 'text',
                    'error' => GenerationFailure::fromThrowable($e, GenerationFailure::Text)->message($brand->languageCode),
                ]);
            }

            Log::error('StreamPostCreation failed', [
                'creation_id' => $this->creationId,
                'phase' => 'text',
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function expectedImageCount(bool $isCarousel, int $slideCount, ?SocialAccount $socialAccount): int
    {
        if ($socialAccount === null) {
            return 0;
        }

        if ($isCarousel) {
            return $slideCount;
        }

        return $this->imageCount > 0 ? 1 : 0;
    }

    /**
     * Run the structured generator output through the humanizer pass and merge
     * the humanized text fields back over the original structure (preserving
     * image_keywords and slide order/count). Failures are logged and the
     * original structure is returned so generation never breaks because of the
     * polish step.
     *
     * @param  array<string, mixed>  $structured
     * @return array<string, mixed>
     */
    private function humanize(Workspace $workspace, array $structured, GeneratorFormat $format, ContentStyle $style, ResolvedBrand $brand): array
    {
        if (! $style->humanizes()) {
            return $structured;
        }

        try {
            $input = $format->isCarousel()
                ? [
                    'caption' => data_get($structured, 'caption', ''),
                    'slides' => array_map(
                        fn ($s) => [
                            'title' => data_get($s, 'title', ''),
                            'body' => data_get($s, 'body', ''),
                        ],
                        data_get($structured, 'slides', []),
                    ),
                ]
                : [
                    'content' => data_get($structured, 'content', ''),
                    'image_title' => data_get($structured, 'image_title', ''),
                    'image_body' => data_get($structured, 'image_body', ''),
                ];

            $humanizer = new PostContentHumanizer(
                workspace: $workspace,
                format: $format,
                platformContext: $this->format,
                brand: $brand,
                languageCode: $brand->languageCode,
            );
            $response = $humanizer->prompt(json_encode($input, JSON_UNESCAPED_UNICODE));
            $humanized = $response->structured ?? [];

            RecordAiUsage::recordText(
                workspace: $workspace,
                promptTokens: $response->usage->promptTokens,
                completionTokens: $response->usage->completionTokens,
                provider: (string) $response->meta->provider,
                model: (string) $response->meta->model,
                userId: $this->userId,
                metadata: [
                    'agent' => 'post_humanizer',
                    'format' => $format->value,
                    'content_language' => $brand->languageCode,
                    'brand_variant_id' => $brand->variantId,
                    'brand_variant_language' => $brand->hasVariant ? $brand->languageCode : null,
                    'has_brand_variant' => $brand->hasVariant,
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('PostContentHumanizer failed, using generator output as-is', [
                'creation_id' => $this->creationId,
                'error' => $e->getMessage(),
            ]);

            return $structured;
        }

        if ($format->isCarousel()) {
            $structured['caption'] = data_get($humanized, 'caption', data_get($structured, 'caption', ''));
            $originalSlides = data_get($structured, 'slides', []);
            $humanizedSlides = data_get($humanized, 'slides', []);

            foreach ($originalSlides as $i => $slide) {
                if (isset($humanizedSlides[$i])) {
                    $originalSlides[$i]['title'] = data_get($humanizedSlides[$i], 'title', data_get($slide, 'title', ''));
                    $originalSlides[$i]['body'] = data_get($humanizedSlides[$i], 'body', data_get($slide, 'body', ''));
                }
            }

            $structured['slides'] = $originalSlides;
        } else {
            $structured['content'] = data_get($humanized, 'content', data_get($structured, 'content', ''));
            $structured['image_title'] = data_get($humanized, 'image_title', data_get($structured, 'image_title', ''));
            $structured['image_body'] = data_get($humanized, 'image_body', data_get($structured, 'image_body', ''));
        }

        return $structured;
    }

    private function createPostFromGenerated(Workspace $workspace, GeneratedPost $generated, ?SocialAccount $socialAccount): Post
    {
        $user = User::findOrFail($this->userId);

        $labelIds = $workspace->labels()->whereIn('id', $this->labelIds)->pluck('id')->all();

        // A hard worker loss between CreatePost and the generation update below
        // leaves a draft with this creation_id but a non-terminal generation,
        // so a retry must adopt that draft instead of inserting a second one.
        $orphan = $workspace->posts()->where('creation_id', $this->creationId)->first();

        if ($orphan instanceof Post) {
            $orphan->update([
                'content' => $generated->content,
                'media' => $generated->media,
            ]);

            $orphan->labels()->sync($labelIds);

            $post = $orphan;
        } else {
            $post = CreatePost::execute($workspace, $user, [
                'content' => $generated->content,
                'media' => $generated->media,
                'date' => $this->date,
                'created_via' => CreatedVia::Web,
                'creation_id' => $this->creationId,
                'label_ids' => $labelIds,
            ]);
        }

        if ($generated->contentType && $socialAccount) {
            $aspectRatio = $this->aspectRatioFor($generated->contentType);

            $post->postPlatforms()
                ->where('social_account_id', $socialAccount->id)
                ->each(function ($platform) use ($aspectRatio, $generated): void {
                    $meta = $platform->meta ?? [];
                    if ($aspectRatio !== null) {
                        $meta['aspect_ratio'] = $aspectRatio;
                    }
                    $platform->meta = $meta;
                    $platform->content_type = $generated->contentType->value;
                    $platform->enabled = true;
                    $platform->save();
                });
        }

        return $post;
    }

    private function notifyReady(Workspace $workspace, Post $post, ResolvedBrand $brand): void
    {
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
    }

    private function aspectRatioFor(ContentType $type): ?string
    {
        $dims = $type->aiImageDimensions();
        $width = (int) data_get($dims, 'width');
        $height = (int) data_get($dims, 'height');

        // A zero/missing height would throw a DivisionByZeroError mid-generation
        // (after text was created) — treat it as "no known ratio" instead.
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $ratio = $width / $height;

        return match (true) {
            abs($ratio - 1.0) < 0.01 => '1:1',
            abs($ratio - 4 / 5) < 0.01 => '4:5',
            abs($ratio - 16 / 9) < 0.01 => '16:9',
            default => null,
        };
    }
}
