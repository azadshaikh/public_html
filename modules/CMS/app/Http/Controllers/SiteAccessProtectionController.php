<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteAccessProtectionRequest;
use App\Services\SiteAccessProtectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteAccessProtectionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        /**
         * The site access protection service instance.
         */
        protected SiteAccessProtectionService $siteAccessProtectionService
    ) {}

    /**
     * Show the site access protection form.
     */
    public function create(): View
    {
        /** @var view-string $view */
        $view = 'cms::site-access-protection.form';

        return view($view, [
        ]);
    }

    /**
     * Verify the site access password and redirect to intended URL.
     */
    public function store(SiteAccessProtectionRequest $request): RedirectResponse
    {
        // Mark site access as verified in session
        $this->siteAccessProtectionService->markSiteAccessAsVerified();

        // Get the intended URL and clear it from session
        $intendedUrl = $this->siteAccessProtectionService->getAndClearIntendedUrl('/');

        return redirect($intendedUrl)->with('success', __('general.site_access_protection_verified'));
    }
}
