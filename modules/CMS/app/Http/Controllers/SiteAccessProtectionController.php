<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteAccessProtectionRequest;
use App\Services\SiteAccessProtectionService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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
    public function create(): Response
    {
        return Inertia::render('cms/site-access-protection/form', [
            'message' => setting(
                'site_access_protection_message',
                setting('password_protected_message', __('general.site_access_protection_description'))
            ),
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
