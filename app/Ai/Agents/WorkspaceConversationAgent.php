<?php

declare(strict_types=1);

namespace App\Ai\Agents;

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
use App\Ai\Tools\Post\DeletePostTool;
use App\Ai\Tools\Post\GeneratePostTool;
use App\Ai\Tools\Post\GetPostMetricsTool;
use App\Ai\Tools\Post\GetPostTool;
use App\Ai\Tools\Post\ListPostsTool;
use App\Ai\Tools\Post\PublishPostTool;
use App\Ai\Tools\Post\SchedulePostTool;
use App\Ai\Tools\Post\StartPostGenerationTool;
use App\Ai\Tools\Post\UpdatePostTool;
use App\Ai\Tools\Signature\CreateSignatureTool;
use App\Ai\Tools\Signature\DeleteSignatureTool;
use App\Ai\Tools\Signature\ListSignaturesTool;
use App\Ai\Tools\Signature\UpdateSignatureTool;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

/**
 * The workspace chat agent: carries the system prompt, exposes the post
 * tools plus the workspace read tools (brand, labels, signatures, assets)
 * scoped to the workspace, and remembers conversation history via the SDK's
 * conversation store.
 *
 * Deliberately does NOT define messages() — that would take precedence over
 * RemembersConversations and silently disable history loading.
 */
#[MaxSteps(8)]
#[Timeout(180)]
class WorkspaceConversationAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(
        public Workspace $workspace,
        public User $user,
    ) {}

    public function instructions(): string
    {
        return view('prompts.conversation.assistant', [
            'brand_name' => $this->workspace->name ?? '',
            'brand_description' => $this->workspace->brand_description ?? '',
            'brand_voice_traits' => $this->workspace->brand_voice_traits ?? [],
            'content_language' => $this->workspace->content_language,
            'connected_platforms' => $this->workspace->socialAccounts()
                ->get()
                ->map(fn ($account): string => $account->platform->value)
                ->unique()
                ->values()
                ->all(),
        ])->render();
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return [
            new ListPostsTool($this->workspace, $this->user),
            new GetPostTool($this->workspace, $this->user),
            new GetPostMetricsTool($this->workspace, $this->user),
            new StartPostGenerationTool($this->workspace, $this->user),
            new GeneratePostTool($this->workspace, $this->user),
            new CreatePostTool($this->workspace, $this->user),
            new UpdatePostTool($this->workspace, $this->user),
            new SchedulePostTool($this->workspace, $this->user),
            new PublishPostTool($this->workspace, $this->user),
            new DeletePostTool($this->workspace, $this->user),
            new GetBrandTool($this->workspace, $this->user),
            new ListLabelsTool($this->workspace, $this->user),
            new ListSignaturesTool($this->workspace, $this->user),
            new ListAssetsTool($this->workspace, $this->user),
            new GetAssetTool($this->workspace, $this->user),
            new AttachExistingAssetTool($this->workspace, $this->user),
            new CreateLabelTool($this->workspace, $this->user),
            new UpdateLabelTool($this->workspace, $this->user),
            new DeleteLabelTool($this->workspace, $this->user),
            new CreateSignatureTool($this->workspace, $this->user),
            new UpdateSignatureTool($this->workspace, $this->user),
            new DeleteSignatureTool($this->workspace, $this->user),
            new UpdateBrandTool($this->workspace, $this->user),
            new CreateBrandVariantTool($this->workspace, $this->user),
            new UpdateBrandVariantTool($this->workspace, $this->user),
            new DeleteBrandVariantTool($this->workspace, $this->user),
            new DeleteBrandReferencePhotoTool($this->workspace, $this->user),
            new AddBrandReferenceFromUrlTool($this->workspace, $this->user),
            new DeleteAssetTool($this->workspace, $this->user),
            new AddAssetFromUrlTool($this->workspace, $this->user),
        ];
    }
}
