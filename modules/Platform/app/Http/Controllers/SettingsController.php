<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Traits\ActivityTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\Http\Requests\UpdatePlatformSettingsRequest;
use Modules\Platform\Models\Server;

class SettingsController extends Controller
{
    use ActivityTrait;

    public function settings(): Response
    {
        abort_unless(Auth::user()->can('manage_platform_settings'), 403);

        return Inertia::render('platform/settings/index', $this->getPageData());
    }

    public function updateSettings(UpdatePlatformSettingsRequest $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_platform_settings'), 403);

        $section = $request->input('section', 'general');
        $validated = $request->validated();

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
            ->with('success', 'Settings updated successfully');
    }

    private function getPageData(): array
    {
        $settingData = Cache::rememberForever('platform_settings', fn () => Settings::query()->where('key', 'like', 'platform_%')
            ->pluck('value', 'key')
            ->toArray());

        return [
            'initialValues' => [
                'trail_server_id' => isset($settingData['platform_trail_server_id'])
                    ? (int) $settingData['platform_trail_server_id']
                    : null,
                'default_sub_domain' => (string) ($settingData['platform_default_sub_domain'] ?? ''),
                'default_domain_ssl_key' => (string) ($settingData['platform_default_domain_ssl_key'] ?? ''),
                'default_domain_ssl_crt' => (string) ($settingData['platform_default_domain_ssl_crt'] ?? ''),
                'default_ssl_expiry' => (string) ($settingData['platform_default_ssl_expiry'] ?? ''),
            ],
            'serverOptions' => Server::getServerOptions(),
            'settingsNav' => [
                [
                    'slug' => 'general',
                    'label' => __('platform::platform.general_settings'),
                    'href' => route('platform.settings.index'),
                ],
            ],
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
