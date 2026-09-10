<?php

declare(strict_types=1);

namespace App\Events\Ai;

use App\Enums\Ai\GenerationStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostCreationProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $userId,
        public string $creationId,
        public GenerationStatus $phase,
        public ?string $postId = null,
        public int $imageDone = 0,
        public int $imageExpected = 0,
    ) {}

    public function broadcastAs(): string
    {
        return 'ai.creation.progress';
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}.ai-creation.{$this->creationId}");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'creation_id' => $this->creationId,
            'phase' => $this->phase->value,
            'post_id' => $this->postId,
            'image_done' => $this->imageDone,
            'image_expected' => $this->imageExpected,
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
