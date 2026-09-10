<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Ai\Tools\ToolReplayer;
use App\Http\Requests\App\Chat\UpdateChatConversationRequest;
use App\Http\Resources\Chat\ConversationMessageResource;
use App\Http\Resources\Chat\ConversationResource;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceConversation;
use App\Models\WorkspaceConversationMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function index(Request $request): Response
    {
        [$workspace, $user] = $this->resolveWorkspaceAndUser($request);

        return Inertia::render('chat/Index', [
            'conversations' => ConversationResource::collection($this->listableQuery($workspace, $user)->get()),
        ]);
    }

    public function show(Request $request, string $conversation): Response
    {
        [$workspace, $user] = $this->resolveWorkspaceAndUser($request);

        $model = $this->findConversation($workspace, $user, $conversation)->load(['messages', 'workspace', 'user']);

        $payloads = app(ToolReplayer::class)->replay($model);

        return Inertia::render('chat/Index', [
            'conversations' => ConversationResource::collection($this->listableQuery($workspace, $user)->get()),
            'conversation' => new ConversationResource($model),
            'messages' => $model->messages
                ->map(fn (WorkspaceConversationMessage $message): array => (new ConversationMessageResource($message, $payloads))->resolve())
                ->all(),
        ]);
    }

    public function update(UpdateChatConversationRequest $request, string $conversation): RedirectResponse
    {
        [$workspace, $user] = $this->resolveWorkspaceAndUser($request);

        $model = $this->findConversation($workspace, $user, $conversation);

        $model->update(['title' => $request->validated('title')]);

        return back();
    }

    public function destroy(Request $request, string $conversation): RedirectResponse
    {
        [$workspace, $user] = $this->resolveWorkspaceAndUser($request);

        $model = $this->findConversation($workspace, $user, $conversation);

        $model->delete();

        // The history panel lets the user delete a conversation they are not
        // currently in. Bouncing them to a blank chat for that would discard
        // the thread they were reading, so stay put when asked to.
        if ($request->boolean('stay')) {
            return back();
        }

        return redirect()->route('app.chat');
    }

    /**
     * Resolve the current user's workspace, authorised for chat access.
     *
     * @return array{0: Workspace, 1: User}
     */
    private function resolveWorkspaceAndUser(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Workspace $workspace */
        $workspace = $user->currentWorkspace;

        $this->authorize('view', $workspace);

        return [$workspace, $user];
    }

    /**
     * Resolve a single conversation the user owns, so another user's
     * conversation 404s rather than 403s — matching how the rest of the
     * app hides records it does not want to acknowledge.
     *
     * Deliberately scopeOwnedBy(), the ownership predicate with no
     * presentation concerns: resolving by the looser listable() scope would
     * still work today, but ownership is the load-bearing half and must not
     * silently inherit whatever the history list filters next.
     */
    private function findConversation(Workspace $workspace, User $user, string $id): WorkspaceConversation
    {
        return WorkspaceConversation::query()->ownedBy($workspace->id, $user->id)->withFallbackTitle()->findOrFail($id);
    }

    private function listableQuery(Workspace $workspace, User $user): Builder
    {
        return WorkspaceConversation::query()
            ->listable($workspace->id, $user->id)
            ->withFallbackTitle();
    }
}
