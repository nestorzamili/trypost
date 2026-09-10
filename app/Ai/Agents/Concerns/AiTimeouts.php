<?php

declare(strict_types=1);

namespace App\Ai\Agents\Concerns;

use Laravel\Ai\Attributes\Timeout;

/**
 * Shared timeout budget for AI text agents.
 *
 * The Laravel AI SDK's {@see Timeout} attribute only
 * accepts a compile-time constant, so an env-driven value cannot be injected
 * there. This constant is the single source of truth the generation-path
 * agents reference, keeping the number in one place instead of repeating a
 * magic literal across every agent.
 *
 * Without an explicit timeout the SDK falls back to the HTTP client default of
 * 60s, which is shorter than the `ai` queue worker's 930s budget and was the
 * cause of `cURL error 28: Operation timed out after 60002ms` on slow DeepSeek
 * responses (the generator plus the humanizer are two sequential round-trips).
 * 180s matches WorkspaceConversationAgent and leaves ample worker headroom.
 */
final class AiTimeouts
{
    /** Text-generation request timeout, in seconds. */
    public const TEXT_SECONDS = 180;
}
