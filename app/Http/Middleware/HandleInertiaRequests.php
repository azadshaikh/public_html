<?php

namespace App\Http\Middleware;

use App\Helpers\AbilityAggregator;
use App\Helpers\NavigationAggregator;
use App\Inertia\Properties\UserAvatar;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Support\Auth\SuperUserAccess;
use Composer\InstalledVersions;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/v3/installation/server-side-setup#setup-root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * The admin slug and the user's primary role ID are included so that
     * changing either one invalidates the client-side Ziggy route definitions
     * (rendered on full page loads).  When Inertia detects a version mismatch
     * it forces a full browser reload, ensuring filtered routes are refreshed.
     *
     * @see https://inertiajs.com/v3/advanced/asset-versioning
     */
    public function version(Request $request): ?string
    {
        $parentVersion = parent::version($request) ?? '';
        $adminSlug = config('app.admin_slug', '');
        $roleId = $request->user()?->roles->first()?->id ?? 'guest';

        return md5($parentVersion.'|admin_slug:'.$adminSlug.'|role:'.$roleId);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/v3/data-props/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $modules = resolve(ModuleManager::class)->sharedData();
        $hasMasterAccess = SuperUserAccess::allows($request->user());

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'appVersion' => InstalledVersions::getRootPackage()['pretty_version'] ?? 'dev-main',
            'branding' => [
                'name' => (string) config('astero.branding.name', ''),
                'website' => (string) config('astero.branding.website', ''),
                'logo' => (string) config('astero.branding.logo', ''),
                'icon' => (string) config('astero.branding.icon', ''),
            ],
            'auth' => [
                'user' => fn (): ?array => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'avatar' => new UserAvatar($request->user()),
                        'email_verified_at' => $request->user()->email_verified_at,
                        'created_at' => $request->user()->created_at,
                        'updated_at' => $request->user()->updated_at,
                    ]
                    : null,
                'abilities' => fn (): array => $request->user()
                    ? [
                        'manageModules' => $hasMasterAccess,
                        'viewRoles' => $request->user()->can('view_roles'),
                        'addRoles' => $request->user()->can('add_roles'),
                        'editRoles' => $request->user()->can('edit_roles'),
                        'deleteRoles' => $request->user()->can('delete_roles'),
                        'restoreRoles' => $request->user()->can('restore_roles'),
                        'viewUsers' => $request->user()->can('view_users'),
                        'addUsers' => $request->user()->can('add_users'),
                        'editUsers' => $request->user()->can('edit_users'),
                        'deleteUsers' => $request->user()->can('delete_users'),
                        'restoreUsers' => $request->user()->can('restore_users'),
                        'impersonateUsers' => $request->user()->can('impersonate_users'),
                        'viewAddresses' => $hasMasterAccess,
                        'addAddresses' => $hasMasterAccess,
                        'editAddresses' => $hasMasterAccess,
                        'deleteAddresses' => $hasMasterAccess,
                        'restoreAddresses' => $hasMasterAccess,
                        'viewEmailProviders' => $hasMasterAccess,
                        'addEmailProviders' => $hasMasterAccess,
                        'editEmailProviders' => $hasMasterAccess,
                        'deleteEmailProviders' => $hasMasterAccess,
                        'restoreEmailProviders' => $hasMasterAccess,
                        'viewEmailTemplates' => $hasMasterAccess,
                        'addEmailTemplates' => $hasMasterAccess,
                        'editEmailTemplates' => $hasMasterAccess,
                        'deleteEmailTemplates' => $hasMasterAccess,
                        'restoreEmailTemplates' => $hasMasterAccess,
                        'viewEmailLogs' => $hasMasterAccess,
                        ...AbilityAggregator::resolve($request->user()),
                    ]
                    : [
                        'manageModules' => false,
                        'viewRoles' => false,
                        'addRoles' => false,
                        'editRoles' => false,
                        'deleteRoles' => false,
                        'restoreRoles' => false,
                        'viewUsers' => false,
                        'addUsers' => false,
                        'editUsers' => false,
                        'deleteUsers' => false,
                        'restoreUsers' => false,
                        'impersonateUsers' => false,
                        'viewAddresses' => false,
                        'addAddresses' => false,
                        'editAddresses' => false,
                        'deleteAddresses' => false,
                        'restoreAddresses' => false,
                        'viewEmailProviders' => false,
                        'addEmailProviders' => false,
                        'editEmailProviders' => false,
                        'deleteEmailProviders' => false,
                        'restoreEmailProviders' => false,
                        'viewEmailTemplates' => false,
                        'addEmailTemplates' => false,
                        'editEmailTemplates' => false,
                        'deleteEmailTemplates' => false,
                        'restoreEmailTemplates' => false,
                        'viewEmailLogs' => false,
                        ...AbilityAggregator::resolve(null),
                    ],
                'impersonation' => fn (): ?array => $this->resolveImpersonation($request),
            ],
            'navigation' => fn (): array => NavigationAggregator::getUnifiedByArea($request->user()),
            'modules' => $modules,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => fn (): array => array_filter([
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'status' => $request->session()->get('status'),
            ]),
        ];
    }

    /**
     * @return array{
     *     active: true,
     *     impersonator: array{id: int, name: string, email: string},
     *     stopUrl: string
     * }|null
     */
    private function resolveImpersonation(Request $request): ?array
    {
        if (! $request->user() || ! $request->session()->has('impersonator_id')) {
            return null;
        }

        $impersonatorId = (int) $request->session()->get('impersonator_id');
        $impersonator = User::query()
            ->select(['id', 'name', 'email'])
            ->find($impersonatorId);

        if (! $impersonator) {
            return null;
        }

        return [
            'active' => true,
            'impersonator' => [
                'id' => $impersonator->id,
                'name' => $impersonator->name,
                'email' => $impersonator->email,
            ],
            'stopUrl' => route('app.users.stop-impersonating'),
        ];
    }
}
