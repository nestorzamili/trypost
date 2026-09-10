<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PostPlatform;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostPlatformStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PostPlatform $postPlatform) {}

    public function broadcastAs(): string
    {
        return 'post.platform.status.updated';
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("post.{$this->postPlatform->post_id}"),
            new PrivateChannel("workspace.{$this->postPlatform->post->workspace_id}"),
        ];
    }

    /**
     * The chat thread patches its frozen post cards live off this event (see
     * `patchPostStatus` in resources/js/lib/chat/postStatus.ts), so the
     * payload carries the post-level fields a card renders — not just the id.
     * Existing listeners reload on the event name and ignore the extra keys.
     *
     * @return array<string, string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'post_id' => $this->postPlatform->post_id,
            'status' => $this->postPlatform->post->status->value,
            'published_at' => $this->postPlatform->post->published_at?->toIso8601String(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
