<?php

use App\Enums\Status;
use App\Http\Controllers\Admin\BroadcastNotificationController;
use App\Http\Controllers\AjaxController;
use App\Http\Controllers\CommentsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Logs\ActivityLogController;
use App\Http\Controllers\Logs\LoginAttemptController;
use App\Http\Controllers\Logs\NotFoundLogController;
use App\Http\Controllers\Masters\AddressController;
use App\Http\Controllers\Masters\EmailLogController;
use App\Http\Controllers\Masters\EmailProviderController;
use App\Http\Controllers\Masters\EmailTemplateController;
use App\Http\Controllers\Masters\GroupController;
use App\Http\Controllers\Masters\GroupItemController;
use App\Http\Controllers\Masters\LaravelToolsController;
use App\Http\Controllers\Masters\SettingsController as MastersSettingsController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaLibraryController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Profile\ProfileTwoFactorController;
use App\Http\Controllers\QueueMonitor\QueueMonitorController;
use App\Http\Controllers\RevisionsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// --- Public Routes ---
// Homepage redirect fallback (only when CMS module is not enabled).
if (! function_exists('module_enabled') || ! module_enabled('CMS')) {
    Route::get('/', function () {
        if (get_env_value('HOMEPAGE_REDIRECT_ENABLED', 'false') === 'true') {
            $slug = ltrim((string) get_env_value('HOMEPAGE_REDIRECT_SLUG', ''), '/');
            if ($slug !== '') {
                return redirect('/'.$slug);
            }
        }

        abort(404);
    })->name('home');
}

Route::get('language/{locale}', [LanguageController::class, 'switchLang'])->name('language.switch');

// --- Admin Routes ---
$adminPrefix = trim((string) config('app.admin_slug'), '/');

if ($adminPrefix !== 'admin') {
    Route::get('admin', fn () => abort(404))->name('legacy-admin.not-found');
    Route::get('admin/{path}', fn () => abort(404))
        ->where('path', '.*')
        ->name('legacy-admin.not-found.path');
}

Route::prefix($adminPrefix)->group(function (): void {
    // Redirect root admin URL to either login or dashboard.
    Route::get('/', [DashboardController::class, 'root']);

    // Cache clear route.
    Route::get('/cache-clear', [DashboardController::class, 'cacheClear'])->name('cache.clear');

    // --- Protected Admin Routes (Authentication Required) ---
    Route::middleware(['auth', 'user.status', 'verified', 'profile.completed'])->group(function (): void {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::group(['as' => 'app.'], function (): void {
            // --- Ajax Routes ---
            Route::get('/ajax/field-attribute', [AjaxController::class, 'fieldAttribute'])->name('ajax.field-attribute');
            Route::get('/ajax/states-by-country', [AjaxController::class, 'ajaxStatesByCountry'])->name('ajax.states-by-country');
            Route::get('/ajax/cities-by-state', [AjaxController::class, 'ajaxCitiesByState'])->name('ajax.cities-by-state');
            Route::get('/ajax/users', [AjaxController::class, 'users'])->name('ajax.users');

            // --- User Profile Management ---
            Route::get('/profile', [ProfileController::class, 'view'])->name('profile');
            Route::get('/profile/billing', [ProfileController::class, 'billing'])->name('profile.billing');
            Route::get('/profile/teams', [ProfileController::class, 'teams'])->name('profile.teams');
            Route::get('/profile/security', [ProfileController::class, 'security'])->name('profile.security');
            Route::get('/profile/security/password', [ProfileController::class, 'securityPassword'])->name('profile.security.password');
            Route::get('/profile/security/two-factor', [ProfileController::class, 'securityTwoFactor'])->name('profile.security.two-factor');
            Route::get('/profile/security/social-logins', [ProfileController::class, 'securitySocialLogins'])->name('profile.security.social-logins');
            Route::get('/profile/security/social-logins/connect/{provider}', [ProfileController::class, 'connectSocialLogin'])
                ->where('provider', 'google|github')
                ->name('profile.security.social-logins.connect');
            Route::delete('/profile/security/social-logins/{provider}', [ProfileController::class, 'disconnectSocialLogin'])
                ->where('provider', 'google|github')
                ->name('profile.security.social-logins.disconnect');
            Route::get('/profile/security/sessions', [ProfileController::class, 'securitySessions'])->name('profile.security.sessions');
            Route::patch('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
            Route::patch('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
            Route::post('/profile/password/verify', [ProfileController::class, 'verifyCurrentPassword'])->name('profile.password.verify');
            Route::delete('/profile/delete', [ProfileController::class, 'destroy'])->name('profile.destroy');

            // Two-factor authentication
            Route::post('/profile/two-factor', [ProfileTwoFactorController::class, 'store'])->name('profile.two-factor.store');
            Route::post('/profile/two-factor/confirm', [ProfileTwoFactorController::class, 'confirm'])
                ->middleware('throttle:6,1')
                ->name('profile.two-factor.confirm');
            Route::delete('/profile/two-factor', [ProfileTwoFactorController::class, 'destroy'])->name('profile.two-factor.destroy');
            Route::post('/profile/two-factor/recovery-codes', [ProfileTwoFactorController::class, 'regenerateRecoveryCodes'])
                ->middleware('throttle:6,1')
                ->name('profile.two-factor.recovery-codes.regenerate');

            // Session Management
            Route::get('/profile/sessions', [ProfileController::class, 'getSessions'])->name('profile.sessions.get');
            Route::delete('/profile/sessions/{sessionId}', [ProfileController::class, 'deleteSession'])->name('profile.sessions.delete');
            Route::delete('/profile/sessions', [ProfileController::class, 'deleteOtherSessions'])->name('profile.sessions.delete-others');

            // --- Notifications Center ---
            Route::prefix('notifications')->name('notifications.')->group(function (): void {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::get('/list', [NotificationController::class, 'list'])->name('list');
                Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
                Route::get('/dropdown', [NotificationController::class, 'dropdown'])->name('dropdown');
                Route::get('/stats', [NotificationController::class, 'stats'])->name('stats');
                Route::get('/preferences', [NotificationController::class, 'preferences'])->name('preferences');
                Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');

                Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
                Route::post('/mark-multiple-read', [NotificationController::class, 'markMultipleAsRead'])->name('mark-multiple-read');
                Route::post('/delete-multiple', [NotificationController::class, 'deleteMultiple'])->name('delete-multiple');
                Route::delete('/delete-all-read', [NotificationController::class, 'deleteAllRead'])->name('delete-all-read');
                Route::post('/toggle-enabled', [NotificationController::class, 'toggleEnabled'])->name('toggle-enabled');

                // Broadcast (Super Admin only) - MUST be before {notification} wildcard
                Route::middleware('role:super_user')->prefix('broadcast')->name('broadcast.')->group(function (): void {
                    Route::get('/', [BroadcastNotificationController::class, 'index'])->name('index');
                    Route::get('/create', [BroadcastNotificationController::class, 'create'])->name('create');
                    Route::post('/', [BroadcastNotificationController::class, 'store'])->name('store');
                });

                // Wildcard routes LAST
                Route::get('/{notification}', [NotificationController::class, 'show'])->name('show');
                Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('mark-read');
                Route::post('/{notification}/unread', [NotificationController::class, 'markAsUnread'])->name('mark-unread');
                Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
            });

            // --- Users Management ---
            Route::group(['prefix' => 'users', 'as' => 'users.'], function (): void {
                // ========================================
                // STATIC ROUTES FIRST
                // ========================================

                // Bulk action
                Route::post('/bulk-action', [UserController::class, 'bulkAction'])->name('bulk-action');

                // Create
                Route::get('/create', [UserController::class, 'create'])->name('create');
                Route::post('/', [UserController::class, 'store'])->name('store');

                // Stop impersonation (no param, must come before parameterized routes)
                Route::post('/stop-impersonating', [UserController::class, 'stopImpersonating'])->name('stop-impersonating');

                // ========================================
                // PARAMETERIZED ROUTES NEXT
                // ⚠️ Must have ->where() constraint to not catch status routes!
                // ========================================

                // Show (with numeric constraint)
                Route::get('/{user}', [UserController::class, 'show'])
                    ->name('show')
                    ->where('user', '[0-9]+');

                // Edit & Update
                Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
                Route::put('/{user}', [UserController::class, 'update'])->name('update');

                // Delete & Restore
                Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
                Route::delete('/{user}/force-delete', [UserController::class, 'forceDelete'])->name('force-delete');
                Route::patch('/{user}/restore', [UserController::class, 'restore'])->name('restore');

                // User-specific actions
                Route::post('/{user}/verify-email', [UserController::class, 'verifyEmail'])->name('verify-email');
                Route::patch('/{user}/approve', [UserController::class, 'approve'])->name('approve');
                Route::patch('/{user}/suspend', [UserController::class, 'suspend'])->name('suspend');
                Route::patch('/{user}/ban', [UserController::class, 'ban'])->name('ban');
                Route::patch('/{user}/unban', [UserController::class, 'unban'])->name('unban');

                // User Impersonation
                Route::get('/{user}/impersonate', [UserController::class, 'impersonate'])->name('impersonate');

                // ========================================
                // CATCH-ALL ROUTES LAST
                // ========================================

                // Index with optional status (MUST be last!)
                Route::get('/{status?}', [UserController::class, 'index'])
                    ->name('index')
                    ->where('status', 'all|'.implode('|', [
                        Status::ACTIVE->value,
                        Status::PENDING->value,
                        Status::SUSPENDED->value,
                        Status::BANNED->value,
                        'trash',
                    ]));
            });

            // --- Roles Management ---
            Route::group(['prefix' => 'roles', 'as' => 'roles.'], function (): void {
                // Static routes FIRST
                Route::post('/bulk-action', [RoleController::class, 'bulkAction'])->name('bulk-action');

                // Standard CRUD routes
                Route::get('/create', [RoleController::class, 'create'])->name('create');
                Route::post('/store', [RoleController::class, 'store'])->name('store');
                Route::get('/{role}/show', [RoleController::class, 'show'])->name('show');
                Route::get('/{role}/edit', [RoleController::class, 'edit'])->name('edit');
                Route::put('/{role}', [RoleController::class, 'update'])->name('update');
                Route::delete('/{role}/delete', [RoleController::class, 'destroy'])->name('destroy');
                Route::patch('/{role}/restore', [RoleController::class, 'restore'])->name('restore');
                Route::delete('/{role}/force-delete', [RoleController::class, 'forceDelete'])->name('force-delete');

                // Main index route with status filtering (must come last due to optional parameter)
                Route::get('/{status?}', [RoleController::class, 'index'])
                    ->where('status', 'all|'.implode('|', [
                        Status::ACTIVE->value,
                        Status::INACTIVE->value,
                        Status::DRAFT->value,
                        'trash',
                    ]))
                    ->name('index');
            });

            // --- System Settings ---
            Route::group(['prefix' => 'settings', 'as' => 'settings.'], function (): void {
                Route::get('/', [SettingsController::class, 'index'])->name('index');

                // Per-section pages
                Route::get('/general', [SettingsController::class, 'general'])->name('general');
                Route::get('/localization', [SettingsController::class, 'localization'])->name('localization');
                Route::get('/registration', [SettingsController::class, 'registration'])->name('registration');
                Route::get('/social-authentication', [SettingsController::class, 'socialAuthentication'])->name('social-authentication');
                Route::get('/site-access-protection', [SettingsController::class, 'siteAccessProtection'])->name('site-access-protection');
                Route::get('/maintenance', [SettingsController::class, 'maintenance'])->name('maintenance');
                Route::get('/coming-soon', [SettingsController::class, 'comingSoon'])->name('coming-soon');
                Route::get('/development', [SettingsController::class, 'development'])->name('development');

                // Updates
                Route::put('/{meta_group}/update', [SettingsController::class, 'update'])->name('update');
            });

            // --- Comment Management ---
            Route::group(['prefix' => 'comments', 'as' => 'comments.'], function (): void {
                Route::post('/store', [CommentsController::class, 'store'])->name('store');
            });

            // --- Masters: Modules Management ---
            Route::middleware('can:manage_modules')->group(function (): void {
                Route::group(['prefix' => 'masters/modules', 'as' => 'masters.modules.'], function (): void {
                    Route::get('/', [ModuleController::class, 'index'])->name('index');
                    Route::patch('/', [ModuleController::class, 'update'])->name('update');
                });
            });

            // --- Masters: Groups Management ---
            Route::group(['prefix' => 'masters/groups', 'as' => 'masters.groups.'], function (): void {
                // Bulk actions (non-parameterized route first)
                Route::post('/bulk-action', [GroupController::class, 'bulkAction'])->name('bulk-action');

                // Create routes
                Route::get('/create', [GroupController::class, 'create'])->name('create');
                Route::post('/', [GroupController::class, 'store'])->name('store');

                // Specific item routes (use {id} parameter)
                Route::get('/{id}', [GroupController::class, 'show'])->name('show')->where('id', '[0-9]+');
                Route::get('/{id}/edit', [GroupController::class, 'edit'])->name('edit');
                Route::put('/{id}', [GroupController::class, 'update'])->name('update');
                Route::delete('/{id}', [GroupController::class, 'destroy'])->name('destroy');
                Route::delete('/{id}/force-delete', [GroupController::class, 'forceDelete'])->name('force-delete');
                Route::patch('/{id}/restore', [GroupController::class, 'restore'])->name('restore');

                // Index with status filter (catch-all, must come last)
                Route::get('/{status?}', [GroupController::class, 'index'])->name('index')->where('status', '^(all|active|inactive|trash)$');

                // Group Items (nested routes)
                Route::group(['prefix' => '{group}/items', 'as' => 'items.'], function (): void {
                    // Bulk actions
                    Route::post('/bulk-action', [GroupItemController::class, 'bulkAction'])->name('bulk-action');

                    // Create routes
                    Route::get('/create', [GroupItemController::class, 'create'])->name('create');
                    Route::post('/', [GroupItemController::class, 'store'])->name('store');

                    // Specific item routes (use {id} parameter)
                    Route::get('/{id}', [GroupItemController::class, 'show'])->name('show')->where('id', '[0-9]+');
                    Route::get('/{id}/edit', [GroupItemController::class, 'edit'])->name('edit');
                    Route::put('/{id}', [GroupItemController::class, 'update'])->name('update');
                    Route::delete('/{id}', [GroupItemController::class, 'destroy'])->name('destroy');
                    Route::delete('/{id}/force-delete', [GroupItemController::class, 'forceDelete'])->name('force-delete');
                    Route::patch('/{id}/restore', [GroupItemController::class, 'restore'])->name('restore');

                    // Index with status filter (catch-all, must come last)
                    Route::get('/{status?}', [GroupItemController::class, 'index'])->name('index')->where('status', '^(all|active|inactive|trash)$');
                });
            });

            // --- Masters: Addresses Management ---
            Route::group(['prefix' => 'masters/addresses', 'as' => 'masters.addresses.'], function (): void {
                // Specific routes FIRST (more specific patterns)
                Route::get('/create', [AddressController::class, 'create'])->name('create');
                Route::get('/{address}', [AddressController::class, 'show'])->name('show')->where('address', '[0-9]+');
                Route::get('/{address}/edit', [AddressController::class, 'edit'])->name('edit');
                Route::post('/', [AddressController::class, 'store'])->name('store');
                Route::put('/{address}', [AddressController::class, 'update'])->name('update');
                Route::delete('/{address}/delete', [AddressController::class, 'destroy'])->name('destroy');
                Route::delete('/{address}/force-delete', [AddressController::class, 'forceDelete'])->name('force-delete');
                Route::patch('/{address}/restore', [AddressController::class, 'restore'])->name('restore');
                Route::post('/bulk-action', [AddressController::class, 'bulkAction'])->name('bulk-action');

                // Generic routes LAST (catch-all patterns)
                Route::get('/{status?}', [AddressController::class, 'index'])->name('index')->where('status', '^(all|trash)$');
            });

            // --- Logs: Activity Logs Management ---
            Route::group(['prefix' => 'logs/activity-logs', 'as' => 'logs.activity-logs.'], function (): void {
                // Non-parameterized routes first
                Route::post('bulk-action', [ActivityLogController::class, 'bulkAction'])->name('bulk-action');
                Route::post('cleanup', [ActivityLogController::class, 'cleanup'])->name('cleanup');
                Route::post('export', [ActivityLogController::class, 'export'])->name('export');
                Route::get('statistics', [ActivityLogController::class, 'statistics'])->name('statistics');

                // Parameterized routes
                Route::get('{activityLog}', [ActivityLogController::class, 'show'])->name('show')->where('activityLog', '[0-9]+');
                Route::delete('{activityLog}', [ActivityLogController::class, 'destroy'])->name('destroy')->where('activityLog', '[0-9]+');
                Route::delete('{activityLog}/force-delete', [ActivityLogController::class, 'forceDelete'])->name('force-delete')->where('activityLog', '[0-9]+');
                Route::patch('{activityLog}/restore', [ActivityLogController::class, 'restore'])->name('restore')->where('activityLog', '[0-9]+');

                // Index with optional status (must be last)
                Route::get('/{status?}', [ActivityLogController::class, 'index'])
                    ->name('index')
                    ->where('status', '^(all|trash)$');
            });

            // --- Logs: Login Attempts Management ---
            Route::group(['prefix' => 'logs/login-attempts', 'as' => 'logs.login-attempts.'], function (): void {
                // Non-parameterized routes first
                Route::post('bulk-action', [LoginAttemptController::class, 'bulkAction'])->name('bulk-action');
                Route::post('cleanup', [LoginAttemptController::class, 'cleanup'])->name('cleanup');
                Route::post('clear-rate-limit', [LoginAttemptController::class, 'clearRateLimit'])->name('clear-rate-limit');
                Route::get('blocked-ips', [LoginAttemptController::class, 'getBlockedIps'])->name('blocked-ips');

                // Parameterized routes
                Route::get('{loginAttempt}', [LoginAttemptController::class, 'show'])->name('show')->where('loginAttempt', '[0-9]+');
                Route::delete('{loginAttempt}', [LoginAttemptController::class, 'destroy'])->name('destroy')->where('loginAttempt', '[0-9]+');
                Route::delete('{loginAttempt}/force-delete', [LoginAttemptController::class, 'forceDelete'])->name('force-delete')->where('loginAttempt', '[0-9]+');
                Route::patch('{loginAttempt}/restore', [LoginAttemptController::class, 'restore'])->name('restore')->where('loginAttempt', '[0-9]+');

                // Index with optional status (must be last)
                Route::get('/{status?}', [LoginAttemptController::class, 'index'])
                    ->name('index')
                    ->where('status', '^(all|success|failed|blocked|trash)$');
            });

            // --- Logs: 404 Logs Management ---
            Route::group(['prefix' => 'logs/not-found-logs', 'as' => 'logs.not-found-logs.'], function (): void {
                // Non-parameterized routes first
                Route::post('bulk-action', [NotFoundLogController::class, 'bulkAction'])->name('bulk-action');
                Route::post('cleanup', [NotFoundLogController::class, 'cleanup'])->name('cleanup');
                Route::get('statistics', [NotFoundLogController::class, 'statistics'])->name('statistics');

                // Parameterized routes
                Route::get('{notFoundLog}', [NotFoundLogController::class, 'show'])->name('show')->where('notFoundLog', '[0-9]+');
                Route::delete('{notFoundLog}', [NotFoundLogController::class, 'destroy'])->name('destroy')->where('notFoundLog', '[0-9]+');
                Route::delete('{notFoundLog}/force-delete', [NotFoundLogController::class, 'forceDelete'])->name('force-delete')->where('notFoundLog', '[0-9]+');
                Route::patch('{notFoundLog}/restore', [NotFoundLogController::class, 'restore'])->name('restore')->where('notFoundLog', '[0-9]+');

                // Index with optional status (must be last)
                Route::get('/{status?}', [NotFoundLogController::class, 'index'])
                    ->name('index')
                    ->where('status', '^(all|suspicious|bots|human|trash)$');
            });

            // --- Masters: General Settings ---
            Route::group(['prefix' => 'masters/settings', 'as' => 'masters.settings.'], function (): void {
                Route::get('/', [MastersSettingsController::class, 'index'])->name('index');

                // Per-section pages
                Route::get('/app', [MastersSettingsController::class, 'app'])->name('app');
                Route::get('/branding', [MastersSettingsController::class, 'branding'])->name('branding');
                Route::get('/login-security', [MastersSettingsController::class, 'loginSecurity'])->name('login-security');
                Route::get('/email', [MastersSettingsController::class, 'email'])->name('email');
                Route::get('/storage', [MastersSettingsController::class, 'storage'])->name('storage');
                Route::get('/media', [MastersSettingsController::class, 'media'])->name('media');
                Route::get('/debug', [MastersSettingsController::class, 'debug'])->name('debug');

                // Updates + actions
                Route::put('/{meta_group}/update', [MastersSettingsController::class, 'update'])->name('update');
                Route::post('/email/send-test-mail', [MastersSettingsController::class, 'sendTestMail'])->name('send-test-mail');
                Route::post('/storage/test-connection', [MastersSettingsController::class, 'testStorageConnection'])->name('test-storage-connection');
            });

            // --- Masters: Laravel Tools ---
            Route::group(['prefix' => 'masters/laravel-tools', 'as' => 'masters.laravel-tools.'], function (): void {
                Route::get('/', [LaravelToolsController::class, 'index'])->name('index');

                // ENV Editor
                Route::get('/env', [LaravelToolsController::class, 'envEditor'])->name('env');
                Route::put('/env', [LaravelToolsController::class, 'updateEnv'])->name('env.update');
                Route::post('/env/restore', [LaravelToolsController::class, 'restoreEnvBackup'])->name('env.restore');
                Route::get('/env/backups', [LaravelToolsController::class, 'getEnvBackupsList'])->name('env.backups');

                // Artisan Runner
                Route::get('/artisan', [LaravelToolsController::class, 'artisanRunner'])->name('artisan');
                Route::post('/artisan/run', [LaravelToolsController::class, 'runArtisan'])->name('artisan.run');

                // Config Browser
                Route::get('/config', [LaravelToolsController::class, 'configBrowser'])->name('config');
                Route::get('/config/values', [LaravelToolsController::class, 'getConfigValues'])->name('config.values');

                // Log Viewer
                Route::get('/logs', [LaravelToolsController::class, 'logViewer'])->name('logs');
                Route::get('/logs/entries', [LaravelToolsController::class, 'getLogEntries'])->name('logs.entries');
                Route::delete('/logs/{filename}', [LaravelToolsController::class, 'deleteLog'])->name('logs.delete');

                // Laravel Queue
                Route::get('/queue', [LaravelToolsController::class, 'queueMonitor'])->name('queue');
                Route::get('/queue/data', [LaravelToolsController::class, 'getQueueData'])->name('queue.data');
                Route::post('/queue/retry/{monitor}', [LaravelToolsController::class, 'retryQueueJob'])->name('queue.retry');
                Route::delete('/queue/{monitor}', [LaravelToolsController::class, 'deleteQueueJob'])->name('queue.delete');
                Route::delete('/queue/purge', [LaravelToolsController::class, 'purgeQueueJobs'])->name('queue.purge');

                // Route List
                Route::get('/routes', [LaravelToolsController::class, 'routeList'])->name('routes');
                Route::get('/routes/list', [LaravelToolsController::class, 'getRoutes'])->name('routes.list');

                // PHP Diagnostics
                Route::get('/php', [LaravelToolsController::class, 'phpDiagnostics'])->name('php');
            });

            // --- Masters: Email Management ---
            Route::group(['prefix' => 'masters/email', 'as' => 'masters.email.'], function (): void {
                // Email Providers
                Route::group(['prefix' => 'providers', 'as' => 'providers.'], function (): void {
                    // ========================================
                    // STATIC ROUTES FIRST
                    // ========================================

                    // Bulk action
                    Route::post('/bulk-action', [EmailProviderController::class, 'bulkAction'])->name('bulk-action');

                    // Create
                    Route::get('/create', [EmailProviderController::class, 'create'])->name('create');
                    Route::post('/', [EmailProviderController::class, 'store'])->name('store');

                    // ========================================
                    // PARAMETERIZED ROUTES NEXT
                    // ⚠️ Must have ->where() constraint to not catch status routes!
                    // ========================================

                    // Show (with numeric constraint)
                    Route::get('/{emailProvider}', [EmailProviderController::class, 'show'])
                        ->name('show')
                        ->where('emailProvider', '[0-9]+');

                    // Edit
                    Route::get('/{emailProvider}/edit', [EmailProviderController::class, 'edit'])->name('edit');
                    Route::put('/{emailProvider}', [EmailProviderController::class, 'update'])->name('update');

                    // Delete
                    Route::delete('/{emailProvider}', [EmailProviderController::class, 'destroy'])->name('destroy');
                    Route::delete('/{emailProvider}/force-delete', [EmailProviderController::class, 'forceDelete'])->name('force-delete');
                    Route::patch('/{emailProvider}/restore', [EmailProviderController::class, 'restore'])->name('restore');

                    // ========================================
                    // CATCH-ALL ROUTES LAST
                    // ========================================

                    // Index with optional status (MUST be last!)
                    Route::get('/{status?}', [EmailProviderController::class, 'index'])
                        ->name('index')
                        ->where('status', '^(all|active|inactive|trash)$');
                });
                // Email Templates
                Route::group(['prefix' => 'templates', 'as' => 'templates.'], function (): void {
                    // ========================================
                    // STATIC ROUTES FIRST
                    // ========================================

                    // Bulk action
                    Route::post('/bulk-action', [EmailTemplateController::class, 'bulkAction'])->name('bulk-action');

                    // Create
                    Route::get('/create', [EmailTemplateController::class, 'create'])->name('create');
                    Route::post('/', [EmailTemplateController::class, 'store'])->name('store');

                    // ========================================
                    // PARAMETERIZED ROUTES NEXT
                    // ⚠️ Must have ->where() constraint to not catch status routes!
                    // ========================================

                    // Show (with numeric constraint)
                    Route::get('/{emailTemplate}', [EmailTemplateController::class, 'show'])
                        ->name('show')
                        ->where('emailTemplate', '[0-9]+');

                    // Edit
                    Route::get('/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('edit');
                    Route::put('/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('update');

                    // Delete
                    Route::delete('/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('destroy');
                    Route::delete('/{emailTemplate}/force-delete', [EmailTemplateController::class, 'forceDelete'])->name('force-delete');
                    Route::patch('/{emailTemplate}/restore', [EmailTemplateController::class, 'restore'])->name('restore');

                    // Send test email
                    Route::post('/{emailTemplate}/send-test', [EmailTemplateController::class, 'sendTestEmail'])->name('send-test');

                    // ========================================
                    // CATCH-ALL ROUTES LAST
                    // ========================================

                    // Index with optional status (MUST be last!)
                    Route::get('/{status?}', [EmailTemplateController::class, 'index'])
                        ->name('index')
                        ->where('status', '^(all|active|inactive|trash)$');
                });
                // Email Logs (Read-only, no create/edit)
                Route::group(['prefix' => 'logs', 'as' => 'logs.'], function (): void {
                    // ========================================
                    // PARAMETERIZED ROUTES
                    // =========================================

                    // Show (with numeric constraint)
                    Route::get('/{emailLog}', [EmailLogController::class, 'show'])
                        ->name('show')
                        ->where('emailLog', '[0-9]+');

                    // ========================================
                    // CATCH-ALL ROUTES LAST
                    // ========================================

                    // Index with optional status (MUST be last!)
                    Route::get('/{status?}', [EmailLogController::class, 'index'])
                        ->name('index')
                        ->where('status', '^(all|sent|failed|queued)$');
                });
            });

            // --- Notes Management ---
            Route::group(['prefix' => 'notes', 'as' => 'notes.'], function (): void {
                Route::post('/', [NotesController::class, 'store'])->name('store');
                Route::get('/{note}/edit', [NotesController::class, 'edit'])->name('edit');
                Route::put('/{note}', [NotesController::class, 'update'])->name('update');
                Route::delete('/{note}', [NotesController::class, 'destroy'])->name('destroy');
                Route::post('/{note}/pin', [NotesController::class, 'togglePin'])->name('toggle-pin');
            });

            // --- Media Management (API routes - used by Media Library V2) ---
            Route::group(['prefix' => 'media', 'as' => 'media.'], function (): void {
                // Upload and API endpoints
                Route::post('/upload-media', [MediaController::class, 'uploadMediaFiles'])->withoutMiddleware([ValidateCsrfToken::class])->name('upload-media');
                Route::get('/upload-settings', [MediaController::class, 'getUploadSettings'])->name('upload-settings');
                Route::get('/get-all-media', [MediaController::class, 'getAllMedia'])->name('get-all-media');
                Route::post('/media-details/update', [MediaController::class, 'updateDetails'])->withoutMiddleware([ValidateCsrfToken::class])->name('detail.update');
                Route::delete('/media-details/{media_id}/delete', [MediaController::class, 'deleteMedia'])->name('delete-media');

                // MediaVariationService endpoints
                Route::get('/{id}/conversion-status', [MediaController::class, 'getConversionStatus'])->name('conversion-status');
                Route::get('/variation-config', [MediaController::class, 'getVariationConfig'])->name('variation-config');
                Route::get('/{id}/responsive-data', [MediaController::class, 'getResponsiveImageData'])->name('responsive-data');
                Route::get('/{id}/responsive-html', [MediaController::class, 'getResponsiveImageHtml'])->name('responsive-html');

                // API endpoint for modal operations (unified media library)
                Route::get('/{id}/details', [MediaController::class, 'getMediaDetails'])->name('details')->where('id', '[0-9]+');

                // Management operations
                Route::delete('/{id}', [MediaController::class, 'destroy'])->name('destroy');
                Route::patch('/{id}/restore', [MediaController::class, 'restore'])->name('restore');

                // Bulk operations
                Route::post('/bulk-destroy', [MediaController::class, 'bulkDestroy'])->name('bulk.destroy');
                Route::post('/bulk-restore', [MediaController::class, 'bulkRestore'])->name('bulk.restore');
                Route::post('/bulk-update-metadata', [MediaController::class, 'bulkUpdateMetadata'])->name('bulk.update-metadata');

                // Note: Index route removed - use Media Library V2 at app.media-library.index
            });

            // --- Media Library V2 (DataGrid-based) ---
            Route::group(['prefix' => 'media-library', 'as' => 'media-library.'], function (): void {
                Route::post('/bulk-action', [MediaLibraryController::class, 'bulkAction'])->name('bulk-action');
                Route::get('/refresh', [MediaLibraryController::class, 'refreshData'])->name('refresh');
                Route::get('/', [MediaLibraryController::class, 'index'])->name('index');
            });

            // --- Revisions Management ---
            Route::group(['prefix' => 'revisions', 'as' => 'revisions.'], function (): void {
                Route::post('/show', [RevisionsController::class, 'show'])->name('show');
                Route::post('/{revision}/restore', [RevisionsController::class, 'restore'])->name('restore');
            });
        });

        // --- Queue Monitor ---
        Route::prefix('masters/queue-monitor')
            ->name('app.masters.queue-monitor.')
            ->controller(QueueMonitorController::class)
            ->group(function (): void {
                Route::get('data', 'data')->name('data');
                Route::post('bulk-action', 'bulkAction')->name('bulk-action');
                Route::delete('monitors/{id}', 'destroy')->name('destroy');
                Route::patch('monitors/{id}/retry', 'retry')->name('retry');
                Route::get('workers', 'workers')->name('workers');
                Route::get('/{status?}', 'index')
                    ->name('index')
                    ->where('status', '^(all|succeeded|failed|running|queued|stale)$');
            });
    });
});

// --- Authentication Routes ---
require __DIR__.'/auth.php';

// Catch-all route for 404 handling (must be absolute last)
Route::fallback(function (): void {
    // This will trigger Laravel's 404 handling which will use our theme-based system
    abort(404);
});
