<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\Brand\StoreBrandReferencePhotoRequest;
use App\Http\Resources\App\MediaResource;
use App\Models\Media;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BrandReferencePhotoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace instanceof Workspace, SymfonyResponse::HTTP_NOT_FOUND);
        $this->authorize('view', $workspace);

        $references = $workspace->getMedia('brand_references')->latest()->get();

        return MediaResource::collection($references);
    }

    public function store(StoreBrandReferencePhotoRequest $request): MediaResource
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace instanceof Workspace, SymfonyResponse::HTTP_NOT_FOUND);
        $this->authorize('update', $workspace);

        $clientMeta = [];
        if ($request->filled('label')) {
            $clientMeta['label'] = trim((string) $request->input('label'));
        }

        $media = $workspace->addMedia($request->file('photo'), 'brand_references', $clientMeta);

        return new MediaResource($media);
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace instanceof Workspace, SymfonyResponse::HTTP_NOT_FOUND);
        $this->authorize('update', $workspace);

        if ($media->mediable_type !== $workspace->getMorphClass()
            || (string) $media->mediable_id !== (string) $workspace->id
            || $media->collection !== 'brand_references') {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }

        $media->delete();

        return response()->json([], SymfonyResponse::HTTP_NO_CONTENT);
    }
}
