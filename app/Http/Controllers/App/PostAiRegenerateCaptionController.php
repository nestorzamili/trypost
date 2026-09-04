<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Ai\RegeneratePostCaptionRequest;
use App\Jobs\Ai\RegeneratePostCaption;
use App\Models\Post;
use App\Support\PostStatusRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PostAiRegenerateCaptionController extends Controller
{
    public function regenerate(RegeneratePostCaptionRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        if (PostStatusRules::blocksEditing($post)) {
            return response()->json([
                'message' => PostStatusRules::editBlockedMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $workspace = $request->user()->currentWorkspace;
        $gate = Gate::inspect('useAi', $workspace->account);

        if ($gate->denied()) {
            return response()->json(['message' => $gate->message()], Response::HTTP_PAYMENT_REQUIRED);
        }

        $regenerationId = $request->string('regeneration_id')->toString();

        RegeneratePostCaption::dispatch(
            workspaceId: $workspace->id,
            userId: $request->user()->id,
            regenerationId: $regenerationId,
            content: $request->string('content')->toString(),
            instruction: $request->string('instruction')->toString() ?: null,
        );

        return response()->json([
            'regeneration_id' => $regenerationId,
            'channel' => "user.{$request->user()->id}.ai-caption.{$regenerationId}",
        ], Response::HTTP_ACCEPTED);
    }
}
