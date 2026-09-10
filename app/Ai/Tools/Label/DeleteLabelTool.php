<?php

declare(strict_types=1);

namespace App\Ai\Tools\Label;

use App\Actions\Label\DeleteLabel;
use App\Ai\Tools\WorkspaceWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Deleting a label detaches it from every post that carries it, so it always
 * asks for confirmation first — unlike deleting a draft post, there is no
 * "nothing at stake" case: even an unused label is workspace configuration,
 * not ephemera. The same refusal rules as the post delete tool apply: never
 * ask to confirm something that is going to fail (unknown id, denied role).
 */
class DeleteLabelTool extends WorkspaceWriteTool implements Approvable
{
    use InteractsWithApprovals;

    public function name(): string
    {
        return 'delete_label';
    }

    public function description(): Stringable|string
    {
        return 'Delete a label from the current workspace. Posts using it lose that tag. Always asks the user to confirm first. Call list_labels first and pass a real id.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'label_id' => $schema->string()->required()->description('The id of the label to delete, as returned by list_labels.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        if ($this->writeDenied()) {
            return false;
        }

        if ($this->resolveLabel($request->string('label_id')->value()) === null) {
            return false;
        }

        return Approval::required(__('chat.approvals.delete_label'));
    }

    protected function run(Request $request): string
    {
        $label = $this->resolveLabel($request->string('label_id')->value());

        if ($label === null) {
            return $this->error(__('chat.tools.label_not_found'));
        }

        $detachedFromPosts = $label->posts()->count();

        DeleteLabel::execute($label);

        return $this->json(['data' => [
            'id' => $label->id,
            'deleted' => true,
            'detached_from_posts' => $detachedFromPosts,
        ]]);
    }
}
