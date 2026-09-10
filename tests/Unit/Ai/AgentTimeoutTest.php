<?php

declare(strict_types=1);

use App\Ai\Agents\Concerns\AiTimeouts;
use App\Ai\Agents\PostBriefRefiner;
use App\Ai\Agents\PostCaptionRegenerator;
use App\Ai\Agents\PostContentGenerator;
use App\Ai\Agents\PostContentHumanizer;
use App\Ai\Agents\PostContentStreamer;
use App\Ai\Agents\PostImageRegenerator;
use Laravel\Ai\Attributes\Timeout;

function timeoutSeconds(string $agentClass): ?int
{
    $attributes = (new ReflectionClass($agentClass))->getAttributes(Timeout::class);

    if ($attributes === []) {
        return null;
    }

    return $attributes[0]->newInstance()->value;
}

it('declares the shared text timeout on every text-generation agent', function (string $agentClass): void {
    expect(timeoutSeconds($agentClass))->toBe(AiTimeouts::TEXT_SECONDS);
})->with([
    PostBriefRefiner::class,
    PostCaptionRegenerator::class,
    PostContentGenerator::class,
    PostContentHumanizer::class,
    PostContentStreamer::class,
    PostImageRegenerator::class,
]);
