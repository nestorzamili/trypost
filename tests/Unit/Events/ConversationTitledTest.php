<?php

declare(strict_types=1);

use App\Events\Ai\ConversationTitled;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceConversation;
use Illuminate\Broadcasting\PrivateChannel;

test('event broadcasts on the workspace channel', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['user_id' => $user->id]);
    $conversation = WorkspaceConversation::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
    ]);

    $event = new ConversationTitled($workspace->id, $conversation->id, 'Draft post count');
    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1);
    expect($channels[0])->toBeInstanceOf(PrivateChannel::class);
    expect($channels[0]->name)->toBe("private-workspace.{$workspace->id}");
});

test('event broadcasts with the conversation id and title', function () {
    $event = new ConversationTitled('workspace-id', 'conversation-id', 'Draft post count');

    expect($event->broadcastWith())->toBe([
        'conversation_id' => 'conversation-id',
        'title' => 'Draft post count',
    ]);
});

test('event broadcasts as a stable name', function () {
    $event = new ConversationTitled('workspace-id', 'conversation-id', 'Draft post count');

    expect($event->broadcastAs())->toBe('conversation.titled');
});
