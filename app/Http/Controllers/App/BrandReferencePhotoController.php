<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\Media\BrandReferenceKind;
use App\Http\Requests\App\Brand\StoreBrandReferenceFromAssetRequest;
use App\Http\Requests\App\Brand\StoreBrandReferencePhotoRequest;
use App\Http\Requests\App\Brand\UpdateBrandReferencePhotoRequest;
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

    public function search(Request $request): AnonymousResourceCollection
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace instanceof Workspace, SymfonyResponse::HTTP_NOT_FOUND);
        $this->authorize('view', $workspace);

        $term = trim((string) $request->input('search', ''));
        $kind = $request->input('kind');

        $kinds = array_map(fn (BrandReferenceKind $case) => $case->value, BrandReferenceKind::cases());

        $references = $workspace->getMedia('brand_references')
            ->when($term !== '', fn ($query) => $query->whereLike('original_filename', '%'.$term.'%'))
            ->when(is_string($kind) && in_array($kind, $kinds, true), fn ($query) => $query->where('meta->kind', $kind))
            ->latest()
            ->paginate(config('app.pagination.default'));

        return MediaResource::collection($references)
            ->additional(['unfiltered_total' => $workspace->getMedia('brand_references')->count()]);
    }

    public function store(StoreBrandReferencePhotoRequest $request): MediaResource
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace instanceof Workspace, SymfonyResponse::HTTP_NOT_FOUND);
        $this->authorize('update', $workspace);

        $this->assertWithinLimit($workspace);

        $clientMeta = [];
        if ($request->filled('label')) {
            $clientMeta['label'] = trim((string) $request->input('label'));
        }
        if ($request->filled('kind')) {
            $clientMeta['kind'] = $request->input('kind');
        }

        $media = $workspace->addMedia($request->file('photo'), 'brand_references', $clientMeta);

        return new MediaResource($media);
    }

    public function update(UpdateBrandReferencePhotoRequest $request, Media $media): MediaResource
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace instanceof Workspace, SymfonyResponse::HTTP_NOT_FOUND);
        $this->authorize('update', $workspace);

        $this->assertScopedToWorkspace($workspace, $media);

        $meta = $media->meta ?? [];
        foreach ($request->validated() as $key => $value) {
            if ($value === null || trim((string) $value) === '') {
                unset($meta[$key]);
            } else {
                $meta[$key] = $key === 'label' ? trim((string) $value) : $value;
            }
        }
        $media->update(['meta' => $meta]);

        return new MediaResource($media->fresh());
    }

    public function fromAsset(StoreBrandReferenceFromAssetRequest $request): MediaResource
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace instanceof Workspace, SymfonyResponse::HTTP_NOT_FOUND);
        $this->authorize('update', $workspace);

        $this->assertWithinLimit($workspace);

        $source = $workspace->media()
            ->whereKey($request->validated('asset_id'))
            ->where('collection', 'assets')
            ->first();

        abort_if(! $source instanceof Media || ! $source->isImage(), SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);

        $meta = [];
        foreach (['width', 'height'] as $dimension) {
            if (data_get($source->meta, $dimension) !== null) {
                $meta[$dimension] = data_get($source->meta, $dimension);
            }
        }
        if ($request->filled('label')) {
            $meta['label'] = trim((string) $request->input('label'));
        }
        if ($request->filled('kind')) {
            $meta['kind'] = $request->input('kind');
        }

        $media = $workspace->addMediaFromStoredPath(
            $source->path,
            $source->original_filename,
            $source->mime_type,
            $source->size,
            'brand_references',
            $meta,
        );

        return new MediaResource($media);
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;

        abort_if(! $workspace instanceof Workspace, SymfonyResponse::HTTP_NOT_FOUND);
        $this->authorize('update', $workspace);

        $this->assertScopedToWorkspace($workspace, $media);

        $media->delete();

        return response()->json([], SymfonyResponse::HTTP_NO_CONTENT);
    }

    private function assertScopedToWorkspace(Workspace $workspace, Media $media): void
    {
        if ($media->mediable_type !== $workspace->getMorphClass()
            || (string) $media->mediable_id !== (string) $workspace->id
            || $media->collection !== 'brand_references') {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }
    }

    private function assertWithinLimit(Workspace $workspace): void
    {
        if ($workspace->getMedia('brand_references')->count() >= BrandReferenceKind::MAX_REFERENCES) {
            abort(SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY, __('assets.references.limit_reached', ['max' => BrandReferenceKind::MAX_REFERENCES]));
        }
    }
}
