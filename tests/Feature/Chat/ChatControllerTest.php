<?php

declare(strict_types=1);

use App\Enums\WorkspaceConversation\Message\Role;
use App\Models\Workspace;
use App\Models\WorkspaceConversation;
use App\Models\WorkspaceConversationMessage;

test('the sidebar lists this users conversations, newest first, untitled included', function () {
    [$user, $workspace] = actingAsWorkspaceUser();

    $older = WorkspaceConversation::factory()->for($workspace)->for($user)->create(['title' => 'Older']);
    $older->forceFill(['updated_at' => now()->subDay()])->saveQuietly();

    $newer = WorkspaceConversation::factory()->for($workspace)->for($user)->create(['title' => 'Newer']);
    $untitled = WorkspaceConversation::factory()->for($workspace)->for($user)->untitled()->create();
    WorkspaceConversation::factory()->for($workspace)->create(['title' => 'Someone else']);

    $this->get(route('app.chat'))
        ->assertInertia(fn ($page) => $page
            ->component('chat/Index')
            ->has('conversations', 3)
            ->where('conversations.0.id', $untitled->id)
            ->where('conversations.0.title', null)
            ->where('conversations.1.title', 'Newer')
            ->where('conversations.2.title', 'Older'));
});

test('another users conversation cannot be opened', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    $foreign = WorkspaceConversation::factory()->for($workspace)->create(['title' => 'Not yours']);

    $this->get(route('app.chat.show', $foreign->id))->assertNotFound();
});

test('a conversation can be renamed and soft deleted', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    $conversation = WorkspaceConversation::factory()->for($workspace)->for($user)->create(['title' => 'Before']);

    $this->patch(route('app.chat.update', $conversation->id), ['title' => 'After']);
    expect($conversation->fresh()->title)->toBe('After');

    $this->delete(route('app.chat.destroy', $conversation->id));
    expect($conversation->fresh()->trashed())->toBeTrue();
});

test('deleting with stay returns to the page the request came from', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    $current = WorkspaceConversation::factory()->for($workspace)->for($user)->create(['title' => 'Current']);
    $other = WorkspaceConversation::factory()->for($workspace)->for($user)->create(['title' => 'Other']);

    $this->from(route('app.chat.show', $current->id))
        ->delete(route('app.chat.destroy', ['conversation' => $other->id, 'stay' => true]))
        ->assertRedirect(route('app.chat.show', $current->id));

    expect($other->fresh()->trashed())->toBeTrue();
});

test('deleting without stay starts a new chat', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    $conversation = WorkspaceConversation::factory()->for($workspace)->for($user)->create(['title' => 'Gone']);

    $this->delete(route('app.chat.destroy', $conversation->id))
        ->assertRedirect(route('app.chat'));
});

test('an untitled conversation can still be opened by its owner', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    $conversation = WorkspaceConversation::factory()->for($workspace)->for($user)->untitled()->create();

    $this->get(route('app.chat.show', $conversation->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('chat/Index')
            ->where('conversation.id', $conversation->id)
            ->where('conversation.title', null));
});

test('an untitled conversation lists with its opening message as a fallback title', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    $conversation = WorkspaceConversation::factory()->for($workspace)->for($user)->untitled()->create();
    WorkspaceConversationMessage::factory()->for($conversation, 'conversation')->create([
        'role' => Role::User,
        'content' => 'How many drafts do I have?',
    ]);

    $this->get(route('app.chat'))
        ->assertInertia(fn ($page) => $page
            ->component('chat/Index')
            ->has('conversations', 1)
            ->where('conversations.0.id', $conversation->id)
            ->where('conversations.0.title', 'How many drafts do I have?'));
});

test('an untitled conversation with no messages yet lists with a null title', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    WorkspaceConversation::factory()->for($workspace)->for($user)->untitled()->create();

    $this->get(route('app.chat'))
        ->assertInertia(fn ($page) => $page
            ->component('chat/Index')
            ->has('conversations', 1)
            ->where('conversations.0.title', null));
});

test('another users untitled conversation still cannot be opened', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    $foreign = WorkspaceConversation::factory()->for($workspace)->untitled()->create();

    $this->get(route('app.chat.show', $foreign->id))->assertNotFound();
});

test('this users conversation in a different workspace cannot be opened', function () {
    [$user, $workspace] = actingAsWorkspaceUser();
    $otherWorkspace = Workspace::factory()->create();
    $elsewhere = WorkspaceConversation::factory()->for($otherWorkspace)->for($user)->create(['title' => 'Wrong workspace']);

    $this->get(route('app.chat.show', $elsewhere->id))->assertNotFound();
});
