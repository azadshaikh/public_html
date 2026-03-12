<?php

namespace App\Http\Controllers\Masters;

use App\Enums\ActivityAction;
use App\Helpers\NavigationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettingsRequest;
use App\Jobs\RecacheApplication;
use App\Models\Settings;
use App\Services\SettingsCacheService;
use App\Traits\ActivityTrait;
use App\Traits\HasAlerts;
use App\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class SettingsController extends Controller
{
    use ActivityTrait;
    use HasAlerts;
    use ResponseTrait;

    public function __construct(private readonly Settings $settings, private readonly SettingsCacheService $settingsCacheService) {}

    /**
     * Display the master settings page
     */
    public function settings(): View
    {
        $this->authorizeSettingsAccess();

        $view_data = $this->getViewData();
        $view_data['settings'] = $this->getCachedSettings();

        return view('app.masters.settings.settings', $view_data);
    }

    /**
     * Update settings for a specific meta group
     */
    public function update(string $meta_group, SettingsRequest $request): RedirectResponse
    {
        $this->authorizeSettingsAccess();

        $section = $request->input('section');
        $redirectUrl = $this->getRedirectUrl('app.masters.settings.index', $section);

        try {
            // Store old values for activity logging
            $oldValues = $this->getCurrentSettingsState($meta_group);

            // Get full validated data for change tracking (before exclusions)
            $fullData = $this->getFullValidatedData($request, $meta_group);

            // Get cleaned data for handler (with exclusions)
            $data = $this->prepareValidatedData($request);
            $handler = $this->getSettingHandler($meta_group);

            // Prevent SettingsObserver from dispatching a competing RecacheApplication
            // job while we handle recache synchronously below.
            request()->attributes->set('astero.recache_dispatched', true);

            $result = $handler($data, $request);
            $redirectUrl = $this->resolvePostUpdateRedirectUrl(
                metaGroup: $meta_group,
                section: $section,
                defaultUrl: $redirectUrl,
                handlerResult: $result
            );

            if ($result['success']) {
                $this->clearSettingsCache();
                $this->clearSidebarNavigationCache((bool) ($result['admin_slug_changed'] ?? false));

                // Normalize boolean fields for proper comparison
                $normalizedData = $this->normalizeBooleanFields($meta_group, $fullData);

                // Build change summary for user feedback
                $changeSummary = $this->buildChangeSummary($meta_group, $oldValues, $normalizedData);

                // Log activity with old and new values
                $this->logSettingsActivity($meta_group, $oldValues, $normalizedData);

                // Rebuild application caches so env-driven settings apply immediately.
                $this->dispatchSettingsRecache(
                    $meta_group,
                    (bool) ($result['recache_sync'] ?? false)
                );

                // Return success with detailed change summary
                return redirect($redirectUrl)
                    ->with('success', $changeSummary);
            }

            return $this->redirectWithError(
                title: 'Settings Update Failed',
                message: 'Unable to update settings. Please try again.',
                redirectTo: $redirectUrl
            );
        } catch (Exception $exception) {
            return $this->redirectWithError(
                title: 'Error Updating Settings',
                message: 'An unexpected error occurred: '.$exception->getMessage(),
                redirectTo: $redirectUrl
            );
        }
    }

    /**
     * Test storage connection with provided credentials
     */
    public function testStorageConnection(Request $request): JsonResponse
    {
        $this->authorizeSettingsAccess();

        $driver = $request->input('storage_driver');

        if (! in_array($driver, ['ftp', 's3'])) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test is only available for FTP and S3 drivers.',
            ]);
        }

        try {
            // Temporarily configure the disk with provided credentials
            if ($driver === 'ftp') {
                config([
                    'filesystems.disks.ftp_test' => [
                        'driver' => 'ftp',
                        'host' => $request->input('ftp_host', ''),
                        'username' => $request->input('ftp_username', ''),
                        'password' => $request->input('ftp_password', ''),
                        'root' => $request->input('ftp_root', ''),
                        'port' => (int) $request->input('ftp_port', 21),
                        'passive' => filter_var($request->input('ftp_passive', true), FILTER_VALIDATE_BOOLEAN),
                        'timeout' => (int) $request->input('ftp_timeout', 30),
                        'ssl' => filter_var($request->input('ftp_ssl', true), FILTER_VALIDATE_BOOLEAN),
                        'ssl_mode' => $request->input('ftp_ssl_mode', 'explicit'),
                    ],
                ]);
                $testDisk = 'ftp_test';
            } else {
                config([
                    'filesystems.disks.s3_test' => [
                        'driver' => 's3',
                        'key' => $request->input('access_key', ''),
                        'secret' => $request->input('secret_key', ''),
                        'region' => $request->input('region', ''),
                        'bucket' => $request->input('bucket', ''),
                        'endpoint' => $request->input('endpoint', ''),
                        'use_path_style_endpoint' => filter_var($request->input('use_path_style_endpoint', false), FILTER_VALIDATE_BOOLEAN),
                    ],
                ]);
                $testDisk = 's3_test';
            }

            $disk = Storage::disk($testDisk);
            $testFile = '.astero_connection_test_'.time().'.txt';
            $testContent = 'Astero storage connection test - '.now()->toIso8601String();

            // Write test file
            $disk->put($testFile, $testContent);

            // Read it back
            $readContent = $disk->get($testFile);

            // Verify content matches
            if ($readContent !== $testContent) {
                $disk->delete($testFile);

                return response()->json([
                    'success' => false,
                    'message' => 'Connection established but file content verification failed.',
                ]);
            }

            // Clean up
            $disk->delete($testFile);

            return response()->json([
                'success' => true,
                'message' => 'Connection successful! Files can be read and written.',
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: '.$exception->getMessage(),
            ]);
        }
    }

    /**
     * Get view data for settings page
     */
    private function getViewData(): array
    {
        return [
            'module_title' => __('settings.settings'),
            'module_name' => __('settings.settings'),
            'module_path' => 'app.masters.settings',
            'parent_module' => __('settings.system_settings'),
            'action' => 'settings',
            'page_title' => __('settings.master_settings'),
            'date_formats' => $this->getFormattedOptions('constants.date_formats'),
            'time_formats' => $this->getFormattedOptions('constants.time_formats'),
            'timezones' => $this->getTimezoneOptions(),
            'email_drivers' => config('constants.email_drivers'),
            'security_types' => [
                'tls' => ['label' => 'TLS', 'value' => 'tls'],
                'ssl' => ['label' => 'SSL', 'value' => 'ssl'],
            ],
            'storage_drivers' => config('constants.storage_drivers'),
            'languages' => config('constants.languages'),
            'theme_modes' => config('constants.theme_modes'),
        ];
    }

    /**
     * Authorize access to settings
     */
    private function authorizeSettingsAccess(): void
    {
        abort_unless(Auth::user()->can('manage_system_settings'), 401);
    }

    /**
     * Get cached settings with proper cache management
     */
    private function getCachedSettings(): array
    {
        // Note: This returns raw values for the settings page display.
        // The setting() helper uses SettingsCacheService for cast values.
        return $this->settings->pluck('value', 'key')->toArray();
    }

    /**
     * Clear settings cache - uses centralized service for robust invalidation
     */
    private function clearSettingsCache(): void
    {
        $this->settingsCacheService->invalidate('Settings page update');
    }

    /**
     * Clear sidebar navigation cache when route-affecting settings change.
     */
    private function clearSidebarNavigationCache(bool $adminSlugChanged): void
    {
        if (! $adminSlugChanged) {
            return;
        }

        try {
            NavigationHelper::clearAllCache();
        } catch (Throwable $throwable) {
            Log::warning('Failed to clear sidebar navigation cache after admin slug update', [
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch recache job.
     */
    private function dispatchSettingsRecache(string $metaGroup, bool $forceSync = false): void
    {
        $reason = 'Master settings update: '.$metaGroup;

        if ($forceSync) {
            $this->runSettingsRecacheImmediately($reason);

            return;
        }

        dispatch(new RecacheApplication($reason));
    }

    /**
     * Rebuild caches by spawning a fresh CLI subprocess.
     *
     * Running as a subprocess avoids FPM OPcache interference: `route:cache`
     * creates a "fresh application" which `require`s the config cache file,
     * but OPcache in the FPM worker may serve the stale compiled bytecode of
     * that file, causing routes to be registered under the OLD admin slug.
     * A separate CLI process (where OPcache is disabled) eliminates this.
     */
    private function runSettingsRecacheImmediately(string $reason): void
    {
        try {
            $phpBinary = $this->resolvePhpBinary();
            $process = new Process(
                [$phpBinary, base_path('artisan'), 'astero:recache'],
                base_path(),
                null,
                null,
                120
            );

            $exitCode = $process->run();

            if ($exitCode !== 0 || ! $process->isSuccessful()) {
                throw new RuntimeException(
                    'astero:recache subprocess failed (exit '.$exitCode.'): '.$process->getErrorOutput()
                );
            }

            // Invalidate FPM OPcache entries for the rebuilt cache files so the
            // current and other FPM workers pick up the new versions immediately.
            $this->invalidateOpcacheForCacheFiles();
        } catch (Throwable $throwable) {
            Log::warning('Immediate recache subprocess failed, falling back to queued recache job', [
                'reason' => $reason,
                'error' => $throwable->getMessage(),
            ]);

            dispatch(new RecacheApplication($reason));
        }
    }

    /**
     * Resolve the CLI PHP binary path (never php-fpm).
     */
    private function resolvePhpBinary(): string
    {
        $finder = new PhpExecutableFinder;
        $binary = $finder->find(false);

        if (is_string($binary) && $binary !== '') {
            return $binary;
        }

        $bindirBinary = PHP_BINDIR.DIRECTORY_SEPARATOR.'php';
        if (is_executable($bindirBinary)) {
            return $bindirBinary;
        }

        return 'php';
    }

    /**
     * Invalidate OPcache entries for config and route cache files.
     */
    private function invalidateOpcacheForCacheFiles(): void
    {
        if (! function_exists('opcache_invalidate')) {
            return;
        }

        $files = [
            app()->getCachedConfigPath(),
            app()->getCachedRoutesPath(),
            app()->getCachedEventsPath(),
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                opcache_invalidate($file, true);
            }
        }
    }

    /**
     * Resolve redirect URL after update.
     */
    private function resolvePostUpdateRedirectUrl(
        string $metaGroup,
        ?string $section,
        string $defaultUrl,
        array $handlerResult
    ): string {
        if ($metaGroup !== 'login_security') {
            return $defaultUrl;
        }

        $adminSlug = trim((string) ($handlerResult['admin_slug'] ?? ''), '/');
        if ($adminSlug === '') {
            return $defaultUrl;
        }

        $url = url('/'.$adminSlug.'/masters/settings');
        if ($section) {
            $url .= '?section='.$section;
        }

        return $url;
    }

    /**
     * Get full validated data including all fields for change tracking
     */
    private function getFullValidatedData(SettingsRequest $request, string $metaGroup): array
    {
        $data = $request->validated();

        // For branding and media, map URL fields to their database field names
        if ($metaGroup === 'branding') {
            $urlMappings = [
                'logo_url' => 'logo',
                'icon_url' => 'icon',
            ];

            foreach ($urlMappings as $urlField => $targetField) {
                if ($request->has($urlField)) {
                    $data[$targetField] = $request->input($urlField);
                }
            }
        }

        // Exclude only system fields
        $systemFields = ['_token', '_method', 'meta_group', 'section'];
        $imageIdFields = ['logo_id', 'icon_id'];
        $excluded = array_merge($systemFields, $imageIdFields);

        return array_diff_key($data, array_flip($excluded));
    }

    /**
     * Prepare validated data by excluding system fields
     */
    private function prepareValidatedData(SettingsRequest $request): array
    {
        $data = $request->validated();

        $excluded_fields = [
            '_token', '_method', 'meta_group', 'section',
            // Branding fields
            'logo_id', 'icon_id',
            'logo_url', 'icon_url',
        ];

        return array_diff_key($data, array_flip($excluded_fields));
    }

    /**
     * Get the appropriate handler for the meta group
     */
    private function getSettingHandler(string $meta_group): callable
    {
        return match ($meta_group) {
            'storage' => $this->handleStorageSettings(...),
            'debug' => $this->handleDebugSettings(...),
            'media' => $this->handleMediaSettings(...),
            'branding' => $this->handleBrandingSettings(...),
            'login_security' => $this->handleLoginSecuritySettings(...),
            'app' => $this->handleAppSettings(...),
            default => $this->handleGenericSettings(...),
        };
    }

    /**
     * Handle storage settings
     */
    private function handleStorageSettings(array $data): array
    {
        $this->updateStorageSettings($data);

        return [
            'success' => true,
            'message' => 'Storage settings updated successfully.',
            'recache_sync' => true,
        ];
    }

    /**
     * Handle debug settings
     */
    private function handleDebugSettings(array $data): array
    {
        $this->updateDebugSettings($data);

        return [
            'success' => true,
            'message' => 'Debug settings updated successfully.',
            'recache_sync' => true,
        ];
    }

    /**
     * Handle app settings (writes HOMEPAGE_REDIRECT_* to .env)
     */
    private function handleAppSettings(array $data): array
    {
        $isCmsModuleEnabled = function_exists('module_enabled') && module_enabled('CMS');

        if ($isCmsModuleEnabled) {
            set_env_values_bulk([
                'HOMEPAGE_REDIRECT_ENABLED' => 'false',
            ], false);

            return [
                'success' => true,
                'message' => 'App settings updated successfully. Homepage redirect is disabled while CMS module is active.',
                'recache_sync' => true,
            ];
        }

        $enabled = filter_var($data['homepage_redirect_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        $slug = ltrim((string) ($data['homepage_redirect_slug'] ?? ''), '/');

        set_env_values_bulk([
            'HOMEPAGE_REDIRECT_ENABLED' => $enabled,
            'HOMEPAGE_REDIRECT_SLUG' => $slug,
        ], false);

        return [
            'success' => true,
            'message' => 'App settings updated successfully.',
            'recache_sync' => true,
        ];
    }

    /**
     * Handle media settings
     */
    private function handleMediaSettings(array $data): array
    {
        $data = $this->updateMediaSettings($data);
        $this->updateMediaEnvironmentSettings($data);

        return [
            'success' => true,
            'message' => 'Media settings updated successfully.',
            'recache_sync' => true,
        ];
    }

    /**
     * Handle branding settings
     */
    private function handleBrandingSettings(array $data, SettingsRequest $request): array
    {
        $data = $this->updateBrandingSettings($data, $request);
        $this->updateBrandingEnvironmentSettings($data);

        return [
            'success' => true,
            'message' => 'Branding settings updated successfully.',
            'recache_sync' => true,
        ];
    }

    /**
     * Handle login security settings
     */
    private function handleLoginSecuritySettings(array $data): array
    {
        $adminSlug = trim((string) ($data['admin_login_url_slug'] ?? ''), '/');
        $data['admin_login_url_slug'] = $adminSlug;

        // Prepare payload - handle checkbox toggle
        if (! isset($data['enable_login_attempt_limiting'])) {
            $data['limit_login_attempts_enabled'] = 'false';
        } else {
            $data['limit_login_attempts_enabled'] = 'true';
            unset($data['enable_login_attempt_limiting']);
        }

        // Update ADMIN_SLUG in .env if changed
        $currentAdminSlug = trim((string) get_env_file_value('ADMIN_SLUG', config('app.admin_slug')), '/');
        $adminSlugChanged = $adminSlug !== $currentAdminSlug;
        if ($adminSlugChanged) {
            set_env_value('ADMIN_SLUG', $data['admin_login_url_slug'], false);
            config(['app.admin_slug' => $adminSlug]);
        }

        // Store settings in database
        $updated = $this->updateGenericSettings($data, 'login_security');

        return [
            'success' => $updated,
            'message' => 'Login Security settings updated successfully.',
            'recache_sync' => true,
            'admin_slug' => $adminSlug,
            'admin_slug_changed' => $adminSlugChanged,
        ];
    }

    /**
     * Handle generic settings (database stored)
     */
    private function handleGenericSettings(array $data, SettingsRequest $request): array
    {
        $updated = $this->updateGenericSettings($data, $request->input('meta_group'));

        return [
            'success' => $updated,
            'message' => ucwords(str_replace(['_', '-'], ' ', $request->input('meta_group'))).' settings updated successfully.',
        ];
    }

    /**
     * Update storage settings (environment variables)
     */
    private function updateStorageSettings(array $data): void
    {
        $envValues = [];

        // Allow clearing values by checking array_key_exists instead of isset
        if (array_key_exists('storage_driver', $data)) {
            $envValues['STORAGE_DISK'] = $data['storage_driver'] ?? '';
        }

        if (array_key_exists('root_folder', $data)) {
            $envValues['STORAGE_ROOT_FOLDER'] = $data['root_folder'] ?? '';
        }

        if (array_key_exists('max_storage_size', $data)) {
            $envValues['MAX_STORAGE_SIZE'] = $data['max_storage_size'] ?? '';
        }

        if (array_key_exists('storage_cdn_url', $data)) {
            $envValues['STORAGE_CDN_URL'] = $data['storage_cdn_url'] ?? '';
        }

        // Handle FTP settings - allow clearing all FTP fields
        if (array_key_exists('storage_driver', $data) && $data['storage_driver'] === 'ftp') {
            $envValues['FTP_HOST'] = $data['ftp_host'] ?? '';
            $envValues['FTP_USERNAME'] = $data['ftp_username'] ?? '';
            $envValues['FTP_PASSWORD'] = $data['ftp_password'] ?? '';
            $envValues['FTP_ROOT'] = $data['ftp_root'] ?? '';
            $envValues['FTP_PORT'] = $data['ftp_port'] ?? '21';
            $envValues['FTP_PASSIVE'] = isset($data['ftp_passive']) ? 'true' : 'false';
            $envValues['FTP_TIMEOUT'] = $data['ftp_timeout'] ?? '30';
            $envValues['FTP_SSL'] = isset($data['ftp_ssl']) ? 'true' : 'false';
            $envValues['FTP_SSL_MODE'] = $data['ftp_ssl_mode'] ?? 'explicit';
        }

        // Handle S3 settings - allow clearing all S3 fields
        if (array_key_exists('storage_driver', $data) && $data['storage_driver'] === 's3') {
            $envValues['AWS_ACCESS_KEY_ID'] = $data['access_key'] ?? '';
            $envValues['AWS_SECRET_ACCESS_KEY'] = $data['secret_key'] ?? '';
            $envValues['AWS_BUCKET'] = $data['bucket'] ?? '';
            $envValues['AWS_DEFAULT_REGION'] = $data['region'] ?? '';
            $envValues['AWS_ENDPOINT'] = $data['endpoint'] ?? '';
            $envValues['AWS_USE_PATH_STYLE_ENDPOINT'] = isset($data['use_path_style_endpoint']) ? 'TRUE' : 'FALSE';
        }

        // Bulk update all env values at once
        set_env_values_bulk($envValues, false);
    }

    /**
     * Update debug settings (environment variables)
     */
    private function updateDebugSettings(array $data): void
    {
        $debugMappings = [
            'enable_debugging' => 'APP_DEBUG',
            'enable_debugging_bar' => 'DEBUGBAR_ENABLED',
            'enable_html_minification' => 'HTML_MINIFICATION_ENABLED',
        ];

        $envValues = [];
        foreach ($debugMappings as $field => $envKey) {
            $value = filter_var($data[$field] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            $envValues[$envKey] = $value;
        }

        // Bulk update all env values at once
        set_env_values_bulk($envValues, false);
    }

    /**
     * Update media settings (database + file handling)
     */
    private function updateMediaSettings(array $data): array
    {
        // Set defaults for boolean fields
        $defaults = ['image_optimization' => 'false', 'delete_trashed' => 'false'];

        return array_merge($defaults, $data);
    }

    /**
     * Update media settings (environment variables)
     */
    private function updateMediaEnvironmentSettings(array $data): void
    {
        $mediaMappings = [
            // File management settings
            'max_file_name_length' => 'MEDIA_MAX_FILE_NAME_LENGTH',
            'max_upload_size' => 'MEDIA_MAX_SIZE_IN_MB',
            'allowed_file_types' => 'MEDIA_ALLOWED_FILE_TYPES',

            // Image processing settings
            'image_optimization' => 'MEDIA_IMAGE_OPTIMIZATION',
            'image_quality' => 'MEDIA_IMAGE_QUALITY',

            // Image sizes
            'thumbnail_width' => 'MEDIA_THUMBNAIL_WIDTH',
            'small_width' => 'MEDIA_SMALL_WIDTH',
            'medium_width' => 'MEDIA_MEDIUM_WIDTH',
            'large_width' => 'MEDIA_LARGE_WIDTH',
            'xlarge_width' => 'MEDIA_XLARGE_WIDTH',

            // Auto delete settings
            'delete_trashed' => 'MEDIA_AUTO_DELETE_TRASHED',
            'delete_trashed_days' => 'MEDIA_TRASH_AUTO_DELETE_DAYS',
        ];

        $envValues = [];
        $booleanFields = ['image_optimization', 'delete_trashed'];
        foreach ($mediaMappings as $field => $envKey) {
            // Use array_key_exists to allow clearing values (empty string)
            if (array_key_exists($field, $data)) {
                $value = $data[$field] ?? '';
                // Convert boolean fields to true/false strings
                if (in_array($field, $booleanFields)) {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                }

                $envValues[$envKey] = $value;
            }
        }

        // Bulk update all env values at once
        if ($envValues !== []) {
            set_env_values_bulk($envValues, false);
        }
    }

    /**
     * Update branding settings (database + file handling)
     */
    private function updateBrandingSettings(array $data, SettingsRequest $request): array
    {
        // Handle file uploads - get full URLs instead of paths
        $fileMappings = [
            'logo_url' => 'logo',
            'icon_url' => 'icon',
        ];

        foreach ($fileMappings as $urlField => $dataField) {
            if ($request->has($urlField)) {
                $data[$dataField] = $request->input($urlField);
            }
        }

        return $data;
    }

    /**
     * Update branding settings (environment variables)
     */
    private function updateBrandingEnvironmentSettings(array $data): void
    {
        $brandingMappings = [
            // Brand Identity
            'brand_name' => 'BRANDING_NAME',
            'brand_website' => 'BRANDING_WEBSITE',
            'logo' => 'BRANDING_LOGO',
            'icon' => 'BRANDING_ICON',
        ];

        $envValues = [];
        foreach ($brandingMappings as $field => $envKey) {
            // Use array_key_exists to allow clearing values (empty string)
            if (array_key_exists($field, $data)) {
                $envValues[$envKey] = $data[$field] ?? '';
            }
        }

        // Bulk update all env values at once
        if ($envValues !== []) {
            set_env_values_bulk($envValues, false);
        }
    }

    /**
     * Update generic settings in database
     */
    private function updateGenericSettings(array $data, string $metaGroup): bool
    {
        if ($data === []) {
            return false;
        }

        $updated = false;
        $userId = Auth::id();

        foreach ($data as $key => $value) {
            if (! empty($value) || $value === '0') {
                $settingKey = $metaGroup.'_'.$key;

                $setting = $this->settings->where('key', $settingKey)->first();

                $settingData = [
                    'key' => $settingKey,
                    'value' => $value,
                    'updated_by' => $userId,
                ];

                if ($setting) {
                    $setting->update($settingData);
                } else {
                    $settingData['created_by'] = $userId;
                    $this->settings->create($settingData);
                }

                $updated = true;
            }
        }

        return $updated;
    }

    /**
     * Get formatted options from config
     */
    private function getFormattedOptions(string $configKey): array
    {
        $options = [];
        foreach (config($configKey) as $key => $value) {
            $options[] = ['value' => $key, 'label' => $value];
        }

        return $options;
    }

    /**
     * Get timezone options
     */
    private function getTimezoneOptions(): array
    {
        $timezones = [];
        foreach (config('timezones') as $tzdata) {
            $timezones[] = ['value' => $tzdata, 'label' => $tzdata];
        }

        return $timezones;
    }

    /**
     * Get current settings state for activity logging
     */
    private function getCurrentSettingsState(string $metaGroup): array
    {
        $currentState = [];

        // Most master settings are stored in environment variables
        switch ($metaGroup) {
            case 'debug':
                $currentState = [
                    'enable_debugging' => get_env_value('APP_DEBUG', 'false'),
                    'enable_debugging_bar' => get_env_value('DEBUGBAR_ENABLED', 'false'),
                ];
                break;

            case 'storage':
                $currentState = [
                    'storage_driver' => get_env_value('STORAGE_DISK', 'public'),
                    'root_folder' => get_env_value('STORAGE_ROOT_FOLDER', ''),
                    'max_storage_size' => get_env_value('MAX_STORAGE_SIZE', ''),
                    'storage_cdn_url' => get_env_value('STORAGE_CDN_URL', ''),
                    // FTP settings
                    'ftp_host' => get_env_value('FTP_HOST', ''),
                    'ftp_username' => get_env_value('FTP_USERNAME', ''),
                    'ftp_password' => get_env_value('FTP_PASSWORD', ''),
                    'ftp_root' => get_env_value('FTP_ROOT', ''),
                    'ftp_port' => get_env_value('FTP_PORT', '21'),
                    'ftp_passive' => get_env_value('FTP_PASSIVE', 'false'),
                    'ftp_timeout' => get_env_value('FTP_TIMEOUT', '30'),
                    'ftp_ssl' => get_env_value('FTP_SSL', 'false'),
                    'ftp_ssl_mode' => get_env_value('FTP_SSL_MODE', 'explicit'),
                    // S3 settings
                    'access_key' => get_env_value('AWS_ACCESS_KEY_ID', ''),
                    'secret_key' => get_env_value('AWS_SECRET_ACCESS_KEY', ''),
                    'bucket' => get_env_value('AWS_BUCKET', ''),
                    'region' => get_env_value('AWS_DEFAULT_REGION', ''),
                    'endpoint' => get_env_value('AWS_ENDPOINT', ''),
                    'use_path_style_endpoint' => get_env_value('AWS_USE_PATH_STYLE_ENDPOINT', 'FALSE'),
                ];
                break;

            case 'media':
                $currentState = [
                    'max_file_name_length' => get_env_value('MEDIA_MAX_FILE_NAME_LENGTH', ''),
                    'max_upload_size' => get_env_value('MEDIA_MAX_SIZE_IN_MB', ''),
                    'allowed_file_types' => trim((string) get_env_value('MEDIA_ALLOWED_FILE_TYPES', ''), '"'),
                    'image_optimization' => get_env_value('MEDIA_IMAGE_OPTIMIZATION', 'false'),
                    'image_quality' => get_env_value('MEDIA_IMAGE_QUALITY', ''),
                    'thumbnail_width' => get_env_value('MEDIA_THUMBNAIL_WIDTH', '150'),
                    'small_width' => get_env_value('MEDIA_SMALL_WIDTH', ''),
                    'medium_width' => get_env_value('MEDIA_MEDIUM_WIDTH', ''),
                    'large_width' => get_env_value('MEDIA_LARGE_WIDTH', ''),
                    'xlarge_width' => get_env_value('MEDIA_XLARGE_WIDTH', '1920'),
                    'delete_trashed' => get_env_value('MEDIA_AUTO_DELETE_TRASHED', 'false'),
                    'delete_trashed_days' => get_env_value('MEDIA_TRASH_AUTO_DELETE_DAYS', ''),
                ];
                break;

            case 'branding':
                $currentState = [
                    'brand_name' => get_env_value('BRANDING_NAME', ''),
                    'brand_website' => get_env_value('BRANDING_WEBSITE', ''),
                    'logo' => get_env_value('BRANDING_LOGO', ''),
                    'icon' => get_env_value('BRANDING_ICON', ''),
                ];
                break;

            case 'login_security':
                // Login security settings are stored in the database with prefix login_security_
                $settings = $this->settings->pluck('value', 'key')->toArray();
                $currentState = [
                    'admin_login_url_slug' => get_env_file_value('ADMIN_SLUG', config('app.admin_slug')),
                    'limit_login_attempts_enabled' => $settings['login_security_limit_login_attempts_enabled'] ?? 'false',
                    'limit_login_attempts' => $settings['login_security_limit_login_attempts'] ?? '5',
                    'lockout_time' => $settings['login_security_lockout_time'] ?? '60',
                ];
                break;
        }

        return $currentState;
    }

    /**
     * Log settings activity with old and new values
     */
    private function logSettingsActivity(string $metaGroup, array $oldValues, array $newValues): void
    {
        $settingDisplayName = $this->getFriendlyMetaGroupName($metaGroup);
        $booleanFields = $this->getBooleanFieldsForMetaGroup($metaGroup);
        $fieldLabels = $this->getFieldLabelsForMetaGroup();

        $changedFieldNames = [];
        $changedFields = [];

        // Find changed fields
        foreach ($newValues as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            $isBooleanField = in_array($field, $booleanFields);

            // Normalize values for comparison
            $normalizedOld = $this->normalizeValue($oldValue, $isBooleanField);
            $normalizedNew = $this->normalizeValue($newValue, $isBooleanField);

            if ($normalizedOld !== $normalizedNew) {
                $fieldLabel = $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));
                $changedFieldNames[] = $fieldLabel;

                // For boolean fields, store as actual boolean type (true/false), not string
                if ($isBooleanField) {
                    $changedFields[$field] = [
                        'old' => $this->formatBooleanForDisplay($oldValue),
                        'new' => $this->formatBooleanForDisplay($newValue),
                    ];
                } else {
                    $changedFields[$field] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        }

        // Build descriptive log message
        $changeCount = count($changedFieldNames);
        if ($changeCount > 0) {
            $fieldList = $changeCount <= 3
                ? implode(', ', $changedFieldNames)
                : implode(', ', array_slice($changedFieldNames, 0, 3)).' and '.($changeCount - 3).' more';

            $descriptiveMessage = sprintf('Settings Updated: %s (%d field', $settingDisplayName, $changeCount).($changeCount > 1 ? 's' : '').sprintf(' changed: %s)', $fieldList);
        } else {
            $descriptiveMessage = sprintf('Settings Saved: %s (no changes detected)', $settingDisplayName);
        }

        // Create a temporary settings model for logging
        $tempModel = new Settings(['id' => 0]);

        $this->logActivityWithPreviousValues(
            $tempModel,
            ActivityAction::UPDATE,
            $descriptiveMessage,
            $oldValues,
            [
                'module' => 'Master Settings',
                'meta_group' => $metaGroup,
                'setting_display_name' => $settingDisplayName,
                'changed_fields' => $changedFields,
                'changed_field_names' => $changedFieldNames,
                'change_count' => $changeCount,
                'new_values' => $newValues,
            ]
        );
    }

    /**
     * Get redirect URL with section parameter
     */
    private function getRedirectUrl(string $routeName, ?string $section): string
    {
        $url = route($routeName);

        if ($section) {
            $url .= '?section='.$section;
        }

        return $url;
    }

    /**
     * Normalize boolean fields (unchecked checkboxes don't submit, so we need to add them as false)
     */
    private function normalizeBooleanFields(string $metaGroup, array $data): array
    {
        $booleanFields = $this->getBooleanFieldsForMetaGroup($metaGroup);

        foreach ($booleanFields as $field) {
            // If the boolean field is not in the data, it means the checkbox was unchecked
            // We need to explicitly set it to false/0 for proper change detection
            if (! isset($data[$field])) {
                $data[$field] = '0';
            }
        }

        return $data;
    }

    /**
     * Build change summary for user feedback
     */
    private function buildChangeSummary(string $metaGroup, array $oldValues, array $newValues): array
    {
        $settingName = $this->getFriendlyMetaGroupName($metaGroup);
        $booleanFields = $this->getBooleanFieldsForMetaGroup($metaGroup);
        $sensitiveFields = ['access_key', 'secret_key', 'api_key', 'ftp_password'];
        $fileFields = ['logo', 'icon',
            'default_avatar_image', 'default_placeholder_image', 'watermark_image',
        ];

        $changedFields = [];
        $changes = [];

        $fieldLabels = $this->getFieldLabelsForMetaGroup();

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            $isBooleanField = in_array($key, $booleanFields);
            $isSensitiveField = in_array($key, $sensitiveFields);
            $isFileField = in_array($key, $fileFields);

            // Normalize values for comparison
            $normalizedOld = $this->normalizeValue($oldValue, $isBooleanField);
            $normalizedNew = $this->normalizeValue($newValue, $isBooleanField);

            if ($normalizedOld !== $normalizedNew) {
                $fieldLabel = $fieldLabels[$key] ?? ucwords(str_replace('_', ' ', $key));

                // Format display values - for booleans, use actual boolean type
                if ($isBooleanField) {
                    $displayOldValue = $this->formatBooleanForDisplay($oldValue);
                    $displayNewValue = $this->formatBooleanForDisplay($newValue);
                } else {
                    $displayOldValue = $oldValue;
                    $displayNewValue = $newValue;
                }

                // For boolean fields, show enabled/disabled
                if ($isBooleanField) {
                    $oldLabel = $displayOldValue ? 'enabled' : 'disabled';
                    $newLabel = $displayNewValue ? 'enabled' : 'disabled';
                    $changedFields[] = sprintf('%s: %s → %s', $fieldLabel, $oldLabel, $newLabel);
                } elseif ($isSensitiveField) {
                    // For sensitive fields, don't show actual values
                    $changedFields[] = $fieldLabel.' updated';
                } elseif ($isFileField) {
                    // For file/image fields, show simplified message
                    $changedFields[] = $fieldLabel.' updated';
                } else {
                    // For regular fields
                    $oldDisplay = $oldValue ? (strlen((string) $oldValue) > 30 ? substr((string) $oldValue, 0, 30).'...' : $oldValue) : '(empty)';
                    $newDisplay = $newValue ? (strlen((string) $newValue) > 30 ? substr((string) $newValue, 0, 30).'...' : $newValue) : '(empty)';
                    $changedFields[] = sprintf('%s: "%s" → "%s"', $fieldLabel, $oldDisplay, $newDisplay);
                }

                $changes[$key] = [
                    'field' => $fieldLabel,
                    'old' => $displayOldValue,
                    'new' => $displayNewValue,
                ];
            }
        }

        $changeCount = count($changedFields);

        if ($changeCount > 0) {
            // Build HTML list of changes
            $changesHtml = '<ul class="mb-0 ps-3">';
            $displayLimit = 5;
            $displayChanges = array_slice($changedFields, 0, $displayLimit);

            foreach ($displayChanges as $change) {
                $changesHtml .= '<li class="mb-1">'.$change.'</li>';
            }

            if ($changeCount > $displayLimit) {
                $changesHtml .= '<li class="mb-1"><em>...and '.($changeCount - $displayLimit).' more</em></li>';
            }

            $changesHtml .= '</ul>';

            return [
                'title' => 'Success!',
                'message' => $settingName.' settings updated successfully.',
                'html' => '<strong>Changes:</strong>'.$changesHtml,
                'changes' => $changes,
                'count' => $changeCount,
            ];
        }

        return [
            'title' => 'Success!',
            'message' => $settingName.' settings saved successfully (no changes detected).',
            'changes' => [],
            'count' => 0,
        ];
    }

    /**
     * Normalize value for comparison
     */
    private function normalizeValue($value, bool $isBooleanField = false): string
    {
        // For boolean fields, treat null as false
        if ($isBooleanField && is_null($value)) {
            return '0';
        }

        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        // Convert string representations of booleans
        $stringValue = (string) $value;
        if (in_array(strtolower($stringValue), ['true', 'false'], true)) {
            return strtolower($stringValue) === 'true' ? '1' : '0';
        }

        return $stringValue;
    }

    /**
     * Format boolean value for display
     */
    private function formatBooleanForDisplay($value): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        $stringValue = (string) $value;

        return in_array(strtolower($stringValue), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Get friendly meta group name
     */
    private function getFriendlyMetaGroupName(string $metaGroup): string
    {
        $names = [
            'branding' => 'Branding',
            'storage' => 'Storage',
            'media' => 'Media',
            'debug' => 'Debug',
            'login_security' => 'Login Security',
        ];

        return $names[$metaGroup] ?? ucwords(str_replace('_', ' ', $metaGroup));
    }

    /**
     * Get boolean fields for meta group
     */
    private function getBooleanFieldsForMetaGroup(string $metaGroup): array
    {
        $booleanFieldsMap = [
            'debug' => ['enable_debugging', 'enable_debugging_bar', 'remove_xframe_header'],
            'media' => ['image_optimization', 'delete_trashed'],
            'storage' => ['ftp_passive', 'ftp_ssl', 'use_path_style_endpoint'],
            'login_security' => ['limit_login_attempts_enabled'],
        ];

        return $booleanFieldsMap[$metaGroup] ?? [];
    }

    /**
     * Get field labels for meta group
     */
    private function getFieldLabelsForMetaGroup(): array
    {
        return [
            // Branding
            'brand_name' => 'Brand Name',
            'brand_website' => 'Brand Website',
            'logo' => 'Logo',
            'favicon' => 'Favicon',
            'icon' => 'Icon',
            'apple_touch_icon' => 'Apple Touch Icon',
            'android_icon' => 'Android Icon',
            'theme_mode' => 'Theme Mode',
            'primary_color' => 'Primary Color',
            'primary_color_rgb' => 'Primary Color RGB',
            'secondary_color' => 'Secondary Color',
            'secondary_color_rgb' => 'Secondary Color RGB',

            // Storage
            'storage_driver' => 'Storage Driver',
            'root_folder' => 'Root Folder',
            'max_storage_size' => 'Max Storage Size',
            'storage_cdn_url' => 'CDN URL',
            'ftp_host' => 'FTP Host',
            'ftp_username' => 'FTP Username',
            'ftp_password' => 'FTP Password',
            'ftp_root' => 'FTP Root',
            'ftp_port' => 'FTP Port',
            'ftp_passive' => 'FTP Passive Mode',
            'ftp_timeout' => 'FTP Timeout',
            'ftp_ssl' => 'FTP SSL',
            'ftp_ssl_mode' => 'FTP SSL Mode',
            'access_key' => 'AWS Access Key',
            'secret_key' => 'AWS Secret Key',
            'bucket' => 'S3 Bucket',
            'region' => 'AWS Region',
            'endpoint' => 'AWS Endpoint',
            'use_path_style_endpoint' => 'Use Path Style Endpoint',

            // Media
            'max_file_name_length' => 'Max File Name Length',
            'max_upload_size' => 'Max Upload Size (MB)',
            'allowed_file_types' => 'Allowed File Types',
            'image_optimization' => 'Image Conversions',
            'image_quality' => 'Image Quality',
            'thumbnail_width' => 'Thumbnail Width',
            'small_width' => 'Small Width',
            'medium_width' => 'Medium Width',
            'large_width' => 'Large Width',
            'xlarge_width' => 'Extra Large Width',
            'default_avatar_image' => 'Default Avatar',
            'default_placeholder_image' => 'Default Placeholder',
            'watermark_image' => 'Watermark Image',
            'delete_trashed' => 'Auto Delete Trashed',
            'delete_trashed_days' => 'Auto Delete After (days)',

            // Debug
            'enable_debugging' => 'Debug Mode',
            'enable_debugging_bar' => 'Debug Bar',
            'remove_xframe_header' => 'Remove X-Frame Header',

            // Login Security
            'admin_login_url_slug' => 'Admin Login URL',
            'limit_login_attempts_enabled' => 'Limit Login Attempts',
            'limit_login_attempts' => 'Max Login Attempts',
            'lockout_time' => 'Lockout Time (minutes)',
        ];
    }
}
