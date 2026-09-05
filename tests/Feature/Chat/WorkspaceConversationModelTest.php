<?php

declare(strict_types=1);

use App\Enums\WorkspaceConversation\Message\Role;
use App\Enums\WorkspaceConversation\Status;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceConversation;
use App\Models\WorkspaceConversationMessage;

test('a conversation casts its status and owns messages', function () {
    $conversation = WorkspaceConversation::factory()->create();

    WorkspaceConversationMessage::factory()
        ->for($conversation, 'conversation')
        ->create(['role' => Role::User, 'content' => 'Hi']);

    expect($conversation->status)->toBe(Status::Idle)
        ->and($conversation->messages)->toHaveCount(1)
        ->and($conversation->messages->first()->role)->toBe(Role::User);
});

test('a message round-trips its json columns', function () {
    $message = WorkspaceConversationMessage::factory()->create([
        'tool_calls' => [['id' => 'call_1', 'name' => 'list_posts', 'arguments' => ['status' => 'draft']]],
        'tool_results' => [['id' => 'call_1', 'result' => '{"data":[]}']],
    ]);

    expect($message->fresh()->tool_calls[0]['name'])->toBe('list_posts')
        ->and($message->fresh()->tool_results[0]['id'])->toBe('call_1');
});

test('the listable scope hides soft deleted and other users conversations, untitled included', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();

    $visible = WorkspaceConversation::factory()->for($workspace)->for($user)->create(['title' => 'Visible']);

    // listable() orders by updated_at, which the database stores at
    // second precision — without a gap the two rows tie and their order is
    // whatever the engine returns that run.
    $this->travelTo(now()->addSecond());

    $untitled = WorkspaceConversation::factory()->for($workspace)->for($user)->untitled()->create();
    WorkspaceConversation::factory()->for($workspace)->for($other)->create(['title' => 'Other user']);
    WorkspaceConversation::factory()->for($workspace)->for($user)->create(['title' => 'Deleted'])->delete();

    $listable = WorkspaceConversation::query()->listable($workspace->id, $user->id)->get();

    // Newest first; the untitled conversation is listed too — its history
    // must not depend on the background title job having finished.
    expect($listable->pluck('id')->all())->toBe([$untitled->id, $visible->id]);
});
