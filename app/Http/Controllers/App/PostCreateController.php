<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Actions\Ai\StartPostGeneration;
use App\Models\AiGeneration;
use App\Models\Workspace;
use App\Services\Ai\PostGenerationCatalog;
use App\Support\BillingCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class PostCreateController extends Controller
{
    public function create(Request $request): InertiaResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return redirect()->route('app.workspaces.create');
        }

        $this->authorize('createPost', $workspace);

        return Inertia::render('posts/Create', [
            'workspace' => $workspace,
            'catalog' => PostGenerationCatalog::forWorkspace($workspace),
            'date' => $request->query('date'),
            'brandReferences' => $workspace->getMedia('brand_references')->get()->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->url,
                'name' => $media->name,
            ])->values()->all(),
            'canManageBrandReferences' => $request->user()->can('update', $workspace),
        ]);
    }

    public function loading(Request $request, string $creationId): InertiaResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace instanceof Workspace) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->authorize('createPost', $workspace);

        $existing = AiGeneration::query()
            ->where('creation_id', $creationId)
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($existing && $existing->post_id && $existing->status->isTerminal() && ! $existing->error) {
            return redirect()->route('app.posts.edit', $existing->post_id);
        }

        $referenceMediaIds = array_values(array_filter(
            is_array($request->query('reference_media_ids'))
                ? $request->query('reference_media_ids')
                : explode(',', (string) $request->query('reference_media_ids', ''))
        ));

        return Inertia::render('posts/ai/Loading', [
            'creationId' => $creationId,
            'channel' => "user.{$request->user()->id}.ai-creation.{$creationId}",
            'imageCount' => (int) $request->query('images', '0'),
            'format' => (string) $request->query('format', ''),
            'prompt' => (string) $request->query('prompt', ''),
            'socialAccountId' => $request->query('social_account_id') ?: null,
            'date' => $request->query('date') ?: null,
            'style' => (string) ($request->query('style') ?: $request->query('template', 'image_card')),
            'template' => (string) ($request->query('template') ?: $request->query('style', 'image_card')),
            'applyBrandVisuals' => $request->boolean('apply_brand_visuals', true),
            'languageCode' => $request->query('language_code') ?: null,
            'useBrandReferences' => $request->boolean('use_brand_references', false),
            'referenceMediaIds' => $referenceMediaIds,
            'alreadyStarted' => $existing !== null,
        ]);
    }

    public function start(Request $request): JsonResponse|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace instanceof Workspace) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->authorize('createPost', $workspace);

        $result = StartPostGeneration::execute(
            $request->user(),
            $workspace,
            $request->all(),
        );

        if ($request->header('X-Inertia')) {
            return redirect()->route('app.posts.ai.loading', [
                'creationId' => $result['creation_id'],
            ]);
        }

        return response()->json($result, Response::HTTP_ACCEPTED);
    }

    public function status(Request $request, string $creationId): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace instanceof Workspace) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $generation = AiGeneration::query()
            ->where('creation_id', $creationId)
            ->where('workspace_id', $workspace->id)
            ->first();

        if ($generation === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => $generation->status->value,
            'post_id' => $generation->post_id,
            'error' => $generation->error,
        ]);
    }

    /**
     * Pre-flight credit check for the AI post wizard.
     *
     * Returns remaining credits so the frontend can disable the Generate
     * button or show a warning before the user commits to a generation.
     */
    public function credits(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if (! $workspace) {
            return response()->json(['allowed' => false, 'reason' => 'no_workspace']);
        }

        $gate = Gate::inspect('useAi', $workspace->account);

        if ($gate->denied()) {
            return response()->json([
                'allowed' => false,
                'reason' => 'credits_exhausted',
                'message' => $gate->message(),
            ]);
        }

        $cycle = BillingCycle::for($workspace->account);

        return response()->json([
            'allowed' => true,
            'remaining' => max(0, $cycle->creditAllotment() - $cycle->usedCredits()),
            'limit' => $cycle->creditAllotment(),
        ]);
    }
}
