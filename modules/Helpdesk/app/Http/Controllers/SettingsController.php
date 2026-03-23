<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Traits\ActivityTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Helpdesk\Http\Requests\HelpdeskSettingsRequest;

class SettingsController extends Controller implements HasMiddleware
{
    use ActivityTrait;

    /**
     * Map of form field names to their settings table key and type.
     */
    private const array SETTING_FIELDS = [
        'ticket_prefix' => ['key' => 'helpdesk_ticket_prefix', 'type' => 'string'],
        'ticket_serial_number' => ['key' => 'helpdesk_ticket_serial_number', 'type' => 'integer'],
        'ticket_digit_length' => ['key' => 'helpdesk_ticket_digit_length', 'type' => 'integer'],
    ];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:manage_helpdesk_settings'),
        ];
    }

    public function settings(): Response
    {
        return Inertia::render('helpdesk/settings/index', $this->buildPageData());
    }

    public function updateSettings(HelpdeskSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Capture previous values for activity log
        $previousValues = $this->getCurrentSettingValues();

        $newValues = [
            'ticket_prefix' => (string) $validated['ticket_prefix'],
            'ticket_serial_number' => (string) $validated['ticket_serial_number'],
            'ticket_digit_length' => (string) $validated['ticket_digit_length'],
        ];

        // Persist each setting to the database
        $userId = $request->user()?->getAuthIdentifier();

        foreach (self::SETTING_FIELDS as $field => $meta) {
            Settings::query()->updateOrCreate(
                ['key' => $meta['key']],
                [
                    'value' => (string) $newValues[$field],
                    'type' => $meta['type'],
                    'updated_by' => $userId,
                ]
            );
        }

        // Bust the settings cache so subsequent reads see new values
        settings_cache()->refresh();

        // Log settings update with previous values
        $settingsModel = new Settings;
        $settingsModel->id = 0;

        $this->logActivityWithPreviousValues(
            $settingsModel,
            ActivityAction::UPDATE,
            'Helpdesk settings updated',
            $previousValues,
            [
                'module' => 'Helpdesk',
                'changed_fields' => $this->getChangedHelpdeskFields($previousValues, $newValues),
                'new_values' => $newValues,
                'changes' => $this->buildHelpdeskChanges($previousValues, $newValues),
            ]
        );

        return to_route('helpdesk.settings.index')
            ->with('success', 'Settings updated successfully.');
    }

    private function buildPageData(): array
    {
        return [
            'initialValues' => $this->getCurrentSettingValues(),
            'ticket_length_options' => config('helpdesk.ticket_length_options'),
        ];
    }

    /**
     * Read the current helpdesk settings from the database (via cache).
     */
    private function getCurrentSettingValues(): array
    {
        $values = [];

        foreach (self::SETTING_FIELDS as $field => $meta) {
            $values[$field] = (string) setting($meta['key'], match ($field) {
                'ticket_prefix' => 'TK',
                'ticket_serial_number' => '1',
                'ticket_digit_length' => '4',
            });
        }

        return $values;
    }

    private function getChangedHelpdeskFields(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (($old[$key] ?? null) !== $value) {
                $changes[] = $key;
            }
        }

        return $changes;
    }

    private function buildHelpdeskChanges(array $old, array $new): array
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
