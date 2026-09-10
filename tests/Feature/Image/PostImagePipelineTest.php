<?php

declare(strict_types=1);

use App\Enums\Media\Source;
use App\Enums\PostPlatform\ContentType;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\Image\PostImagePipeline;
use App\Services\Image\TemplateImageGenerator;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();

    $this->workspace = Workspace::factory()->create();
    $this->account = SocialAccount::factory()->instagram()->create([
        'workspace_id' => $this->workspace->id,
    ]);

    Storage::put('ai-images/x.webp', 'fake-image-bytes');

    $this->rendered = [
        'path' => 'ai-images/x.webp',
        'source_meta' => [
            'keywords' => ['productivity'],
            'style' => 'cinematic',
            'language' => 'en',
        ],
    ];
});

test('forSingle returns a media item with the expected shape and persists a Media row', function () {
    $this->mock(TemplateImageGenerator::class, function ($mock) {
        $mock->shouldReceive('render')->once()->andReturn($this->rendered);
    });

    $structured = [
        'content' => 'A single productivity tip',
        'image_title' => 'Tip',
        'image_body' => 'Do less',
        'image_keywords' => ['productivity'],
    ];

    $pipeline = app(PostImagePipeline::class);

    $media = $pipeline->forSingle($this->workspace, $this->account, $structured, ContentType::InstagramFeed);

    expect($media)->toHaveCount(1);

    $item = $media[0];

    expect($item)->toHaveKeys(['id', 'path', 'url', 'type', 'mime_type', 'source', 'source_meta']);
    expect($item['path'])->toBe('ai-images/x.webp');
    expect($item['type'])->toBe('image');
    expect($item['mime_type'])->toBe('image/webp');
    expect($item['source'])->toBe(Source::Ai->value);
    expect($item['source_meta'])->toBe($this->rendered['source_meta']);

    $this->assertDatabaseHas('medias', [
        'id' => $item['id'],
        'path' => 'ai-images/x.webp',
        'collection' => 'ai-generated',
    ]);
});

test('forSingle returns an empty array when the generator renders nothing', function () {
    $this->mock(TemplateImageGenerator::class, function ($mock) {
        $mock->shouldReceive('render')->once()->andReturn(null);
    });

    $structured = [
        'image_title' => 'Tip',
        'image_body' => 'Do less',
        'image_keywords' => [],
    ];

    $pipeline = app(PostImagePipeline::class);

    $media = $pipeline->forSingle($this->workspace, $this->account, $structured, ContentType::InstagramFeed);

    expect($media)->toBe([]);
});

test('forCarousel returns one media item per slide', function () {
    $this->mock(TemplateImageGenerator::class, function ($mock) {
        $mock->shouldReceive('render')->times(3)->andReturn($this->rendered);
    });

    $structured = [
        'caption' => 'Swipe',
        'slides' => [
            ['title' => 'Tip 1', 'body' => 'First', 'image_keywords' => ['a']],
            ['title' => 'Tip 2', 'body' => 'Second', 'image_keywords' => ['b']],
            ['title' => 'Tip 3', 'body' => 'Third', 'image_keywords' => ['c']],
        ],
    ];

    $pipeline = app(PostImagePipeline::class);

    $media = $pipeline->forCarousel($this->workspace, $this->account, $structured, ContentType::InstagramFeed);

    expect($media)->toHaveCount(3);
    expect($media[0])->toHaveKeys(['id', 'path', 'url', 'type', 'mime_type', 'source', 'source_meta']);

    $this->assertDatabaseCount('medias', 3);
});

test('forCarousel resumes: reused slides are not re-rendered and are returned unchanged', function () {
    // Only slide index 1 (the previously-failed one) should be rendered.
    $this->mock(TemplateImageGenerator::class, function ($mock) {
        $mock->shouldReceive('render')->once()->andReturn($this->rendered);
    });

    $structured = [
        'caption' => 'Swipe',
        'slides' => [
            ['title' => 'Tip 1', 'body' => 'First', 'image_keywords' => ['a']],
            ['title' => 'Tip 2', 'body' => 'Second', 'image_keywords' => ['b']],
            ['title' => 'Tip 3', 'body' => 'Third', 'image_keywords' => ['c']],
        ],
    ];

    // Slides 0 and 2 already rendered on a prior attempt.
    $existing = [
        0 => ['id' => 'kept-0', 'path' => 'ai-images/kept0.webp', 'source' => Source::Ai->value],
        2 => ['id' => 'kept-2', 'path' => 'ai-images/kept2.webp', 'source' => Source::Ai->value],
    ];

    $pipeline = app(PostImagePipeline::class);

    $media = $pipeline->forCarousel(
        $this->workspace,
        $this->account,
        $structured,
        ContentType::InstagramFeed,
        existingSlideMedia: $existing,
    );

    // Keyed by slide index, all three present.
    expect(array_keys($media))->toBe([0, 1, 2]);
    // Reused slides passed through verbatim (no new Media rows for them).
    expect($media[0])->toBe($existing[0]);
    expect($media[2])->toBe($existing[2]);
    // Only the one newly-rendered slide created a Media row.
    $this->assertDatabaseCount('medias', 1);
});

test('forCarousel resume leaves a gap when a still-missing slide renders nothing', function () {
    $this->mock(TemplateImageGenerator::class, function ($mock) {
        $mock->shouldReceive('render')->once()->andReturn(null);
    });

    $structured = [
        'caption' => 'Swipe',
        'slides' => [
            ['title' => 'Tip 1', 'body' => 'First', 'image_keywords' => ['a']],
            ['title' => 'Tip 2', 'body' => 'Second', 'image_keywords' => ['b']],
        ],
    ];

    $existing = [
        0 => ['id' => 'kept-0', 'path' => 'ai-images/kept0.webp', 'source' => Source::Ai->value],
    ];

    $pipeline = app(PostImagePipeline::class);

    $media = $pipeline->forCarousel(
        $this->workspace,
        $this->account,
        $structured,
        ContentType::InstagramFeed,
        existingSlideMedia: $existing,
    );

    // Slide 1 still failed -> index 1 absent (gap), only the reused slide 0 present.
    expect(array_keys($media))->toBe([0]);
    expect($media[0])->toBe($existing[0]);
});
