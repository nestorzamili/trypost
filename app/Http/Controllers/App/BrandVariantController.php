<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Requests\App\BrandVariant\StoreBrandVariantRequest;
use App\Http\Requests\App\BrandVariant\UpdateBrandVariantRequest;
use App\Models\BrandVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BrandVariantController extends Controller
{
    public function store(StoreBrandVariantRequest $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('update', $workspace);
        $workspace->brandVariants()->create($request->validated());

        return back()->with('flash.banner', __('settings.brand.flash.variant_created'))
            ->with('flash.bannerStyle', 'success');
    }

    public function update(UpdateBrandVariantRequest $request, BrandVariant $brandVariant): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('update', $workspace);
        abort_unless($brandVariant->workspace_id === $workspace->id, 404);

        $brandVariant->update($request->validated());

        return back()->with('flash.banner', __('settings.brand.flash.variant_updated'))
            ->with('flash.bannerStyle', 'success');
    }

    public function destroy(Request $request, BrandVariant $brandVariant): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $this->authorize('update', $workspace);
        abort_unless($brandVariant->workspace_id === $workspace->id, 404);

        $brandVariant->delete();

        return back()->with('flash.banner', __('settings.brand.flash.variant_deleted'))
            ->with('flash.bannerStyle', 'success');
    }
}
