<?php

namespace App\Http\Middleware;

use App\Helpers\AbilityAggregator;
use App\Helpers\NavigationAggregator;
use App\Inertia\Properties\UserAvatar;
use App\Models\User;
use App\Modules\ModuleManager;
use App\Services\NotificationService;
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
            'runtime' => [
                'inertiaHardReloadPageLimit' => (int) config('app.inertia_hard_reload_page_limit', 15),
            ],
            'auth' => [
                'user' => fn (): ?array => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'avatar' => new UserAvatar($request->user()),
                    ]
                    : null,
                'abilities' => fn (): array => $this->resolveSharedAbilities($request, $hasMasterAccess),
                'impersonation' => fn (): ?array => $this->resolveImpersonation($request),
            ],
            'navigation' => fn (): array => NavigationAggregator::getUnifiedByArea($request->user()),
            'modules' => $modules,
            'notifications' => fn (): array => [
                'unreadCount' => $request->user()
                    ? app(NotificationService::class)->getUnreadCount($request->user())
                    : 0,
            ],
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

    /**
     * @return array<string, bool>
     */
    private function resolveSharedAbilities(Request $request, bool $hasMasterAccess): array
    {
        $user = $request->user();
        $abilityKeys = $this->sharedAbilityKeysForRequest($request);

        $abilities = [
            'manageModules' => $hasMasterAccess,
        ];

        if ($abilityKeys === []) {
            return $abilities;
        }

        $coreAbilityKeys = array_values(array_filter($abilityKeys, fn (string $key): bool => $this->isCoreSharedAbility($key)));
        $moduleAbilityKeys = array_values(array_diff($abilityKeys, $coreAbilityKeys));
        $moduleAbilities = AbilityAggregator::resolve($user, $moduleAbilityKeys);

        foreach ($coreAbilityKeys as $abilityKey) {
            $abilities[$abilityKey] = $this->resolveCoreSharedAbility($user, $abilityKey, $hasMasterAccess);
        }

        foreach ($moduleAbilityKeys as $abilityKey) {
            $abilities[$abilityKey] = $moduleAbilities[$abilityKey] ?? false;
        }

        return $abilities;
    }

    /**
     * @return array<int, string>
     */
    private function sharedAbilityKeysForRequest(Request $request): array
    {
        return match (true) {
            $request->routeIs('app.roles.*') => ['addRoles', 'editRoles', 'deleteRoles', 'restoreRoles'],
            $request->routeIs('app.users.*') => ['addUsers', 'editUsers', 'deleteUsers', 'restoreUsers'],
            $request->routeIs('app.masters.addresses.*') => ['addAddresses', 'editAddresses', 'deleteAddresses', 'restoreAddresses'],
            $request->routeIs('app.masters.email.providers.*') => ['addEmailProviders', 'editEmailProviders', 'deleteEmailProviders', 'restoreEmailProviders'],
            $request->routeIs('app.masters.email.templates.*') => ['addEmailTemplates', 'editEmailTemplates', 'deleteEmailTemplates', 'restoreEmailTemplates'],
            $request->routeIs('app.todos.*') => ['addTodos', 'editTodos', 'deleteTodos', 'restoreTodos'],
            $request->routeIs('platform.secrets.*') => ['addSecrets'],
            $request->routeIs('platform.servers.*') => ['addServers'],
            $request->routeIs('platform.agencies.*') => ['addAgencies', 'editAgencies', 'deleteAgencies', 'restoreAgencies'],
            $request->routeIs('platform.websites.*') => ['addWebsites'],
            $request->routeIs('platform.providers.*') => ['addProviders'],
            $request->routeIs('platform.tlds.*') => ['addTlds'],
            $request->routeIs('platform.domains.*') => ['addDomains', 'editDomains'],
            $request->routeIs('platform.ssl-certificates.*') => ['editDomains'],
            $request->routeIs('platform.dns.*') => ['addDomainDnsRecords'],
            $request->routeIs('releasemanager.releases.*') => ['addReleases', 'editReleases', 'deleteReleases', 'restoreReleases'],
            $request->routeIs('cms.posts.*') => ['addPosts', 'editPosts', 'deletePosts', 'restorePosts'],
            $request->routeIs('cms.pages.*') => ['addPages', 'editPages', 'deletePages', 'restorePages'],
            $request->routeIs('cms.categories.*') => ['addCategories', 'editCategories', 'deleteCategories', 'restoreCategories'],
            $request->routeIs('cms.tags.*') => ['addTags', 'editTags', 'deleteTags', 'restoreTags'],
            $request->routeIs('cms.form.*') => ['addCmsForms', 'editCmsForms', 'deleteCmsForms', 'restoreCmsForms'],
            $request->routeIs('cms.designblock.*') => ['addDesignBlocks', 'editDesignBlocks', 'deleteDesignBlocks', 'restoreDesignBlocks'],
            $request->routeIs('cms.appearance.menus.*') => ['addMenus', 'editMenus', 'deleteMenus', 'restoreMenus'],
            $request->routeIs('cms.appearance.themes.*') => ['addThemes', 'editThemes', 'deleteThemes'],
            $request->routeIs('cms.redirections.*') => ['addRedirections', 'editRedirections', 'deleteRedirections', 'restoreRedirections'],
            $request->routeIs('cms.settings.default-pages*') => ['manageDefaultPages'],
            $request->routeIs('cms.integrations.*') => ['manageIntegrationsSeoSettings'],
            $request->routeIs('helpdesk.departments.*') => ['addHelpdeskDepartments', 'editHelpdeskDepartments', 'deleteHelpdeskDepartments', 'restoreHelpdeskDepartments'],
            $request->routeIs('helpdesk.tickets.*') => ['addHelpdeskTickets', 'editHelpdeskTickets', 'deleteHelpdeskTickets', 'restoreHelpdeskTickets'],
            $request->routeIs('helpdesk.settings.*') => ['manageHelpdeskSettings'],
            $request->routeIs('app.customers.contacts.*') => ['addCustomerContacts', 'editCustomerContacts', 'deleteCustomerContacts', 'restoreCustomerContacts'],
            $request->routeIs('app.customers.*') => ['addCustomers', 'editCustomers', 'deleteCustomers', 'restoreCustomers'],
            $request->routeIs('app.billing.invoices.*') => ['addInvoices', 'editInvoices', 'deleteInvoices', 'restoreInvoices'],
            $request->routeIs('app.billing.payments.*') => ['addPayments', 'editPayments', 'deletePayments', 'restorePayments'],
            $request->routeIs('app.billing.credits.*') => ['addCredits', 'editCredits', 'deleteCredits', 'restoreCredits'],
            $request->routeIs('app.billing.refunds.*') => ['addRefunds', 'editRefunds', 'deleteRefunds', 'restoreRefunds'],
            $request->routeIs('app.billing.taxes.*') => ['addTaxes', 'editTaxes', 'deleteTaxes', 'restoreTaxes'],
            $request->routeIs('app.billing.coupons.*') => ['addCoupons', 'editCoupons', 'deleteCoupons', 'restoreCoupons'],
            $request->routeIs('app.billing.transactions.*') => ['viewTransactions'],
            $request->routeIs('app.billing.settings.*') => ['manageBillingSettings'],
            $request->routeIs('app.chatbot.*') => ['useChatbot', 'manageChatbotSettings'],
            $request->routeIs('ai-registry.providers.*') => ['viewAiProviders', 'addAiProviders', 'editAiProviders', 'deleteAiProviders', 'restoreAiProviders'],
            $request->routeIs('ai-registry.models.*') => ['viewAiModels', 'addAiModels', 'editAiModels', 'deleteAiModels', 'restoreAiModels'],
            default => [],
        };
    }

    private function isCoreSharedAbility(string $abilityKey): bool
    {
        return in_array($abilityKey, [
            'addRoles',
            'editRoles',
            'deleteRoles',
            'restoreRoles',
            'addUsers',
            'editUsers',
            'deleteUsers',
            'restoreUsers',
            'addAddresses',
            'editAddresses',
            'deleteAddresses',
            'restoreAddresses',
            'addEmailProviders',
            'editEmailProviders',
            'deleteEmailProviders',
            'restoreEmailProviders',
            'addEmailTemplates',
            'editEmailTemplates',
            'deleteEmailTemplates',
            'restoreEmailTemplates',
        ], true);
    }

    private function resolveCoreSharedAbility(?User $user, string $abilityKey, bool $hasMasterAccess): bool
    {
        return match ($abilityKey) {
            'addRoles' => $user?->can('add_roles') ?? false,
            'editRoles' => $user?->can('edit_roles') ?? false,
            'deleteRoles' => $user?->can('delete_roles') ?? false,
            'restoreRoles' => $user?->can('restore_roles') ?? false,
            'addUsers' => $user?->can('add_users') ?? false,
            'editUsers' => $user?->can('edit_users') ?? false,
            'deleteUsers' => $user?->can('delete_users') ?? false,
            'restoreUsers' => $user?->can('restore_users') ?? false,
            'addAddresses',
            'editAddresses',
            'deleteAddresses',
            'restoreAddresses',
            'addEmailProviders',
            'editEmailProviders',
            'deleteEmailProviders',
            'restoreEmailProviders',
            'addEmailTemplates',
            'editEmailTemplates',
            'deleteEmailTemplates',
            'restoreEmailTemplates' => $hasMasterAccess,
            default => false,
        };
    }
}
