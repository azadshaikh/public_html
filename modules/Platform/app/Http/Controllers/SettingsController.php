<?php

namespace Modules\Platform\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Settings;
use App\Traits\ActivityTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\Platform\Models\Server;

class SettingsController extends Controller
{
    use ActivityTrait;

    private const string MODULE_PATH = 'platform::settings';

    public function settings(): View
    {
        abort_unless(Auth::user()->can('manage_platform_settings'), 403);

        $view_data = $this->getViewData('settings');

        return view(self::MODULE_PATH.'.settings', $view_data);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_platform_settings'), 403);

        $section = $request->input('section', 'general');

        $validated = $request->validate([
            'trail_server_id' => ['required', 'integer'],
            'default_server_group' => ['required', 'integer'],
            'default_sub_domain' => ['required', 'string', 'max:255'],
            'default_domain_ssl_key' => ['required', 'string'],
            'default_domain_ssl_crt' => ['required', 'string'],
            'default_ssl_expiry' => ['required', 'date'],
        ], [
            'trail_server_id.required' => 'Trial Server is required',
            'default_server_group.required' => 'Server Group is required',
            'default_sub_domain.required' => 'Trial Domain is required',
            'default_domain_ssl_key.required' => 'Domain SSL Key is required',
            'default_domain_ssl_crt.required' => 'Domain SSL Certificate is required',
            'default_ssl_expiry.required' => 'Default SSL Expiry is required',
            'default_ssl_expiry.date' => 'Default SSL Expiry must be a date',
        ]);

        // Capture previous values for activity log
        $previousValues = [];
        foreach (array_keys($validated) as $key) {
            $settingKey = 'platform_'.$key;
            $setting = Settings::query()->where('key', $settingKey)->first();
            $previousValues[$key] = $setting?->value;
        }

        // Update settings
        foreach ($validated as $key => $val) {
            $settingKey = 'platform_'.$key;

            if (! empty($val)) {
                Settings::query()->updateOrCreate(['key' => $settingKey], [
                    'value' => $val,
                    'updated_by' => Auth::id(),
                ]);
            } else {
                Settings::query()->where('key', $settingKey)->delete();
            }
        }

        // Clear cache
        Cache::forget('platform_settings');
        Artisan::call('optimize:clear');

        // Log activity
        $settingsModel = new Settings;
        $settingsModel->id = 0;

        $this->logActivityWithPreviousValues(
            $settingsModel,
            ActivityAction::UPDATE,
            'Platform settings updated',
            $previousValues,
            [
                'module' => 'Platform',
                'changed_fields' => $this->getChangedFields($previousValues, $validated),
                'new_values' => $validated,
                'changes' => $this->buildChanges($previousValues, $validated),
            ]
        );

        return to_route('platform.settings.index', ['section' => $section])
            ->with('success', 'Settings updated successfully')
            ->with('platform_settings_values', $validated);
    }

    private function getViewData(string $action): array
    {
        $settingData = Cache::rememberForever('platform_settings', fn () => Settings::query()->where('key', 'like', 'platform_%')
            ->pluck('value', 'key')
            ->toArray());

        return [
            'module_title' => __('platform::platform.settings'),
            'module_name' => __('platform::platform.settings'),
            'module_path' => self::MODULE_PATH,
            'parent_module' => __('platform::platform.platform'),
            'action' => $action,
            'page_title' => __('platform::platform.manage_settings'),
            'servers_options' => Server::getServerOptions(),
            'server_groups_options' => Group::getGroupOptions('server'),
            'setting_data' => $settingData,
        ];
    }

    private function getChangedFields(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (($old[$key] ?? null) !== $value) {
                $changes[] = $key;
            }
        }

        return $changes;
    }

    private function buildChanges(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (($old[$key] ?? null) !== $value) {
                $changes[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }
}
