<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Ai\Agents\PostCaptionRegenerator;
use App\Models\Workspace;
use App\Services\Ai\RecordAiUsage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegeneratePostCaption implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $workspaceId,
        public string $userId,
        public string $regenerationId,
        public string $content,
        public ?string $instruction,
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        $workspace = Workspace::findOrFail($this->workspaceId);
        $brand = $workspace->resolvedBrand();
        $prompt = "Caption to regenerate:\n{$this->content}";

        if ($this->instruction !== null) {
            $prompt .= "\n\nAdditional instruction:\n{$this->instruction}";
        }

        $response = (new PostCaptionRegenerator($workspace, $brand))
            ->broadcast($prompt, new PrivateChannel("user.{$this->userId}.ai-caption.{$this->regenerationId}"), now: true);

        RecordAiUsage::recordText(
            workspace: $workspace,
            promptTokens: $response->usage?->promptTokens ?? 0,
            completionTokens: $response->usage?->completionTokens ?? 0,
            provider: (string) $response->meta?->provider,
            model: (string) $response->meta?->model,
            userId: $this->userId,
            metadata: ['agent' => 'post_caption_regenerator', 'content_language' => $brand->languageCode],
        );
    }
}
