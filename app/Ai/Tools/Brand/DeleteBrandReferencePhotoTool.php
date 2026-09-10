<?php

declare(strict_types=1);

namespace App\Ai\Tools\Brand;

use App\Ai\Tools\WorkspaceAdminTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The file itself is removed, not just the record, so deletion always asks
 * for confirmation first.
 */
class DeleteBrandReferencePhotoTool extends WorkspaceAdminTool implements Approvable
{
    use InteractsWithApprovals;

    public function name(): string
    {
        return 'delete_brand_reference_photo';
    }

    public function description(): Stringable|string
    {
        return 'Delete a brand reference photo from the current workspace. Always asks the user to confirm first. Call get_brand first and pass a real photo id.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'photo_id' => $schema->string()->required()->description('The id of the reference photo to delete, as returned by get_brand.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        if ($this->writeDenied()) {
            return false;
        }

        if ($this->resolveBrandReference($request->string('photo_id')->value()) === null) {
            return false;
        }

        return Approval::required(__('chat.approvals.delete_brand_reference'));
    }

    protected function run(Request $request): string
    {
        $photo = $this->resolveBrandReference($request->string('photo_id')->value());

        if ($photo === null) {
            return $this->error(__('chat.tools.brand_reference_not_found'));
        }

        $snapshot = [
            'id' => $photo->id,
            'original_filename' => $photo->original_filename,
            'label' => data_get($photo->meta, 'label'),
        ];

        $photo->delete();

        return $this->json(['data' => [
            ...$snapshot,
            'deleted' => true,
        ]]);
    }
}
