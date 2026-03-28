<?php

namespace Modules\CMS\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\RecacheApplication;
use App\Models\Settings;
use App\Traits\ActivityTrait;
use App\Traits\HasMediaPicker;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Http\Controllers\Concerns\InteractsWithSeoSettingsAudit;
use Modules\CMS\Http\Controllers\Concerns\InteractsWithSeoSettingsPages;
use Modules\CMS\Http\Requests\UpdateSeoSettingsRequest;
use Modules\CMS\Services\SeoSettingService;
use Modules\CMS\Services\SitemapService;

/**
 * SeoSettingController - Handles SEO settings management
 *
 * This controller manages various SEO-related settings including:
 * - General SEO settings (site title, meta tags)
 * - Titles & Meta templates for different content types
 * - Local SEO settings
 * - Social Media settings
 * - Schema markup settings
 * - Sitemap configuration
 * - Robots.txt configuration
 */
class SeoSettingController extends Controller
{
    use ActivityTrait;
    use HasMediaPicker;
    use InteractsWithSeoSettingsAudit;
    use InteractsWithSeoSettingsPages;

    // =============================================================================
    // CONSTANTS
    // =============================================================================

    private const string MODULE_NAME = 'seo';

    private const string MODULE_PATH = 'seo::settings';

    protected string $activityLogModule = 'CMS';

    // =============================================================================
    // CONSTRUCTOR & DEPENDENCY INJECTION
    // =============================================================================

    public function __construct(
        private readonly Settings $settings,
        private readonly SeoSettingService $seoSettingService
    ) {}

    // =============================================================================
    // MIDDLEWARE CONFIGURATION
    // =============================================================================

    public static function middleware(): array
    {
        // Note: SEO settings use dynamic permissions based on master_group
        // Permissions are checked in the index() and update() methods
        return [];
    }

    // =============================================================================
    // INDEX ACTION
    // =============================================================================

    /**
     * Display SEO settings page
     */
    public function index(Request $request, string $masterGroup, string $fileName): Response
    {
        // Determine permission module (cms for titlesmeta)
        // Map route master_group to actual seeded permission names
        $permissionModule = match (true) {
            $masterGroup === 'titlesmeta' => 'cms',
            $masterGroup === 'settings' && $fileName === 'titlesmeta' => 'cms',
            $masterGroup === 'common' => 'seo',
            default => $masterGroup,
        };

        // 'common' group uses 'manage_seo_settings' (no middle segment)
        $permission = $permissionModule === 'seo'
            ? 'manage_seo_settings'
            : 'manage_'.$permissionModule.'_seo_settings';

        // Check permissions
        abort_unless(Auth::user()->can($permission), 403);

        // Check if module is active for CMS-related settings
        if (in_array($masterGroup, ['cms', 'titlesmeta', 'classified']) || ($masterGroup === 'settings' && $fileName === 'titlesmeta')) {
            $moduleToCheck = $masterGroup === 'titlesmeta' || ($masterGroup === 'settings' && $fileName === 'titlesmeta') ? 'cms' : $masterGroup;
            abort_unless(active_modules($moduleToCheck), 403);
        }

        $data = $this->getViewData('settings', $masterGroup, $fileName);

        if ($masterGroup === 'integrations' && in_array($fileName, [
            'webmaster_tools',
            'google_analytics',
            'google_tags',
            'meta_pixel',
            'microsoft_clarity',
            'google_adsense',
            'other',
        ], true)) {
            return Inertia::render('cms/integrations/index', $this->getIntegrationsPageData($data['settings_data'], $fileName));
        }

        // Add sitemap status data for sitemap settings page
        if ($fileName === 'sitemap') {
            $sitemapService = resolve(SitemapService::class);
            $data['sitemapStatus'] = $sitemapService->getStatus();
        }

        $inertiaPage = $this->resolveSeoInertiaPage($request, $data, $masterGroup, $fileName);
        if ($inertiaPage !== null) {
            return Inertia::render($inertiaPage['component'], $inertiaPage['props']);
        }

        abort(404);
    }

    // =============================================================================
    // UPDATE ACTION
    // =============================================================================

    /**
     * Update SEO settings with detailed change tracking
     */
    public function update(string $masterGroup, string $fileName, UpdateSeoSettingsRequest $request): RedirectResponse
    {
        abort_unless($this->seoSettingService->validateModuleAccess($masterGroup), 403);

        try {
            // Capture old values BEFORE update for audit trail
            $oldValues = $this->captureCurrentSettings($masterGroup, $fileName, $request);

            // Perform the update
            $this->seoSettingService->updateSettings($masterGroup, $fileName, $request);

            // Dispatch job to rebuild all caches asynchronously (non-blocking)
            dispatch(new RecacheApplication(sprintf('SEO settings update: %s/%s', $masterGroup, $fileName)));

            // Get new values from the request (what was just saved)
            // Exclude internal fields that shouldn't be tracked as changes
            // Media picker components auto-generate *_url fields from *_id fields
            $excludedFields = ['_token', '_method', 'section'];

            // Exclude auto-generated media URL fields (they're generated from ID fields)
            foreach ($request->all() as $key => $value) {
                if (str_ends_with((string) $key, '_url') && $request->has(str_replace('_url', '', $key))) {
                    $excludedFields[] = $key;
                }
            }

            $newValues = $request->except($excludedFields);

            // Add boolean fields with their actual values (false if not in request)
            $booleanFields = $this->getBooleanFieldsForFile($masterGroup, $fileName);
            foreach ($booleanFields as $field) {
                if (! isset($newValues[$field])) {
                    $newValues[$field] = $request->boolean($field);
                }
            }

            // Build change summary for user feedback
            $changeSummary = $this->buildChangeSummary($masterGroup, $fileName, $oldValues, $newValues, $booleanFields);

            // Log with detailed change information
            $this->logSettingsUpdateWithChanges($masterGroup, $fileName, $oldValues, $newValues);

            if ($masterGroup === 'integrations') {
                $routeName = match ($fileName) {
                    'webmaster_tools' => 'cms.integrations.webmastertools',
                    'google_analytics' => 'cms.integrations.googleanalytics',
                    'google_tags' => 'cms.integrations.googletags',
                    'meta_pixel' => 'cms.integrations.metapixel',
                    'microsoft_clarity' => 'cms.integrations.microsoftclarity',
                    'google_adsense' => 'cms.integrations.googleadsense',
                    'other' => 'cms.integrations.other',
                    default => 'cms.integrations.index',
                };

                // Check for integration validation warnings
                $integrationWarnings = $this->seoSettingService->getIntegrationWarnings();

                if ($integrationWarnings !== []) {
                    $warningMessages = array_map(fn (array $w): string => '<strong>'.$w['field'].':</strong> '.$w['message'], $integrationWarnings);

                    return to_route($routeName)
                        ->with('success', $changeSummary)
                        ->with('error', 'Some invalid HTML was removed:<br>'.implode('<br>', $warningMessages));
                }

                return to_route($routeName)->with('success', $changeSummary);
            }

            // Preserve the section parameter when redirecting
            $redirectUrl = url()->previous();
            $section = $request->input('section', $fileName);

            // Parse the URL and preserve/add the section parameter
            $parsedUrl = parse_url($redirectUrl);
            $queryParams = [];
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
            }

            $queryParams['section'] = $section;

            $newQuery = http_build_query($queryParams);
            $finalUrl = $parsedUrl['path'].($newQuery !== '' && $newQuery !== '0' ? '?'.$newQuery : '');

            // Check for integration validation warnings
            $integrationWarnings = $this->seoSettingService->getIntegrationWarnings();

            // Add error if any invalid HTML was found (use 'error' instead of 'warning' for visibility)
            if ($integrationWarnings !== []) {
                $warningMessages = array_map(fn (array $w): string => '<strong>'.$w['field'].':</strong> '.$w['message'], $integrationWarnings);

                return redirect($finalUrl)
                    ->with('success', $changeSummary)
                    ->with('error', 'Some invalid HTML was removed:<br>'.implode('<br>', $warningMessages));
            }

            return redirect($finalUrl)->with('success', $changeSummary);
        } catch (Exception) {
            return back()
                ->with('error', 'Failed to update settings. Please try again.')
                ->withInput();
        }
    }

    // =============================================================================
    // SITEMAP REGENERATION
    // =============================================================================

    /**
     * Regenerate sitemap
     */
    public function regenerateSitemap(): RedirectResponse
    {
        $sitemapService = resolve(SitemapService::class);
        $results = $sitemapService->generateAll();

        $settingsModel = new Settings;
        $settingsModel->id = 0;

        $this->logActivity(
            $settingsModel,
            ActivityAction::UPDATE,
            'SEO Settings: Sitemap regenerated',
            [
                'module_name' => self::MODULE_NAME,
                'master_group' => 'common',
                'action_type' => 'sitemap_regeneration',
                'results' => $results,
            ]
        );

        // Calculate total URLs generated
        $totalUrls = array_sum(array_map(fn (array $r) => $r['count'] ?? 0, $results));

        return to_route('seo.settings.sitemap')
            ->with('success', sprintf('Sitemap regenerated successfully. %s URLs generated.', $totalUrls));
    }
}
