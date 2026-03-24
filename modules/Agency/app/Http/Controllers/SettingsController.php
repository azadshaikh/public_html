<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\RecacheApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    public function settings(): Response|RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_agency_settings'), 401);

        if (! request()->has('section')) {
            return to_route('agency.admin.settings.index', ['section' => 'general']);
        }

        return Inertia::render('agency/settings/index', [
            'section' => request('section', 'general'),
            'settings' => [
                'free_subdomain' => (string) config('agency.free_subdomain', ''),
                'platform_api_url' => (string) config('agency.platform_api_url', ''),
                'has_agency_secret_key' => filled(config('agency.agency_secret_key')),
            ],
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_agency_settings'), 401);

        $request->validate([
            'free_subdomain' => ['required', 'string', 'max:253', 'regex:/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?)*$/'],
        ], [
            'free_subdomain.required' => 'Free subdomain domain is required.',
            'free_subdomain.regex' => 'Free subdomain must be a valid domain name (e.g. sites.example.com).',
        ]);

        set_env_value('AGENCY_FREE_SUBDOMAIN', $request->free_subdomain, false);

        try {
            Artisan::call('config:clear');
        } catch (Throwable $throwable) {
            Log::warning('Failed to clear config cache after Agency general settings update.', ['exception' => $throwable]);
        }

        dispatch(new RecacheApplication('Agency general settings updated'));

        return to_route('agency.admin.settings.index', ['section' => 'general'])
            ->with('success', 'General settings updated successfully.');
    }

    public function updatePlatform(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_agency_settings'), 401);

        $request->validate([
            'platform_api_url' => ['nullable', 'string', 'url', 'max:255'],
            'agency_secret_key' => ['nullable', 'string', 'max:255'],
        ], [
            'platform_api_url.url' => 'Platform API URL must be a valid URL.',
        ]);

        if (filled($request->platform_api_url)) {
            set_env_value('PLATFORM_API_URL', $request->platform_api_url, false);
        }

        if (filled($request->agency_secret_key)) {
            set_env_value('AGENCY_SECRET_KEY', $request->agency_secret_key, false);
        }

        try {
            Artisan::call('config:clear');
        } catch (Throwable $throwable) {
            Log::warning('Failed to clear config cache after Agency platform settings update.', ['exception' => $throwable]);
        }

        dispatch(new RecacheApplication('Agency platform settings updated'));

        return to_route('agency.admin.settings.index', ['section' => 'platform'])
            ->with('success', 'Platform settings updated successfully.');
    }
}
