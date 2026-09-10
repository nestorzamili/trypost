<?php

declare(strict_types=1);

namespace App\Ai\Tools\Signature;

use App\Actions\Signature\DeleteSignature;
use App\Ai\Tools\WorkspaceWriteTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Deleting a signature always asks for confirmation first. Already published
 * posts are unaffected (they keep the text they went out with), but the
 * signature is workspace configuration rather than ephemera, so there is no
 * "nothing at stake" shortcut — and never ask to confirm something that is
 * going to fail (unknown id, denied role).
 */
class DeleteSignatureTool extends WorkspaceWriteTool implements Approvable
{
    use InteractsWithApprovals;

    public function name(): string
    {
        return 'delete_signature';
    }

    public function description(): Stringable|string
    {
        return 'Delete a signature from the current workspace. Always asks the user to confirm first. Call list_signatures first and pass a real id.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'signature_id' => $schema->string()->required()->description('The id of the signature to delete, as returned by list_signatures.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        if ($this->writeDenied()) {
            return false;
        }

        if ($this->resolveSignature($request->string('signature_id')->value()) === null) {
            return false;
        }

        return Approval::required(__('chat.approvals.delete_signature'));
    }

    protected function run(Request $request): string
    {
        $signature = $this->resolveSignature($request->string('signature_id')->value());

        if ($signature === null) {
            return $this->error(__('chat.tools.signature_not_found'));
        }

        DeleteSignature::execute($signature);

        return $this->json(['data' => [
            'id' => $signature->id,
            'deleted' => true,
        ]]);
    }
}
