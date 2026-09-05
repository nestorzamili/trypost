<?php

declare(strict_types=1);

use App\Ai\Tools\Asset\AttachExistingAssetTool;
use App\Ai\Tools\Asset\GetAssetTool;
use App\Ai\Tools\Asset\ListAssetsTool;
use App\Ai\Tools\Brand\GetBrandTool;
use App\Ai\Tools\Label\ListLabelsTool;
use App\Ai\Tools\Post\CreatePostTool;
use App\Ai\Tools\Post\UpdatePostTool;
use App\Ai\Tools\Signature\ListSignaturesTool;
use App\Enums\Post\Status;
use App\Enums\UserWorkspace\Role;
use App\Models\Post;
use App\Models\WorkspaceLabel;
use App\Models\WorkspaceSignature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;

test('get_brand returns the workspace identity with variants and photo references', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $workspace->update(['name' => 'Acme Co', 'brand_description' => 'We sell anvils.']);
    $workspace->brandVariants()->create([
        'language_code' => 'en',
        'label' => 'English',
        'sort_order' => 0,
    ]);

    Storage::fake();
    $workspace->addMedia(UploadedFile::fake()->image('ref.jpg'), 'brand_references');

    $output = json_decode((new GetBrandTool($workspace, $user))->handle(new Request([])), true);

    expect($output['data']['name'])->toBe('Acme Co')
        ->and($output['data']['brand_description'])->toBe('We sell anvils.')
        ->and($output['data']['variants'])->toHaveCount(1)
        ->and($output['data']['reference_photos'])->toHaveCount(1);
});

test('list_labels only returns labels from the tool workspace', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $mine = WorkspaceLabel::factory()->for($workspace)->create(['name' => 'Mine']);
    WorkspaceLabel::factory()->create(['name' => 'Theirs']);

    $output = json_decode((new ListLabelsTool($workspace, $user))->handle(new Request([])), true);

    expect($output['data'])->toHaveCount(1)
        ->and($output['data'][0]['id'])->toBe($mine->id);
});

test('list_signatures returns workspace signatures without leaking other workspaces', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $mine = WorkspaceSignature::factory()->for($workspace)->create(['name' => 'Hashtags']);
    WorkspaceSignature::factory()->create(['name' => 'Foreign']);

    $output = json_decode((new ListSignaturesTool($workspace, $user))->handle(new Request([])), true);

    expect($output['data'])->toHaveCount(1)
        ->and($output['data'][0]['id'])->toBe($mine->id)
        ->and($output['data'][0]['content'])->toBe($mine->content);
});

test('list_assets returns only the assets collection and get_asset resolves one item', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    Storage::fake();
    $asset = $workspace->addMedia(UploadedFile::fake()->image('campaign.jpg'), 'assets');
    $workspace->addMedia(UploadedFile::fake()->image('logo.jpg'), 'logo');

    $list = json_decode((new ListAssetsTool($workspace, $user))->handle(new Request([])), true);

    expect($list['data'])->toHaveCount(1)
        ->and($list['data'][0]['id'])->toBe($asset->id);

    $single = json_decode((new GetAssetTool($workspace, $user))->handle(new Request(['asset_id' => $asset->id])), true);

    expect($single['data']['id'])->toBe($asset->id);

    $missing = json_decode((new GetAssetTool($workspace, $user))->handle(
        new Request(['asset_id' => (string) Str::uuid()])
    ), true);

    expect($missing['error'])->toBe(__('chat.tools.asset_not_found'));
});

test('create_post tags the post with valid label ids', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $label = WorkspaceLabel::factory()->for($workspace)->create();

    $output = json_decode((new CreatePostTool($workspace, $user))->handle(
        new Request(['content' => 'Tagged draft', 'label_ids' => [$label->id]])
    ), true);

    $post = Post::find($output['data']['id']);

    expect($post->labels()->pluck('workspace_labels.id')->all())->toBe([$label->id]);
});

test('create_post refuses unknown label ids instead of silently dropping them', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    $output = json_decode((new CreatePostTool($workspace, $user))->handle(
        new Request(['content' => 'Tagged draft', 'label_ids' => [(string) Str::uuid()]])
    ), true);

    expect($output)->toHaveKey('error')
        ->and(Post::count())->toBe(0);
});

test('update_post retags the post with valid label ids', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $post = Post::factory()->for($workspace)->create();
    $label = WorkspaceLabel::factory()->for($workspace)->create();

    $output = json_decode((new UpdatePostTool($workspace, $user))->handle(
        new Request(['post_id' => $post->id, 'label_ids' => [$label->id]])
    ), true);

    expect($output['data']['id'])->toBe($post->id)
        ->and($post->fresh()->labels()->pluck('workspace_labels.id')->all())->toBe([$label->id]);
});

test('attach_existing_asset reuses library media on a draft post', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $post = Post::factory()->for($workspace)->create(['status' => Status::Draft, 'media' => []]);

    Storage::fake();
    $asset = $workspace->addMedia(UploadedFile::fake()->image('campaign.jpg'), 'assets');

    $output = json_decode((new AttachExistingAssetTool($workspace, $user))->handle(
        new Request(['post_id' => $post->id, 'asset_id' => $asset->id])
    ), true);

    expect($output['data']['id'])->toBe($post->id)
        ->and($post->fresh()->media)->toHaveCount(1);
});

test('attach_existing_asset refuses a published post and an unknown asset', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $published = Post::factory()->for($workspace)->create(['status' => Status::Published, 'media' => []]);

    Storage::fake();
    $asset = $workspace->addMedia(UploadedFile::fake()->image('campaign.jpg'), 'assets');

    $blocked = json_decode((new AttachExistingAssetTool($workspace, $user))->handle(
        new Request(['post_id' => $published->id, 'asset_id' => $asset->id])
    ), true);

    expect($blocked)->toHaveKey('error');

    $draft = Post::factory()->for($workspace)->create(['status' => Status::Draft, 'media' => []]);

    $missing = json_decode((new AttachExistingAssetTool($workspace, $user))->handle(
        new Request(['post_id' => $draft->id, 'asset_id' => (string) Str::uuid()])
    ), true);

    expect($missing['error'])->toBe(__('chat.tools.asset_not_found'))
        ->and($draft->fresh()->media)->toBe([]);
});
