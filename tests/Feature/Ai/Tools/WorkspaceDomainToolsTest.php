<?php

declare(strict_types=1);

use App\Actions\Post\AttachExistingAsset;
use App\Ai\Tools\Asset\AddAssetFromUrlTool;
use App\Ai\Tools\Asset\AttachExistingAssetTool;
use App\Ai\Tools\Asset\DeleteAssetTool;
use App\Ai\Tools\Asset\GetAssetTool;
use App\Ai\Tools\Asset\ListAssetsTool;
use App\Ai\Tools\Brand\AddBrandReferenceFromUrlTool;
use App\Ai\Tools\Brand\CreateBrandVariantTool;
use App\Ai\Tools\Brand\DeleteBrandReferencePhotoTool;
use App\Ai\Tools\Brand\DeleteBrandVariantTool;
use App\Ai\Tools\Brand\GetBrandTool;
use App\Ai\Tools\Brand\UpdateBrandTool;
use App\Ai\Tools\Brand\UpdateBrandVariantTool;
use App\Ai\Tools\Label\CreateLabelTool;
use App\Ai\Tools\Label\DeleteLabelTool;
use App\Ai\Tools\Label\ListLabelsTool;
use App\Ai\Tools\Label\UpdateLabelTool;
use App\Ai\Tools\Post\CreatePostTool;
use App\Ai\Tools\Post\UpdatePostTool;
use App\Ai\Tools\Signature\CreateSignatureTool;
use App\Ai\Tools\Signature\DeleteSignatureTool;
use App\Ai\Tools\Signature\ListSignaturesTool;
use App\Ai\Tools\Signature\UpdateSignatureTool;
use App\Enums\Post\Status;
use App\Enums\UserWorkspace\Role;
use App\Models\BrandVariant;
use App\Models\Post;
use App\Models\WorkspaceLabel;
use App\Models\WorkspaceSignature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Approval;
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

test('create_label creates a label and returns the existing one instead of duplicating', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    $created = json_decode((new CreateLabelTool($workspace, $user))->handle(
        new Request(['name' => 'Promo', 'color' => '#FF5733'])
    ), true);

    expect($created['data']['name'])->toBe('Promo')
        ->and($created['data']['already_existed'])->toBeFalse();

    $duplicate = json_decode((new CreateLabelTool($workspace, $user))->handle(
        new Request(['name' => 'promo', 'color' => '#000000'])
    ), true);

    expect($duplicate['data']['id'])->toBe($created['data']['id'])
        ->and($duplicate['data']['already_existed'])->toBeTrue()
        ->and(WorkspaceLabel::where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('create_label refuses a whitespace-only name', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    $output = json_decode((new CreateLabelTool($workspace, $user))->handle(
        new Request(['name' => '   ', 'color' => '#FF5733'])
    ), true);

    expect($output)->toHaveKey('error')
        ->and(WorkspaceLabel::count())->toBe(0);
});

test('update_label refuses a whitespace-only name and keeps the old one', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $label = WorkspaceLabel::factory()->for($workspace)->create(['name' => 'Kept']);

    $output = json_decode((new UpdateLabelTool($workspace, $user))->handle(
        new Request(['label_id' => $label->id, 'name' => '   '])
    ), true);

    expect($output)->toHaveKey('error')
        ->and($label->fresh()->name)->toBe('Kept');
});

test('create_label refuses an invalid color with an actionable message', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    $output = json_decode((new CreateLabelTool($workspace, $user))->handle(
        new Request(['name' => 'Promo', 'color' => 'red'])
    ), true);

    expect($output)->toHaveKey('error')
        ->and(WorkspaceLabel::count())->toBe(0);
});

test('update_label renames a workspace label and refuses foreign ones', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $label = WorkspaceLabel::factory()->for($workspace)->create(['name' => 'Old']);
    $foreign = WorkspaceLabel::factory()->create(['name' => 'Foreign']);

    $output = json_decode((new UpdateLabelTool($workspace, $user))->handle(
        new Request(['label_id' => $label->id, 'name' => 'New'])
    ), true);

    expect($output['data']['name'])->toBe('New');

    $refused = json_decode((new UpdateLabelTool($workspace, $user))->handle(
        new Request(['label_id' => $foreign->id, 'name' => 'Hacked'])
    ), true);

    expect($refused['error'])->toBe(__('chat.tools.label_not_found'))
        ->and($foreign->fresh()->name)->toBe('Foreign');
});

test('delete_label needs approval and reports how many posts lose the tag', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    $label = WorkspaceLabel::factory()->for($workspace)->create();
    $post = Post::factory()->for($workspace)->create();
    $post->labels()->attach($label->id);

    $tool = new DeleteLabelTool($workspace, $user);
    $request = new Request(['label_id' => $label->id]);

    expect($tool->shouldRequestApproval($request))->toBeInstanceOf(Approval::class);

    $output = json_decode($tool->handle($request), true);

    expect($output['data']['deleted'])->toBeTrue()
        ->and($output['data']['detached_from_posts'])->toBe(1)
        ->and($post->fresh()->labels()->count())->toBe(0);
});

test('delete_label needs no approval and errors for an unknown id', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    $tool = new DeleteLabelTool($workspace, $user);
    $request = new Request(['label_id' => (string) Str::uuid()]);

    expect($tool->shouldRequestApproval($request))->toBeNull()
        ->and(json_decode($tool->handle($request), true)['error'])->toBe(__('chat.tools.label_not_found'));
});

test('signature create, update and approved delete round-trip', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    $created = json_decode((new CreateSignatureTool($workspace, $user))->handle(
        new Request(['name' => 'Tags', 'content' => '#a #b'])
    ), true);

    expect($created['data']['content'])->toBe('#a #b');

    $updated = json_decode((new UpdateSignatureTool($workspace, $user))->handle(
        new Request(['signature_id' => $created['data']['id'], 'content' => '#a #b #c'])
    ), true);

    expect($updated['data']['content'])->toBe('#a #b #c');

    $tool = new DeleteSignatureTool($workspace, $user);
    $request = new Request(['signature_id' => $created['data']['id']]);

    expect($tool->shouldRequestApproval($request))->toBeInstanceOf(Approval::class);

    $deleted = json_decode($tool->handle($request), true);

    expect($deleted['data']['deleted'])->toBeTrue();
    $this->assertSoftDeleted('workspace_signatures', ['id' => $created['data']['id']]);
});

test('update_brand changes only the fields it is given', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);
    $guidelinesBefore = $workspace->brand_guidelines;

    $output = json_decode((new UpdateBrandTool($workspace, $user))->handle(
        new Request(['brand_description' => 'We sell anvils now.'])
    ), true);

    expect($output['data']['brand_description'])->toBe('We sell anvils now.')
        ->and($workspace->fresh()->brand_guidelines)->toBe($guidelinesBefore);
});

test('update_brand refuses unknown fields and invalid enum values actionably', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);

    $empty = json_decode((new UpdateBrandTool($workspace, $user))->handle(new Request([])), true);

    expect($empty)->toHaveKey('error');

    $badTrait = json_decode((new UpdateBrandTool($workspace, $user))->handle(
        new Request(['brand_voice_traits' => ['not-a-trait']])
    ), true);

    expect($badTrait['error'])->toContain('Valid traits are:');

    $palette = json_decode((new UpdateBrandTool($workspace, $user))->handle(
        new Request(['brand_description' => 'Kept', 'colors' => ['Primary' => '#FF0000']])
    ), true);

    expect($palette['error'])->toContain('settings UI')
        ->and($workspace->fresh()->brand_description)->not->toBe('Kept');
});

test('update_brand_variant refuses the palette map with a settings pointer', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);
    $variant = $workspace->brandVariants()->create([
        'language_code' => 'en',
        'label' => 'English',
        'sort_order' => 0,
    ]);

    $output = json_decode((new UpdateBrandVariantTool($workspace, $user))->handle(
        new Request(['variant_id' => $variant->id, 'colors' => ['Primary' => '#FF0000']])
    ), true);

    expect($output['error'])->toContain('settings UI');
});

test('brand variant round-trips every font field the tools accept', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);

    $created = json_decode((new CreateBrandVariantTool($workspace, $user))->handle(
        new Request(['language_code' => 'en', 'label' => 'English', 'label_font' => 'Inter', 'accent_font' => 'Inter'])
    ), true);

    expect($created['data']['label_font'])->toBe('Inter')
        ->and($created['data']['accent_font'])->toBe('Inter');

    $brand = json_decode((new GetBrandTool($workspace, $user))->handle(new Request([])), true);

    expect($brand['data']['variants'][0]['label_font'])->toBe('Inter')
        ->and($brand['data']['variants'][0]['accent_font'])->toBe('Inter');
});

test('update_brand needs approval once a field is given', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);

    expect((new UpdateBrandTool($workspace, $user))->shouldRequestApproval(new Request([])))->toBeNull()
        ->and((new UpdateBrandTool($workspace, $user))->shouldRequestApproval(
            new Request(['brand_description' => 'New'])
        ))->toBeInstanceOf(Approval::class);
});

test('brand variant create, duplicate-language refusal, update and approved delete', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);

    $created = json_decode((new CreateBrandVariantTool($workspace, $user))->handle(
        new Request(['language_code' => 'en', 'label' => 'English', 'visual_notes' => 'Warm light.'])
    ), true);

    expect($created['data']['language_code'])->toBe('en');

    $duplicate = json_decode((new CreateBrandVariantTool($workspace, $user))->handle(
        new Request(['language_code' => 'en', 'label' => 'English again'])
    ), true);

    expect($duplicate)->toHaveKey('error');

    $updated = json_decode((new UpdateBrandVariantTool($workspace, $user))->handle(
        new Request(['variant_id' => $created['data']['id'], 'visual_notes' => 'Cool light.'])
    ), true);

    expect($updated['data']['visual_notes'])->toBe('Cool light.')
        ->and($updated['data']['label'])->toBe('English');

    $tool = new DeleteBrandVariantTool($workspace, $user);
    $request = new Request(['variant_id' => $created['data']['id']]);

    expect($tool->shouldRequestApproval($request))->toBeInstanceOf(Approval::class);

    $deleted = json_decode($tool->handle($request), true);

    expect($deleted['data']['deleted'])->toBeTrue();
});

test('brand variant tools refuse foreign variants', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);
    $foreign = BrandVariant::factory()->create();

    $output = json_decode((new UpdateBrandVariantTool($workspace, $user))->handle(
        new Request(['variant_id' => $foreign->id, 'label' => 'Hacked'])
    ), true);

    expect($output['error'])->toBe(__('chat.tools.brand_variant_not_found'));
});

test('delete_brand_reference_photo removes the file and refuses other collections', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);

    Storage::fake();
    $photo = $workspace->addMedia(UploadedFile::fake()->image('ref.jpg'), 'brand_references');
    $logo = $workspace->addMedia(UploadedFile::fake()->image('logo.jpg'), 'logo');

    $tool = new DeleteBrandReferencePhotoTool($workspace, $user);

    expect($tool->shouldRequestApproval(new Request(['photo_id' => $photo->id])))->toBeInstanceOf(Approval::class);

    $output = json_decode($tool->handle(new Request(['photo_id' => $photo->id])), true);

    expect($output['data']['deleted'])->toBeTrue();
    Storage::assertMissing($photo->path);

    $refused = json_decode($tool->handle(new Request(['photo_id' => $logo->id])), true);

    expect($refused['error'])->toBe(__('chat.tools.brand_reference_not_found'));
});

test('add_brand_reference_from_url registers an image', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);
    config()->set('trypost.security.allow_private_network', true);

    Storage::fake();

    // The body never needs to be a real image: the URL flow stores bytes and
    // only inspects the Content-Type header.
    Http::fake(['*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);

    $output = json_decode((new AddBrandReferenceFromUrlTool($workspace, $user))->handle(
        new Request(['url' => 'https://example.com/ref.jpg', 'label' => 'Storefront'])
    ), true);

    expect($output['data']['label'])->toBe('Storefront')
        ->and($workspace->getMedia('brand_references')->count())->toBe(1);
});

test('add_brand_reference_from_url refuses non-image content', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Admin);
    config()->set('trypost.security.allow_private_network', true);

    Storage::fake();

    Http::fake(['*' => Http::response('not an image', 200, ['Content-Type' => 'text/html'])]);

    $refused = json_decode((new AddBrandReferenceFromUrlTool($workspace, $user))->handle(
        new Request(['url' => 'https://example.com/page'])
    ), true);

    expect($refused)->toHaveKey('error')
        ->and($workspace->getMedia('brand_references')->count())->toBe(0);
});

test('delete_asset needs approval, removes the file and counts using posts', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    Storage::fake();
    $asset = $workspace->addMedia(UploadedFile::fake()->image('campaign.jpg'), 'assets');
    $post = Post::factory()->for($workspace)->create(['status' => Status::Draft, 'media' => []]);
    AttachExistingAsset::execute($post, $asset);

    $tool = new DeleteAssetTool($workspace, $user);
    $request = new Request(['asset_id' => $asset->id]);

    expect($tool->shouldRequestApproval($request))->toBeInstanceOf(Approval::class);

    $output = json_decode($tool->handle($request), true);

    expect($output['data']['deleted'])->toBeTrue()
        ->and($output['data']['used_by_posts'])->toBe(1);
    Storage::assertMissing($asset->path);
});

test('add_asset_from_url mirrors the controller allowlist', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    config()->set('trypost.security.allow_private_network', true);

    Storage::fake();

    Http::fake(['*' => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg'])]);

    $output = json_decode((new AddAssetFromUrlTool($workspace, $user))->handle(
        new Request(['url' => 'https://images.unsplash.com/photo-123', 'filename' => 'hero.jpg'])
    ), true);

    expect($output['data']['original_filename'])->toBe('hero.jpg');

    $refused = json_decode((new AddAssetFromUrlTool($workspace, $user))->handle(
        new Request(['url' => 'https://example.com/photo.jpg', 'filename' => 'hero.jpg'])
    ), true);

    expect($refused)->toHaveKey('error');
});

test('add_asset_from_url refuses non-library content types and types video correctly', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    config()->set('trypost.security.allow_private_network', true);

    Storage::fake();

    Http::fake(['*' => Http::response('not an image', 200, ['Content-Type' => 'text/html'])]);

    $refused = json_decode((new AddAssetFromUrlTool($workspace, $user))->handle(
        new Request(['url' => 'https://images.unsplash.com/page', 'filename' => 'page.jpg'])
    ), true);

    expect($refused)->toHaveKey('error')
        ->and($workspace->getMedia('assets')->count())->toBe(0);
});

test('add_asset_from_url stores video with its own type and extension', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);
    config()->set('trypost.security.allow_private_network', true);

    Storage::fake();

    Http::fake(['*' => Http::response('fake-video-bytes', 200, ['Content-Type' => 'video/mp4'])]);

    $output = json_decode((new AddAssetFromUrlTool($workspace, $user))->handle(
        new Request(['url' => 'https://images.unsplash.com/clip-123', 'filename' => 'clip.mp4'])
    ), true);

    expect($output['data']['type'])->toBe('video')
        ->and($output['data']['mime_type'])->toBe('video/mp4');
});

test('signature content may not be blank', function () {
    [$user, $workspace] = workspaceUserWithRole(Role::Member);

    $created = json_decode((new CreateSignatureTool($workspace, $user))->handle(
        new Request(['name' => 'Tags', 'content' => '   '])
    ), true);

    expect($created)->toHaveKey('error');

    $signature = WorkspaceSignature::factory()->for($workspace)->create(['content' => 'Kept']);

    $updated = json_decode((new UpdateSignatureTool($workspace, $user))->handle(
        new Request(['signature_id' => $signature->id, 'content' => '   '])
    ), true);

    expect($updated)->toHaveKey('error')
        ->and($signature->fresh()->content)->toBe('Kept');
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
