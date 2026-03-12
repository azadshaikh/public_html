<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [

    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * Super User Bypass System
         *
         * This Gate::before() callback provides a "free pass" for users with the 'super_user' role.
         * Super users can:
         * - Access any route/action regardless of permissions
         * - Bypass all permission checks in controllers
         * - Access any module regardless of activation status
         * - Perform any action without specific permission grants
         *
         * How it works:
         * 1. Before any authorization check, this callback runs first
         * 2. If user has the super_user role, it returns true (grant access)
         * 3. If not a super user, it returns null (let other gates handle authorization)
         *
         * Usage in controllers:
         * - Keep existing 'permission:permission_name' middleware unchanged
         * - Super users will automatically bypass, others will be checked normally
         *
         * Example:
         * new Middleware('permission:view_users', only: ['index'])
         */
        Gate::before(function ($user): ?true {
            // Check if user has super_user role (by ID for reliability)
            if ($user && $user->hasRole(User::superUserRoleId())) {
                return true; // Grant access to everything
            }

            return null; // Let other gates handle the authorization
        });
    }
}
