<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * Titles a conversation from its opening user message. Runs on the
 * application's default provider (AI_TEXT_PROVIDER) so it never needs a
 * second provider configured next to the one the chat itself uses. It fires
 * on every first turn and is not user-facing AI usage worth metering.
 */
class ConversationTitleGenerator implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return view('prompts.conversation.title')->render();
    }
}
