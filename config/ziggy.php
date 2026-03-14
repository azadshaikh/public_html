<?php

/*
|--------------------------------------------------------------------------
| Ziggy Route Filtering
|--------------------------------------------------------------------------
|
| Route groups control which named routes are exposed to the frontend via
| the @routes Blade directive. The ZiggyRouteFilter service selects the
| appropriate groups based on the current user's role and permissions,
| so only authorised routes appear in page source.
|
| Each group is an array of route-name patterns (supports * wildcards).
| See: Tighten\Ziggy\Ziggy::filter()
|
*/

return [

    'groups' => [

        /*
        |----------------------------------------------------------------------
        | Public — accessible without authentication
        |----------------------------------------------------------------------
        */
        'public' => [
            'login',
            'login.*',
            'register',
            'register.*',
            'password.*',
            'social.*',
            'two-factor.*',
            'verification.*',
            'language.switch',
            'legacy-admin.*',
            'cashier.*',
            'storage.local',
            'storage.local.*',
            'profile.complete',
            'profile.complete.*',
            'logout',
        ],

        /*
        |----------------------------------------------------------------------
        | Authenticated — every logged-in user
        |----------------------------------------------------------------------
        */
        'authenticated' => [
            'dashboard',
            'app.profile',
            'app.profile.*',
            'app.notifications.*',
            'app.settings.*',
            'app.media.*',
            'app.media-library.*',
            'app.comments.*',
            'app.notes.*',
            'app.revisions.*',
            'app.ajax.*',
            'cms.*',
            'cache.clear',
        ],

        /*
        |----------------------------------------------------------------------
        | Users management — requires view_users permission
        |----------------------------------------------------------------------
        */
        'users' => [
            'app.users.*',
        ],

        /*
        |----------------------------------------------------------------------
        | Roles management — requires view_roles permission
        |----------------------------------------------------------------------
        */
        'roles' => [
            'app.roles.*',
        ],

        /*
        |----------------------------------------------------------------------
        | Masters — modules, settings, addresses, email, laravel-tools, queue
        |----------------------------------------------------------------------
        */
        'masters' => [
            'app.masters.modules.*',
            'app.masters.settings.*',
            'app.masters.addresses.*',
            'app.masters.email.*',
            'app.masters.laravel-tools.*',
            'app.masters.queue-monitor.*',
        ],

        /*
        |----------------------------------------------------------------------
        | Logs — activity logs, login attempts, not-found logs, log viewer
        |----------------------------------------------------------------------
        */
        'logs' => [
            'app.logs.*',
            'log-viewer.*',
        ],

        /*
        |----------------------------------------------------------------------
        | Broadcast notifications — super user only
        |----------------------------------------------------------------------
        */
        'broadcast' => [
            'app.notifications.broadcast.*',
        ],
    ],
];
