<?php

declare(strict_types=1);

namespace App\Ai\Tools\Brand;

use App\Ai\Tools\WorkspaceAdminTool;
use App\Http\Resources\Chat\ChatBrandResource;
use App\Models\BrandVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Deleting a variant always asks for confirmation first: generation in its
 * language silently falls back to the default brand afterwards, which is
 * exactly the kind of quiet behavior change worth one click.
 */
class DeleteBrandVariantTool extends WorkspaceAdminTool implements Approvable
{
    use InteractsWithApprovals;

    public function name(): string
    {
        return 'delete_brand_variant';
    }

    public function description(): Stringable|string
    {
        return 'Delete a language variant of the workspace brand. Always asks the user to confirm first. Call get_brand first and pass a real id.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'variant_id' => $schema->string()->required()->description('The id of the variant to delete, as returned by get_brand.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        if ($this->writeDenied()) {
            return false;
        }

        if ($this->resolveVariant($request->string('variant_id')->value()) === null) {
            return false;
        }

        return Approval::required(__('chat.approvals.delete_brand_variant'));
    }

    protected function run(Request $request): string
    {
        $variant = $this->resolveVariant($request->string('variant_id')->value());

        if ($variant === null) {
            return $this->error(__('chat.tools.brand_variant_not_found'));
        }

        $snapshot = ChatBrandResource::variantData($variant);
        $variant->delete();

        return $this->json(['data' => [
            ...$snapshot,
            'deleted' => true,
        ]]);
    }

    private function resolveVariant(?string $variantId): ?BrandVariant
    {
        if (blank($variantId)) {
            return null;
        }

        return $this->workspace->brandVariants()->find($variantId);
    }
}
