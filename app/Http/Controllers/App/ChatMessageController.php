<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Ai\Agents\WorkspaceConversationAgent;
use App\Enums\WorkspaceConversation\Message\Role;
use App\Enums\WorkspaceConversation\Status;
use App\Http\Requests\App\Chat\StoreChatMessageRequest;
use App\Jobs\Ai\GenerateConversationTitle;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceConversation;
use App\Models\WorkspaceConversationMessage;
use App\Services\Ai\RecordAiUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Symfony\Component\HttpFoundation\Response;

class ChatMessageController extends Controller
{
    /**
     * How long a claimed turn may hold a conversation before another request may
     * take it over.
     *
     * then() only runs when the stream iterator completes normally, so a provider
     * error surviving failover, a mid-stream throw or a client disconnect leaves
     * the row in progress with nobody left to release it. Waiting on
     * ReleaseStalledConversations means locking the user out of their own
     * conversation for up to ten minutes behind a 409 that is no longer true, so
     * the claim heals itself first and the command stays the backstop for rows
     * nobody comes back to.
     *
     * A real turn is bounded by the agent's #[Timeout(180)] and finishes well
     * inside a minute, so five minutes never reclaims a healthy turn in
     * practice. Reclaiming one that is genuinely still streaming is not free:
     * WorkspaceConversationStore::storeUserMessage() only guards against the
     * single trailing message row, so the reclaimed turn's end-of-turn write
     * misses that guard once a newer turn has appended its own user message,
     * and duplicates the older prompt at the tail of the conversation. Its
     * then() also still writes status = Idle after the newer turn has taken
     * over, disarming the 409 guard early and allowing a third concurrent
     * turn to start. Both are accepted as the lesser failure mode: a rare
     * duplicated prompt beats locking a user out of their own conversation
     * for up to ten minutes after a routine provider error.
     */
    private const STALE_TURN_MINUTES = 5;

    /**
     * Run one chat turn and stream it back over Vercel's data stream protocol.
     */
    public function store(StoreChatMessageRequest $request, string $conversation): StreamableAgentResponse|JsonResponse
    {
        [$workspace, $user] = $this->resolveWorkspaceAndUser($request);

        $gate = Gate::inspect('useAi', $workspace->account);
        if ($gate->denied()) {
            return response()->json(['message' => $gate->message()], Response::HTTP_PAYMENT_REQUIRED);
        }

        $prompt = $this->prompt($request);

        if ($prompt instanceof Decisions && $this->isSettledReplay($conversation, $workspace, $user, $prompt)) {
            return response()->json([
                'message' => __('chat.errors.request_failed'),
                'code' => 'decisions_resolved',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $model = $this->claim($conversation, $workspace, $user, $prompt);

        return (new WorkspaceConversationAgent($workspace, $user))
            ->continue($model->id, as: $user)
            ->stream($prompt)
            ->usingVercelDataProtocol()
            ->then(function (StreamedAgentResponse $response) use ($model, $workspace, $user): void {
                RecordAiUsage::recordText(
                    workspace: $workspace,
                    promptTokens: $response->usage->promptTokens,
                    completionTokens: $response->usage->completionTokens,
                    provider: (string) $response->meta->provider,
                    model: (string) $response->meta->model,
                    userId: $user->id,
                    metadata: ['agent' => 'workspace_conversation'],
                );

                $model->update(['status' => Status::Idle]);

                if ($model->title === null) {
                    GenerateConversationTitle::dispatch($model->id);
                }
            });
    }

    /**
     * Release a turn stuck in progress so the conversation accepts new
     * messages again.
     *
     * A provider error mid-stream, a client disconnect, or an explicit stop
     * leaves the row in progress with nobody left to run then(): the 5-minute
     * self-heal in claim() and ReleaseStalledConversations only bound the
     * damage. The frontend calls this when the user stops a turn and
     * best-effort when a turn errors (any non-409 failure means no other turn
     * is actually running), so neither case locks the user out.
     *
     * Only an in-progress row is touched: bumping updated_at on an idle
     * conversation would reorder it to the top of the history for no reason.
     */
    public function cancel(Request $request, string $conversation): JsonResponse
    {
        [$workspace, $user] = $this->resolveWorkspaceAndUser($request);

        $model = WorkspaceConversation::query()
            ->ownedBy($workspace->id, $user->id)
            ->findOrFail($conversation);

        if ($model->status === Status::InProgress) {
            $model->forceFill(['status' => Status::Idle])->save();
        }

        return response()->json(['status' => $model->status->value]);
    }

    /**
     * Resolve the current user's workspace, authorised for chat access.
     *
     * @return array{0: Workspace, 1: User}
     */
    private function resolveWorkspaceAndUser(StoreChatMessageRequest|Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Workspace $workspace */
        $workspace = $user->currentWorkspace;

        $this->authorize('view', $workspace);

        return [$workspace, $user];
    }

    /**
     * Take exclusive ownership of the conversation for this turn.
     *
     * The row lock, the idle check, the user-message write and the flip to
     * in-progress all live in one transaction on purpose. Locking only long
     * enough to read the status would let two simultaneous requests both see
     * Idle and both start streaming: the lock is released at commit, so the
     * check has to happen while it is still held, and the status has to be
     * written before it is released. Holding the lock across all four means the
     * loser blocks on the SELECT ... FOR UPDATE, wakes up after the winner has
     * committed InProgress, and gets a 409.
     *
     * The conversation is looked up with its client-supplied id rather than
     * route-model binding: the very first message of a conversation targets a
     * row that does not exist yet.
     *
     * The retries matter only off Postgres: when the row does not exist yet, MySQL
     * and MariaDB take gap locks and deadlock the loser (1213) instead of blocking
     * it. Retrying sends that request down the row-exists path, where it gets its
     * 409 instead of a 500.
     */
    private function claim(
        string $conversation,
        Workspace $workspace,
        User $user,
        Decisions|string $prompt,
    ): WorkspaceConversation {
        return DB::transaction(function () use ($conversation, $workspace, $user, $prompt): WorkspaceConversation {
            $model = WorkspaceConversation::withTrashed()
                ->lockForUpdate()
                ->firstOrCreate(
                    ['id' => $conversation],
                    [
                        'workspace_id' => $workspace->id,
                        'user_id' => $user->id,
                        'status' => Status::Idle,
                    ],
                );

            abort_if(
                $model->user_id !== $user->id || $model->workspace_id !== $workspace->id,
                Response::HTTP_FORBIDDEN,
            );

            abort_if($model->trashed(), Response::HTTP_NOT_FOUND);

            abort_if(
                ! $model->isIdle() && ! $this->hasStalledTurn($model),
                Response::HTTP_CONFLICT,
                __('chat.errors.turn_in_progress'),
            );

            // The user's message is persisted before the stream opens so a dropped
            // connection never loses what they typed. The exact same string is handed
            // to the agent below: WorkspaceConversationStore::storeUserMessage() is
            // idempotent only against a trailing user row whose content matches the
            // prompt byte for byte, so any trimming or decorating here would make the
            // SDK's end-of-turn write a duplicate instead of a no-op...
            if (is_string($prompt)) {
                WorkspaceConversationMessage::create([
                    'workspace_conversation_id' => $model->id,
                    'role' => Role::User,
                    'content' => $prompt,
                ]);
            }

            // forceFill + save(), not update(): reclaiming a stalled turn leaves the
            // status attribute unchanged, and Eloquent skips the query — and the
            // timestamp — when nothing is dirty. The next request would then see the
            // same stale updated_at and reclaim the conversation all over again...
            $model->forceFill([
                'status' => Status::InProgress,
                'updated_at' => now(),
            ])->save();

            return $model;
        }, attempts: 3);
    }

    /**
     * Determine whether an in-progress turn has been abandoned and may be taken over.
     *
     * updated_at is restamped every time a turn is claimed, so it doubles as the
     * turn's start time.
     */
    private function hasStalledTurn(WorkspaceConversation $model): bool
    {
        return $model->updated_at !== null
            && $model->updated_at->lt(now()->subMinutes(self::STALE_TURN_MINUTES));
    }

    /**
     * Whether every decision id in this resume already has a stored tool
     * result in the conversation — i.e. the client is replaying approvals a
     * previous turn settled, not resuming a paused run.
     *
     * The client's `approval-responded` parts are terminal history: the
     * approval continuation streams into the same step, so no new step
     * boundary ever moves them out of resend scope, and a stale client (or a
     * tab predating the submitted-tracking) replays them after every approval
     * turn. Handing those to the agent would throw an approval-mismatch 500
     * AFTER the claim committed, stranding the conversation in progress
     * behind an error the user can neither fix nor retry past. A 422 with a
     * machine-readable code instead refuses before any claim is taken, so the
     * client can absorb the settled ids and carry on silently — nothing was
     * lost, the approvals already took effect.
     *
     * Only a pure replay qualifies: a mix with even one pending id falls
     * through to the agent, which still validates (and loudly rejects) a
     * genuinely mismatched set.
     */
    private function isSettledReplay(
        string $conversation,
        Workspace $workspace,
        User $user,
        Decisions $prompt,
    ): bool {
        $ids = array_keys($prompt->all());

        if ($ids === []) {
            return false;
        }

        $model = WorkspaceConversation::query()
            ->ownedBy($workspace->id, $user->id)
            ->find($conversation);

        if ($model === null) {
            return false;
        }

        $resolved = $model->messages()
            ->whereNotNull('tool_results')
            ->pluck('tool_results')
            ->flatten(1)
            ->map(fn ($result): ?string => is_array($result) ? data_get($result, 'id') : null)
            ->filter()
            ->all();

        return array_diff($ids, $resolved) === [];
    }

    /**
     * Resolve the turn's prompt: a new user message, or the approval decisions
     * that resume a run paused on a tool call.
     */
    private function prompt(StoreChatMessageRequest $request): Decisions|string
    {
        $validated = $request->validated();
        $decisions = data_get($validated, 'decisions');

        if (blank($decisions)) {
            return (string) data_get($validated, 'message');
        }

        return Decisions::from(collect($decisions)->map(
            fn (array $decision): Decision => data_get($decision, 'action') === 'approve'
                ? Decision::approve()
                : Decision::reject(data_get($decision, 'result')),
        )->all());
    }
}
