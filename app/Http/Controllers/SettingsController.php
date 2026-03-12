<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\Status;
use App\Http\Requests\SettingsRequest;
use App\Jobs\RecacheApplication;
use App\Mail\TestEmail;
use App\Models\Role;
use App\Models\Settings;
use App\Services\GeoDataService;
use App\Traits\ActivityTrait;
use App\Traits\HasAlerts;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class SettingsController extends Controller
{
    use ActivityTrait;
    use HasAlerts;

    private bool $generalSettingsUpdated = false;

    public function __construct(
        private readonly Settings $settings,
        private readonly GeoDataService $geoDataService
    ) {}

    /**
     * Display the settings page.
     */
    public function index(): View
    {
        $this->authorizeManageSettings();

        $viewData = $this->formOptions();
        $viewData['settings'] = $this->getCachedSettings();

        return view('app.settings.settings', $viewData);
    }

    /**
     * Update a settings meta group.
     */
    public function update(string $metaGroup, SettingsRequest $request): RedirectResponse
    {
        $this->authorizeManageSettings();

        $metaGroup = Str::slug($metaGroup, '_');
        $section = $request->input('section');
        $redirectUrl = $request->input('redirect_to') ?: $this->getRedirectUrl('app.settings.index', $section);

        $this->generalSettingsUpdated = false;

        try {
            // Capture old values before changes
            $oldValues = $this->getCurrentSettingsState($metaGroup);

            $payload = $this->sanitizePayload($request);

            // For general settings, capture site_title and tagline before they're unset
            // These fields are stored via updateSeoSettingValue(), not storeMetaSettings()
            $generalFieldsForTracking = [];
            if ($metaGroup === 'general') {
                $generalFieldsForTracking = [
                    'site_title' => trim((string) ($payload['site_title'] ?? '')),
                    'tagline' => (string) ($payload['tagline'] ?? ''),
                ];
            }

            $this->processMetaGroupSettings($metaGroup, $payload);
            $wasUpdated = $this->storeMetaSettings($metaGroup, $payload);

            if ($metaGroup === 'general' && $this->generalSettingsUpdated) {
                $wasUpdated = true;
            }

            if (! $wasUpdated) {
                return $this->redirectWithError(
                    title: 'Settings Update Failed',
                    message: 'Unable to update settings. Please try again.',
                    redirectTo: $redirectUrl
                )->withInput($request->all());
            }

            $this->applyPostUpdateEffects($metaGroup, $payload);

            // Clear cached config when env values were written so the redirect
            // reads fresh .env values. The async RecacheApplication job will
            // rebuild the full cache shortly after.
            if ($this->metaGroupWritesEnv($metaGroup)) {
                try {
                    Artisan::call('config:clear');
                } catch (Throwable $e) {
                    Log::warning('Failed to clear configuration cache after settings update.', [
                        'meta_group' => $metaGroup,
                        'exception' => $e,
                    ]);
                }
            }

            // Use the transformed payload for change tracking (not the raw request data)
            // This ensures we compare what was actually saved to the database
            $normalizedData = $this->normalizeBooleanFields($metaGroup, $payload);

            // Re-add general fields that were unset during prepareMetaGroupPayload
            if ($metaGroup === 'general' && $generalFieldsForTracking !== []) {
                $normalizedData = array_merge($generalFieldsForTracking, $normalizedData);
            }

            // Build change summary for user feedback
            $changeSummary = $this->buildChangeSummary($metaGroup, $oldValues, $normalizedData);

            // Log with detailed change information
            $this->logSettingsUpdateWithChanges($metaGroup, $oldValues, $normalizedData);

            // Dispatch job to rebuild caches asynchronously (non-blocking)
            dispatch(new RecacheApplication('Settings update: '.$metaGroup));

            // Return success with detailed change summary
            return redirect($redirectUrl)
                ->with('success', $changeSummary);
        } catch (Throwable $throwable) {
            report($throwable);

            return $this->redirectWithError(
                title: 'Error Updating Settings',
                message: 'An unexpected error occurred: '.$throwable->getMessage(),
                redirectTo: $redirectUrl
            )->withInput($request->all());
        }
    }

    /**
     * Send a test email using the provided SMTP configuration.
     */
    public function sendTestMail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'email_driver' => ['required'],
            'email_sent_from_name' => ['required'],
            'email_sent_from_address' => ['required', 'email'],
            'smtp_host' => ['required_if:email_driver,smtp'],
            'smtp_port' => ['required_if:email_driver,smtp'],
            'smtp_username' => ['required_if:email_driver,smtp'],
            'smtp_password' => ['required_if:email_driver,smtp'],
            'smtp_encryption' => ['required_if:email_driver,smtp'],
            'smtp_security_type' => ['required_if:email_driver,smtp'],
        ]);

        $email = $request->input('email');

        try {
            $emailDriver = $request->input('email_driver');
            if ($emailDriver === 'smtp') {
                config([
                    'mail.default' => 'smtp',
                    'mail.mailers.smtp.host' => $request->input('smtp_host'),
                    'mail.mailers.smtp.port' => $request->input('smtp_port'),
                    'mail.mailers.smtp.username' => $request->input('smtp_username'),
                    'mail.mailers.smtp.password' => $request->input('smtp_password'),
                    'mail.mailers.smtp.encryption' => $request->input('smtp_encryption'),
                    'mail.mailers.smtp.security_type' => $request->input('smtp_security_type'),
                    'mail.mailers.smtp.streams.ssl.allow_self_signed' => true,
                ]);
            } else {
                config([
                    'mail.default' => 'sendmail',
                    'mail.mailers.sendmail.path' => '/usr/sbin/sendmail -bs',
                ]);
            }

            config([
                'mail.from.address' => $request->input('email_sent_from_address'),
                'mail.from.name' => $request->input('email_sent_from_name'),
            ]);

            Mail::to($email)->send(new TestEmail);

            return response()->json([
                'status' => 'success',
                'message' => 'Test email sent successfully.',
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test email: '.$exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Export settings data.
     */
    public function exportSettings(Request $request): JsonResponse
    {
        $exportOptions = $request->input('export_options', []);
        $settings = Settings::withoutTrashed()->get();

        $settingsData = $settings->filter(function ($setting) use ($exportOptions): bool {
            if ($exportOptions === 'all' || empty($exportOptions)) {
                return true;
            }

            foreach ($exportOptions as $option) {
                if (str_contains((string) $setting->key, $option)) {
                    return true;
                }
            }

            return false;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Settings exported successfully.',
            'jsondata' => $settingsData->values()->toJson(),
        ]);
    }

    /**
     * Import settings from a JSON file.
     */
    public function importSettings(Request $request): JsonResponse
    {
        $request->validate([
            'import_file' => ['required', 'mimes:json'],
        ], ['import_file.mimes' => 'Please upload a valid json file.']);

        $fileContents = File::get($request->file('import_file')->getRealPath());
        $settings = json_decode($fileContents, true) ?? [];

        if (empty($settings)) {
            return response()->json([
                'status' => 2,
                'type' => 'toast',
                'message' => 'failed to import settings. No data found in the file.',
                'refresh' => 'true',
            ]);
        }

        $wasImported = false;
        $userId = Auth::id();

        foreach ($settings as $entry) {
            if (! isset($entry['key'], $entry['value'])) {
                continue;
            }

            $data = [
                'key' => $entry['key'],
                'value' => $entry['value'],
                'updated_by' => $userId,
            ];

            $existing = $this->settings->where('key', $entry['key'])->first();

            if ($existing) {
                $existing->update($data);
                $wasImported = true;

                continue;
            }

            $data['created_by'] = $userId;
            $this->settings->create($data);
            $wasImported = true;
        }

        if (! $wasImported) {
            return $this->errorResponse('failed to import settings. Please check the data and try again.');
        }

        dispatch(new RecacheApplication('Settings import'));

        return response()->json([
            'status' => 1,
            'type' => 'toast',
            'message' => 'Settings imported successfully.',
            'refresh' => 'true',
        ]);
    }

    private function authorizeManageSettings(): void
    {
        abort_unless(Auth::user()?->can('manage_system_settings'), 401);
    }

    private function formOptions(): array
    {
        $countries = array_map(
            fn (array $country): array => [
                'value' => $country['iso2'],
                'label' => $country['name'],
            ],
            $this->geoDataService->getAllCountries()
        );

        $roles = Role::query()
            ->where('status', Status::ACTIVE)
            ->orderBy('display_name')
            ->orderBy('name')
            ->get(['id', 'display_name', 'name'])
            ->map(static function (Role $role): array {
                $displayName = (string) ($role->getAttribute('display_name') ?? '');

                return [
                    'value' => (string) $role->id,
                    'label' => $displayName !== '' ? $displayName : Str::headline($role->name),
                ];
            })
            ->values()
            ->all();

        return [
            'module_title' => __('settings.settings'),
            'module_name' => __('settings.settings'),
            'module_path' => 'app.settings',
            'parent_module' => __('settings.app_settings'),
            'action' => 'settings',
            'page_title' => __('settings.settings'),
            'date_formats' => config('constants.date_formats'),
            'time_formats' => config('constants.time_formats'),
            'timezones' => config('timezones'),
            'email_drivers' => config('constants.email_drivers'),
            'security_types' => [
                'tls' => ['label' => 'TLS', 'value' => 'tls'],
                'ssl' => ['label' => 'SSL', 'value' => 'ssl'],
            ],
            'storage_drivers' => config('constants.storage_drivers'),
            'languages' => config('languages'),
            'countries' => $countries,
            'roles' => $roles,
            'validationFieldLabels' => $this->getValidationFieldLabels(),
        ];
    }

    private function getCachedSettings(): array
    {
        // Don't cache settings to prevent stale data, use raw_value for booleans
        return Settings::all()->mapWithKeys(function ($setting): array {
            // Use raw_value for booleans to get 'true'/'false' strings for forms
            $value = $setting->type === 'boolean' ? $setting->raw_value : $setting->value;

            return [$setting->key => $value];
        })->toArray();
    }

    private function sanitizePayload(SettingsRequest $request): array
    {
        return $request->except([
            '_token',
            '_method',
            'meta_group',
            'section',
            'redirect_to',
            'site_logo',
            'favicon',
            'site_logo_id',
            'favicon_id',
            'site_logo_url',
            'favicon_url',
        ]);
    }

    /**
     * Process and transform settings payload for a specific meta group.
     * Handles special cases like env vars, file operations, and checkbox normalization.
     */
    private function processMetaGroupSettings(string $metaGroup, array &$payload): void
    {
        match ($metaGroup) {
            'general' => $this->prepareGeneralPayload($payload),
            'business' => null,
            'storage' => $this->prepareStoragePayload($payload),
            'registration' => $this->prepareRegistrationPayload($payload),
            'social_authentication' => $this->prepareSocialAuthPayload($payload),
            'email' => $this->prepareEmailPayload($payload),
            'localization' => $this->prepareLocalizationPayload(),
            'site_access_protection' => $this->prepareSiteAccessProtectionPayload($payload),
            // Google AdSense moved to CMS module: cms.integrations.googleadsense.update
            'maintenance' => $this->prepareMaintenancePayload($payload),
            'development' => $this->prepareDevelopmentPayload($payload),
            'media' => $this->prepareMediaPayload($payload),
            default => null,
        };
    }

    private function prepareGeneralPayload(array &$payload): void
    {
        $userId = Auth::id();
        $currentSessionCookie = config('session.cookie');
        $hasCustomSessionCookie = get_env_value('SESSION_COOKIE') !== null;

        if (array_key_exists('site_title', $payload)) {
            $siteTitle = trim((string) $payload['site_title']);

            if ($siteTitle !== '') {
                // Keep the session cookie name stable so changing the app name doesn't log users out
                if (! $hasCustomSessionCookie && $currentSessionCookie) {
                    set_env_value('SESSION_COOKIE', $currentSessionCookie, false);
                    config(['session.cookie' => $currentSessionCookie]);
                }

                if ($siteTitle !== config('app.name')) {
                    set_env_value('APP_NAME', $siteTitle, false);
                    config(['app.name' => $siteTitle]);
                }

                if ($siteTitle !== config('mail.from.name')) {
                    set_env_value('MAIL_FROM_NAME', $siteTitle, false);
                    config(['mail.from.name' => $siteTitle]);
                }
            }

            $this->updateSeoSettingValue('site_title', $siteTitle, $userId);

            $this->generalSettingsUpdated = true;

            unset($payload['site_title']);
        }

        if (array_key_exists('tagline', $payload)) {
            $tagline = (string) $payload['tagline'];

            $this->updateSeoSettingValue('tagline', $tagline, $userId);

            $this->generalSettingsUpdated = true;

            unset($payload['tagline']);
        }
    }

    private function updateSeoSettingValue(string $key, ?string $value, ?int $userId = null): void
    {
        $setting = Settings::query()->firstOrNew([
            'group' => 'seo',
            'key' => $key,
        ]);

        $setting->value = $value ?? '';

        if (empty($setting->type)) {
            $setting->type = 'string';
        }

        if (! $setting->exists && $userId) {
            $setting->created_by = $userId;
        }

        if ($userId) {
            $setting->updated_by = $userId;
        }

        $setting->save();
    }

    private function prepareStoragePayload(array &$payload): void
    {
        // Use array_key_exists to allow clearing values (empty string)
        if (array_key_exists('storage_driver', $payload)) {
            set_env_value('STORAGE_DISK', $payload['storage_driver'] ?? '', false);
        }

        if (array_key_exists('root_folder', $payload)) {
            set_env_value('STORAGE_ROOT_FOLDER', $payload['root_folder'] ?? '', false);
        }

        if (array_key_exists('storage_cdn_url', $payload)) {
            set_env_value('STORAGE_CDN_URL', $payload['storage_cdn_url'] ?? '', false);
        }

        if (($payload['storage_driver'] ?? null) === 'ftp') {
            set_env_value('FTP_HOST', $payload['ftp_host'] ?? '', false);
            set_env_value('FTP_USERNAME', $payload['ftp_username'] ?? '', false);
            set_env_value('FTP_PASSWORD', $payload['ftp_password'] ?? '', false);
        } elseif (($payload['storage_driver'] ?? null) === 's3') {
            set_env_value('AWS_ACCESS_KEY_ID', $payload['access_key'] ?? '', false);
            set_env_value('AWS_SECRET_ACCESS_KEY', $payload['secret_key'] ?? '', false);
            set_env_value('AWS_BUCKET', $payload['bucket'] ?? '', false);
            set_env_value('AWS_DEFAULT_REGION', $payload['region'] ?? '', false);
            set_env_value('AWS_ENDPOINT', $payload['endpoint'] ?? '', false);
            set_env_value('AWS_USE_PATH_STYLE_ENDPOINT', isset($payload['use_path_style_endpoint']) ? 'TRUE' : 'FALSE', false);
        }
    }

    private function prepareRegistrationPayload(array &$payload): void
    {
        $payload['enable_registration'] = isset($payload['enable_registration']) ? 'true' : 'false';
        $payload['require_email_verification'] = isset($payload['require_email_verification']) ? 'true' : 'false';
        $payload['auto_approve'] = isset($payload['auto_approve']) ? 'true' : 'false';

        if (array_key_exists('default_role', $payload)) {
            $payload['default_role'] = (string) $payload['default_role'];
        }
    }

    private function prepareSocialAuthPayload(array &$payload): void
    {
        $fields = [
            'enable_social_authentication',
            'enable_google_authentication',
            'enable_github_authentication',
        ];

        foreach ($fields as $field) {
            $payload[$field] ??= 0;
        }
    }

    private function prepareEmailPayload(array &$payload): void
    {
        $mapping = [
            'email_driver' => 'MAIL_MAILER',
            'email_host' => 'MAIL_HOST',
            'email_port' => 'MAIL_PORT',
            'email_username' => 'MAIL_USERNAME',
            'email_password' => 'MAIL_PASSWORD',
            'email_encryption' => 'MAIL_ENCRYPTION',
            'email_from_address' => 'MAIL_FROM_ADDRESS',
            'email_from_name' => 'MAIL_FROM_NAME',
        ];

        foreach ($mapping as $field => $envKey) {
            // Use array_key_exists to allow clearing values (empty string)
            if (array_key_exists($field, $payload)) {
                set_env_value($envKey, $payload[$field] ?? '', false);
            }
        }
    }

    private function prepareLocalizationPayload(): void
    {
        // Note: We no longer update the config file
        // Settings are stored only in the database
        // The config file serves as initial defaults only
    }

    private function prepareSiteAccessProtectionPayload(array &$payload): void
    {
        if (! isset($payload['is_enabled'])) {
            $payload['mode_enabled'] = 'false';
        } else {
            $payload['mode_enabled'] = 'true';
            unset($payload['is_enabled']);
        }
    }

    // Google AdSense settings moved to CMS module
    // See: modules/CMS/app/Services/SeoSettingService.php::processIntegrationsSettings()

    private function prepareMaintenancePayload(array &$payload): void
    {
        if (! isset($payload['enable_mode'])) {
            $payload['mode_enabled'] = 'false';
            // Keep the maintenance_mode_type even when disabled
        } else {
            $payload['mode_enabled'] = 'true';
            unset($payload['enable_mode']);
        }

        // Set default maintenance mode type if not provided
        if (! isset($payload['maintenance_mode_type']) || empty($payload['maintenance_mode_type'])) {
            $payload['maintenance_mode_type'] = 'frontend';
        }
    }

    private function prepareDevelopmentPayload(array &$payload): void
    {
        // CDN caching disabled when development mode is enabled
        $cacheEnabled = 'false';
        if (! isset($payload['mode_enabled'])) {
            $payload['mode_enabled'] = 'false';
            $cacheEnabled = 'true';
        }

        // Set cache-related env vars
        set_env_value('CDN_CACHE_HEADERS', $cacheEnabled, false);
    }

    private function prepareMediaPayload(array &$payload): void
    {
        $mapping = [
            'max_file_name_length' => 'MEDIA_MAX_FILE_NAME_LENGTH',
            'max_upload_size' => 'MEDIA_MAX_SIZE_IN_MB',
            'allowed_file_types' => 'MEDIA_ALLOWED_FILE_TYPES',
            'image_optimization' => 'MEDIA_IMAGE_OPTIMIZATION',
            'image_quality' => 'MEDIA_IMAGE_QUALITY',
            'thumbnail_width' => 'MEDIA_THUMBNAIL_WIDTH',
            'small_width' => 'MEDIA_SMALL_WIDTH',
            'medium_width' => 'MEDIA_MEDIUM_WIDTH',
            'large_width' => 'MEDIA_LARGE_WIDTH',
            'xlarge_width' => 'MEDIA_XLARGE_WIDTH',
            'delete_trashed' => 'MEDIA_AUTO_DELETE_TRASHED',
            'delete_trashed_days' => 'MEDIA_TRASH_AUTO_DELETE_DAYS',
        ];

        $booleanFields = ['image_optimization', 'delete_trashed'];
        foreach ($mapping as $field => $envKey) {
            // Use array_key_exists to allow clearing values (empty string)
            if (array_key_exists($field, $payload)) {
                $value = $payload[$field] ?? '';
                // Convert boolean fields to true/false strings
                if (in_array($field, $booleanFields)) {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
                }

                set_env_value($envKey, $value, false);
            }
        }
    }

    private function storeMetaSettings(string $metaGroup, array $payload): bool
    {
        // Social authentication is env-only — never persist to the database.
        // Return true so the update() method proceeds to applyPostUpdateEffects().
        if ($metaGroup === 'social_authentication') {
            return true;
        }

        $updated = false;
        $userId = Auth::id();

        foreach ($payload as $key => $value) {
            if ($this->shouldSkipValue($value)) {
                continue;
            }

            $settingKey = sprintf('%s_%s', $metaGroup, $key);
            $data = [
                'key' => $settingKey,
                'value' => $value,
                'updated_by' => $userId,
            ];

            $existing = $this->settings->where('key', $settingKey)->first();

            if ($existing) {
                $existing->update($data);
                $updated = true;

                continue;
            }

            $data['created_by'] = $userId;
            $this->settings->create($data);
            $updated = true;
        }

        return $updated;
    }

    private function shouldSkipValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return $value === '';
    }

    private function applyPostUpdateEffects(string $metaGroup, array $payload): void
    {
        // Note: We no longer update APP_TIMEZONE in .env
        // Localization timezone is stored in database only
        // APP_TIMEZONE should remain UTC for consistent data storage

        if ($metaGroup === 'social_authentication') {
            $this->applySocialAuthEnvValues($payload);
        }
    }

    /**
     * Check if a meta group writes environment variables during save.
     */
    private function metaGroupWritesEnv(string $metaGroup): bool
    {
        return in_array($metaGroup, [
            'general',
            'storage',
            'social_authentication',
            'email',
            'development',
            'media',
        ], true);
    }

    private function applySocialAuthEnvValues(array $payload): void
    {
        $socialEnabled = filter_var($payload['enable_social_authentication'] ?? false, FILTER_VALIDATE_BOOLEAN);
        set_env_value('SOCIAL_AUTH_ENABLED', $socialEnabled ? 'true' : 'false', false);

        $googleEnabled = filter_var($payload['enable_google_authentication'] ?? false, FILTER_VALIDATE_BOOLEAN);
        set_env_value('GOOGLE_AUTH_ENABLED', $googleEnabled ? 'true' : 'false', false);

        if (! empty($payload['google_client_id'])) {
            set_env_value('GOOGLE_CLIENT_ID', $payload['google_client_id'], false);
        }

        if (! empty($payload['google_client_secret'])) {
            set_env_value('GOOGLE_CLIENT_SECRET', $payload['google_client_secret'], false);
        }

        if ($googleEnabled) {
            set_env_value('GOOGLE_REDIRECT', route('social.login.callback', 'google'), false);
        }

        $githubEnabled = filter_var($payload['enable_github_authentication'] ?? false, FILTER_VALIDATE_BOOLEAN);
        set_env_value('GITHUB_AUTH_ENABLED', $githubEnabled ? 'true' : 'false', false);

        if (! empty($payload['github_client_id'])) {
            set_env_value('GITHUB_CLIENT_ID', $payload['github_client_id'], false);
        }

        if (! empty($payload['github_client_secret'])) {
            set_env_value('GITHUB_CLIENT_SECRET', $payload['github_client_secret'], false);
        }

        if ($githubEnabled) {
            set_env_value('GITHUB_REDIRECT', route('social.login.callback', 'github'), false);
        }

        set_env_value('TWITTER_CLIENT_ID', '', false);
        set_env_value('TWITTER_CLIENT_SECRET', '', false);
        set_env_value('TWITTER_REDIRECT', '', false);
    }

    // Removed: updateLocalizationConfig()
    // The config/appsettings.php file is now read-only and serves as initial defaults only
    // All localization settings are stored in the database via storeMetaSettings()

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
     * Get current settings state before changes
     */
    private function getCurrentSettingsState(string $metaGroup): array
    {
        $settings = $this->getCachedSettings();
        $prefix = $metaGroup.'_';
        $currentState = [];

        foreach ($settings as $key => $value) {
            if (str_starts_with((string) $key, $prefix)) {
                // Remove only the first occurrence of the prefix
                $field = substr((string) $key, strlen($prefix));
                $currentState[$field] = $value;
            }
        }

        if ($metaGroup === 'general') {
            $currentState['site_title'] = $settings['site_title'] ?? config('app.name');
            $currentState['tagline'] = $settings['tagline'] ?? '';
        }

        return $currentState;
    }

    /**
     * Normalize boolean fields (unchecked checkboxes don't submit, so we need to add them as false)
     */
    private function normalizeBooleanFields(string $metaGroup, array $payload): array
    {
        $booleanFields = $this->getBooleanFieldsForMetaGroup($metaGroup);

        foreach ($booleanFields as $field) {
            // If the boolean field is not in the payload, it means the checkbox was unchecked
            // We need to explicitly set it to false/0 for proper change detection
            if (! isset($payload[$field])) {
                $payload[$field] = '0';
            }
        }

        return $payload;
    }

    /**
     * Build change summary for user feedback
     */
    private function buildChangeSummary(string $metaGroup, array $oldValues, array $newValues): array
    {
        $settingName = $this->getFriendlyMetaGroupName($metaGroup);
        $booleanFields = $this->getBooleanFieldsForMetaGroup($metaGroup);
        $sensitiveFields = ['smtp_password', 'password', 'secret_key', 'api_key', 'client_secret',
            'google_client_secret', 'github_client_secret',
        ];
        $fileFields = ['site_logo', 'favicon', 'site_logo_url', 'favicon_url'];

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
     * Log settings update with detailed change information
     */
    private function logSettingsUpdateWithChanges(string $metaGroup, array $oldValues, array $newValues): void
    {
        $settingsModel = new Settings;
        $settingsModel->id = 0;

        $settingDisplayName = $this->getFriendlyMetaGroupName($metaGroup);
        $booleanFields = $this->getBooleanFieldsForMetaGroup($metaGroup);

        // Determine which fields actually changed
        $changedFields = [];
        $changes = [];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;
            $isBooleanField = in_array($key, $booleanFields);

            // Normalize values for comparison
            $normalizedOld = $this->normalizeValue($oldValue, $isBooleanField);
            $normalizedNew = $this->normalizeValue($newValue, $isBooleanField);

            if ($normalizedOld !== $normalizedNew) {
                $fieldLabel = ucwords(str_replace('_', ' ', $key));
                $changedFields[] = $fieldLabel;

                // Format display values
                $displayOldValue = $isBooleanField ? $this->formatBooleanForDisplay($oldValue) : $oldValue;
                $displayNewValue = $isBooleanField ? $this->formatBooleanForDisplay($newValue) : $newValue;

                $changes[$key] = [
                    'old' => $displayOldValue,
                    'new' => $displayNewValue,
                ];
            }
        }

        // Build descriptive log message
        $changeCount = count($changedFields);
        if ($changeCount > 0) {
            $fieldList = $changeCount <= 3
                ? implode(', ', $changedFields)
                : implode(', ', array_slice($changedFields, 0, 3)).' and '.($changeCount - 3).' more';

            $message = sprintf('Settings Updated: %s (%d field', $settingDisplayName, $changeCount).($changeCount > 1 ? 's' : '').sprintf(' changed: %s)', $fieldList);
        } else {
            $message = sprintf('Settings Saved: %s (no changes detected)', $settingDisplayName);
        }

        // Log with previous values for full audit trail
        $this->logActivityWithPreviousValues(
            $settingsModel,
            ActivityAction::UPDATE,
            $message,
            $oldValues,
            [
                'module_name' => 'App Settings',
                'meta_group' => $metaGroup,
                'setting_display_name' => $settingDisplayName,
                'changed_fields' => $changedFields,
                'change_count' => $changeCount,
                'new_values' => $newValues,
                'changes' => $changes,
            ]
        );
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
            'business' => 'Business Details',
            'registration' => 'Registration',
            'email' => 'Email',
            'social_authentication' => 'Social Authentication',
            'localization' => 'Localization',
            'site_access_protection' => 'Site Access Protection',
            'google_adsense' => 'Google Adsense',
            'maintenance' => 'Maintenance Mode',
            'coming_soon' => 'Coming Soon Mode',
            'development' => 'Development Mode',
            'storage' => 'Storage',
            'media' => 'Media',
        ];

        return $names[$metaGroup] ?? ucwords(str_replace('_', ' ', $metaGroup));
    }

    /**
     * Get boolean fields for meta group
     */
    private function getBooleanFieldsForMetaGroup(string $metaGroup): array
    {
        $booleanFieldsMap = [
            'registration' => ['enable_registration', 'require_email_verification', 'auto_approve'],
            'social_authentication' => ['enable_social_authentication', 'enable_google_authentication', 'enable_github_authentication'],
            'site_access_protection' => ['is_enabled'],
            'google_adsense' => ['enable_adsense', 'hide_ads_for_login_user', 'hide_ads_for_home_page'],
            'maintenance' => ['enable_mode'],
            'coming_soon' => ['enabled'],
            'development' => ['mode_enabled'],
            'media' => ['image_optimization', 'delete_trashed'],
        ];

        return $booleanFieldsMap[$metaGroup] ?? [];
    }

    /**
     * Get field labels for meta group
     */
    private function getFieldLabelsForMetaGroup(): array
    {
        // Common field labels across settings
        return [
            // General
            'site_title' => 'Site Title',
            'tagline' => 'Tagline',

            // Business
            'name' => 'Business Name',
            'contact_number' => 'Contact Number',
            'email' => 'Email Address',
            'address_1' => 'Address Line 1',
            'address_2' => 'Address Line 2',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'zip_code' => 'Zip Code',
            'website' => 'Website',
            'tax_number' => 'Tax Number',

            // Registration
            'enable_registration' => 'Enable Registration',
            'default_role' => 'Default Role',
            'require_email_verification' => 'Require Email Verification',
            'auto_approve' => 'Auto Approve',

            // Email
            'driver' => 'Email Driver',
            'sent_from_name' => 'From Name',
            'sent_from_address' => 'From Address',
            'smtp_host' => 'SMTP Host',
            'smtp_port' => 'SMTP Port',
            'smtp_username' => 'SMTP Username',
            'smtp_password' => 'SMTP Password',
            'smtp_encryption' => 'Encryption',
            'smtp_security_type' => 'Security Type',

            // Social Authentication
            'enable_social_authentication' => 'Social Authentication',
            'enable_google_authentication' => 'Google Authentication',
            'google_client_id' => 'Google Client ID',
            'google_client_secret' => 'Google Client Secret',
            'enable_github_authentication' => 'GitHub Authentication',
            'github_client_id' => 'GitHub Client ID',
            'github_client_secret' => 'GitHub Client Secret',

            // Localization
            'language' => 'Site Language',
            'date_format' => 'Date Format',
            'time_format' => 'Time Format',
            'timezone' => 'Timezone',

            // Site Access Protection
            'is_enabled' => 'Site Access Protection',
            'password' => 'Access Password',
            'protection_message' => 'Protection Message',

            // Google Adsense
            'enable_adsense' => 'Google Adsense',
            'google_adsense_code' => 'Adsense Code',
            'hide_ads_for_login_user' => 'Hide Ads for Logged-in Users',
            'hide_ads_for_home_page' => 'Hide Ads on Home Page',

            // Maintenance
            'enable_mode' => 'Maintenance Mode',
            'maintenance_mode_type' => 'Maintenance Mode Type',
            'message' => 'Message',
            'title' => 'Title',
            'description' => 'Message',

            // Coming Soon
            'enabled' => 'Coming Soon Mode',

            // Development
            'mode_enabled' => 'Development Mode',
        ];
    }

    /**
     * Get validation field labels for JavaScript form validation
     */
    private function getValidationFieldLabels(): array
    {
        return $this->getFieldLabelsForMetaGroup();
    }
}
