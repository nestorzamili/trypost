<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkspaceConversation\Message\Role;
use App\Enums\WorkspaceConversation\Status;
use Database\Factories\WorkspaceConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceConversation extends Model
{
    /** @use HasFactory<WorkspaceConversationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'workspace_id',
        'user_id',
        'title',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WorkspaceConversationMessage::class)->oldest();
    }

    /**
     * The conversation's opening user message, selected as a plain attribute
     * for the history fallback title. Titling runs in the background and may
     * fail (or the provider may be down entirely); the list must never depend
     * on it, so an untitled conversation shows this instead of disappearing.
     *
     * A correlated subselect, deliberately not a HasOne-of-many: of-many
     * always aggregates the related key as well (MIN/MAX(id)), and
     * PostgreSQL has no MIN/MAX(uuid), so that shape 500s on Cloud's
     * engine while only ever working on MySQL.
     */
    public function scopeWithFallbackTitle(Builder $query): Builder
    {
        return $query->addSelect([
            'fallback_message' => WorkspaceConversationMessage::select('content')
                ->whereColumn('workspace_conversation_id', 'workspace_conversations.id')
                ->where('role', Role::User)
                ->oldest()
                ->limit(1),
        ]);
    }

    /**
     * This user's conversations in this workspace, regardless of title.
     *
     * The sole ownership predicate: everything that needs to resolve a
     * specific conversation for its owner (show/update/destroy) uses this,
     * not scopeListable(), so a best-effort background job (title
     * generation) can never make a conversation with real messages
     * permanently unreachable by the person who wrote them.
     */
    public function scopeOwnedBy(Builder $query, string $workspaceId, string $userId): Builder
    {
        return $query->where('workspace_id', $workspaceId)
            ->where('user_id', $userId);
    }

    /**
     * Conversations shown in the history: this user's, in this workspace,
     * newest first — titled or not. An untitled conversation is a real
     * conversation whose background titling hasn't finished (or failed), so
     * excluding it would hide history exactly when the assistant is already
     * struggling. See firstUserMessage() for what renders instead of a title.
     */
    public function scopeListable(Builder $query, string $workspaceId, string $userId): Builder
    {
        return $query->ownedBy($workspaceId, $userId)
            ->latest('updated_at');
    }

    public function isIdle(): bool
    {
        return $this->status === Status::Idle;
    }
}
