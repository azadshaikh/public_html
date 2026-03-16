<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\RecacheApplication;
use App\Models\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Http\Requests\UpdateDefaultPagesRequest;
use Modules\CMS\Services\CmsDefaultPagesCacheService;
use Modules\CMS\Services\PageService;

/**
 * DefaultPagesSettingController
 *
 * Manages the "Default Pages" settings where administrators can configure
 * which CMS pages serve as important site pages (home, blog, contact, about,
 * privacy policy, terms of service).
 *
 * Similar to WordPress's Reading Settings.
 */
class DefaultPagesSettingController extends Controller implements HasMiddleware
{
    /**
     * Cache key for default pages URLs.
     */
    public const CACHE_KEY = 'cms_default_pages_urls';

    /**
     * Cache TTL in seconds (24 hours).
     */
    public const CACHE_TTL = 86400;

    public function __construct(
        private readonly PageService $pageService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage_default_pages'),
        ];
    }

    /**
     * Display the default pages settings form.
     */
    public function index(): Response
    {
        $pageOptions = $this->pageService->getPublishedPageOptions();

        // Get current settings
        $settings = [
            'home_page' => (string) setting('cms_default_pages_home_page', ''),
            'blogs_page' => (string) setting('cms_default_pages_blogs_page', ''),
            'blog_base_url' => (string) setting('cms_default_pages_blog_base_url', 'blog'),
            'contact_page' => (string) setting('cms_default_pages_contact_page', ''),
            'about_page' => (string) setting('cms_default_pages_about_page', ''),
            'privacy_policy_page' => (string) setting('cms_default_pages_privacy_policy_page', ''),
            'terms_of_service_page' => (string) setting('cms_default_pages_terms_of_service_page', ''),
            'blog_same_as_home' => (bool) setting('cms_default_pages_blog_same_as_home', false),
        ];

        return Inertia::render('cms/settings/default-pages', [
            'pageOptions' => $pageOptions,
            'settings' => $settings,
            'publishedPageCount' => count($pageOptions) > 0 ? max(count($pageOptions) - 1, 0) : 0,
        ]);
    }

    /**
     * Update default pages settings.
     */
    public function update(UpdateDefaultPagesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Handle blog_same_as_home checkbox - if checked, clear blogs_page
        $blogSameAsHome = $request->boolean('blog_same_as_home');

        // Sanitize blog base URL - default to 'blog' if empty
        $blogBaseUrl = trim($validated['blog_base_url'] ?? 'blog');
        $blogBaseUrl = $blogBaseUrl !== '' && $blogBaseUrl !== '0' ? $blogBaseUrl : 'blog';

        // Define settings to save
        $settingsToSave = [
            'home_page' => $validated['home_page'] ?? '',
            'blogs_page' => $blogSameAsHome ? '' : ($validated['blogs_page'] ?? ''),
            'blog_base_url' => $blogBaseUrl,
            'contact_page' => $validated['contact_page'] ?? '',
            'about_page' => $validated['about_page'] ?? '',
            'privacy_policy_page' => $validated['privacy_policy_page'] ?? '',
            'terms_of_service_page' => $validated['terms_of_service_page'] ?? '',
            'blog_same_as_home' => $blogSameAsHome,
        ];

        // Save each setting
        foreach ($settingsToSave as $key => $value) {
            $this->saveSetting($key, $value);
        }

        // Clear cached URLs
        static::clearCache();

        // Dispatch job to rebuild all caches asynchronously (non-blocking)
        dispatch(new RecacheApplication('Default pages settings update'));

        return to_route('cms.settings.default-pages')
            ->with('success', 'Default pages settings updated successfully.');
    }

    /**
     * Clear the default pages URL cache.
     *
     * Uses CmsDefaultPagesCacheService for two-tier cache invalidation.
     */
    public static function clearCache(): void
    {
        resolve(CmsDefaultPagesCacheService::class)->invalidate('DefaultPagesSettingController::clearCache');
    }

    /**
     * Save a single setting to the database.
     */
    protected function saveSetting(string $key, mixed $value): void
    {
        $type = is_bool($value) ? 'boolean' : 'string';
        $storeValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        Settings::query()->updateOrCreate([
            'group' => 'cms_default_pages',
            'key' => $key,
        ], [
            'value' => $storeValue,
            'type' => $type,
        ]);
    }
}
