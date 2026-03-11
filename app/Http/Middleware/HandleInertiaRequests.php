<?php

namespace App\Http\Middleware;

use App\Inertia\Properties\UserAvatar;
use App\Modules\ModuleManager;
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
     * @see https://inertiajs.com/v3/advanced/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
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

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
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
                        'manageModules' => $request->user()->can('manage_modules'),
                        'viewRoles' => $request->user()->can('view_roles'),
                        'addRoles' => $request->user()->can('add_roles'),
                        'editRoles' => $request->user()->can('edit_roles'),
                        'deleteRoles' => $request->user()->can('delete_roles'),
                    ]
                    : [
                        'manageModules' => false,
                        'viewRoles' => false,
                        'addRoles' => false,
                        'editRoles' => false,
                        'deleteRoles' => false,
                    ],
            ],
            'modules' => $modules,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
