<?php

declare(strict_types=1);

use App\Ai\Agents\WorkspaceConversationAgent;
use App\Ai\Tools\Post\ListPostsTool;
use App\Enums\SocialAccount\Platform;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

test('the agent exposes the post plus workspace tools', function () {
    $agent = new WorkspaceConversationAgent(Workspace::factory()->create(), User::factory()->create());

    $names = collect($agent->tools())->map(fn ($tool) => $tool->name())->all();

    expect($names)->toBe([
        'list_posts',
        'get_post',
        'get_post_metrics',
        'start_post_generation',
        'generate_post',
        'retry_post_images',
        'create_post',
        'update_post',
        'schedule_post',
        'publish_post',
        'delete_post',
        'get_brand',
        'list_labels',
        'list_signatures',
        'list_assets',
        'get_asset',
        'attach_existing_asset',
        'create_label',
        'update_label',
        'delete_label',
        'create_signature',
        'update_signature',
        'delete_signature',
        'update_brand',
        'create_brand_variant',
        'update_brand_variant',
        'delete_brand_variant',
        'delete_brand_reference_photo',
        'add_brand_reference_from_url',
        'delete_asset',
        'add_asset_from_url',
    ])
        ->and(collect($agent->tools())->first())->toBeInstanceOf(ListPostsTool::class);
});

test('the instructions carry the workspace brand and content language', function () {
    $workspace = Workspace::factory()->create([
        'name' => 'Acme Co',
        'brand_website' => 'https://acme.example',
        'brand_description' => 'We sell anvils.',
        'content_language' => 'es',
        'brand_voice_traits' => ['playful', 'confident'],
    ]);
    SocialAccount::factory()->create([
        'workspace_id' => $workspace->id,
        'platform' => Platform::LinkedIn,
    ]);

    $instructions = (new WorkspaceConversationAgent($workspace, User::factory()->create()))->instructions();

    expect($instructions)->toContain('Acme Co')
        ->and($instructions)->toContain('https://acme.example')
        ->and($instructions)->toContain('We sell anvils.')
        ->and($instructions)->toContain('Content language: es')
        ->and($instructions)->toContain('playful')
        ->and($instructions)->toContain('confident')
        ->and($instructions)->toContain('linkedin');
});

test('provider() is null by default so the single default provider is used', function () {
    config()->set('ai.text.failover', []);

    $agent = new WorkspaceConversationAgent(Workspace::factory()->create(), User::factory()->create());

    expect($agent->provider())->toBeNull();
});

test('provider() returns the configured failover chain when set', function () {
    config()->set('ai.text.failover', ['openai', 'gemini']);

    $agent = new WorkspaceConversationAgent(Workspace::factory()->create(), User::factory()->create());

    expect($agent->provider())->toBe(['openai', 'gemini']);
});

test('the conversation window is capped from config', function () {
    config()->set('ai.text.chat.max_conversation_messages', 25);

    $agent = new WorkspaceConversationAgent(Workspace::factory()->create(), User::factory()->create());

    $method = new ReflectionMethod($agent, 'maxConversationMessages');
    $method->setAccessible(true);

    expect($method->invoke($agent))->toBe(25);
});

test('the conversation window never drops below one', function () {
    config()->set('ai.text.chat.max_conversation_messages', 0);

    $agent = new WorkspaceConversationAgent(Workspace::factory()->create(), User::factory()->create());

    $method = new ReflectionMethod($agent, 'maxConversationMessages');
    $method->setAccessible(true);

    expect($method->invoke($agent))->toBe(1);
});

test('the instructions carry the current date, time and timezone for relative scheduling', function () {
    $agent = new WorkspaceConversationAgent(
        Workspace::factory()->create(),
        User::factory()->create(),
        'Asia/Jakarta',
    );

    $instructions = $agent->instructions();

    $expectedYear = now('Asia/Jakarta')->format('Y');

    expect($instructions)->toContain('Current date & time')
        ->and($instructions)->toContain('Asia/Jakarta')
        ->and($instructions)->toContain($expectedYear)
        // The +07:00 offset must be present so scheduled_at round-trips unambiguously.
        ->and($instructions)->toContain('+07:00');
});

test('an unknown timezone falls back to the app default without throwing', function () {
    config()->set('app.timezone', 'UTC');

    $agent = new WorkspaceConversationAgent(
        Workspace::factory()->create(),
        User::factory()->create(),
        'Not/AZone',
    );

    // Must not throw, and must anchor on the app default (UTC).
    $instructions = $agent->instructions();

    expect($instructions)->toContain('timezone UTC');
});
