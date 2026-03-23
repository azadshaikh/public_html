<?php

declare(strict_types=1);

namespace Modules\Orders\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Settings;
use App\Traits\ActivityTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use ActivityTrait;

    private const string MODULE_PATH = 'orders::settings';

    /**
     * Map of form field names to their settings table key and type.
     */
    private const array ORDER_NUMBER_FIELDS = [
        'order_prefix' => ['key' => 'orders_order_prefix',        'type' => 'string'],
        'order_serial_number' => ['key' => 'orders_order_serial_number', 'type' => 'integer'],
        'order_digit_length' => ['key' => 'orders_order_digit_length',  'type' => 'integer'],
        'order_format' => ['key' => 'orders_order_format',        'type' => 'string'],
    ];

    public function settings(): Response
    {
        abort_unless(Auth::user()->can('manage_orders_settings'), 401);

        return Inertia::render('orders/settings/index', [
            'initialValues' => [
                'order_prefix' => setting('orders_order_prefix', 'ORD'),
                'order_serial_number' => (int) setting('orders_order_serial_number', 1),
                'order_digit_length' => (int) setting('orders_order_digit_length', 4),
                'order_format' => setting('orders_order_format', 'date_sequence'),
            ],
            'digitLengthOptions' => config('orders.order_digit_length_options'),
            'formatOptions' => config('orders.order_format_options'),
        ]);
    }

    public function updateOrderNumber(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_orders_settings'), 401);

        $request->validate([
            'order_prefix' => ['required', 'string', 'max:20'],
            'order_serial_number' => ['required', 'integer', 'min:1'],
            'order_digit_length' => ['required', 'integer'],
            'order_format' => ['required', 'string', 'in:date_sequence,year_sequence,year_month_sequence,sequence_only'],
        ], [
            'order_prefix.required' => 'Order Prefix is required',
            'order_serial_number.required' => 'Next Order Number is required',
            'order_digit_length.required' => 'Order Number Length is required',
            'order_format.required' => 'Order Number Format is required',
        ]);

        $previousValues = $this->getCurrentFieldValues(self::ORDER_NUMBER_FIELDS);

        $newValues = [
            'order_prefix' => $request->order_prefix,
            'order_serial_number' => (int) $request->order_serial_number,
            'order_digit_length' => (int) $request->order_digit_length,
            'order_format' => $request->order_format,
        ];

        $userId = Auth::id();

        foreach (self::ORDER_NUMBER_FIELDS as $field => $meta) {
            Settings::query()->updateOrCreate(
                ['key' => $meta['key']],
                [
                    'value' => (string) $newValues[$field],
                    'type' => $meta['type'],
                    'updated_by' => $userId,
                ]
            );
        }

        settings_cache()->refresh();

        $settingsModel = new Settings;
        $settingsModel->id = 0;

        $this->logActivityWithPreviousValues(
            $settingsModel,
            ActivityAction::UPDATE,
            'Orders order number settings updated',
            $previousValues,
            [
                'module' => 'Orders',
                'section' => 'order-number',
                'changed_fields' => $this->getChangedFields($previousValues, $newValues),
            ]
        );

        return to_route('app.orders.settings.index')
            ->with('success', 'Order number settings updated successfully.');
    }

    /**
     * @param  array<string, array{key: string, type: string}>  $fields
     * @return array<string, mixed>
     */
    private function getCurrentFieldValues(array $fields): array
    {
        $values = [];

        foreach ($fields as $field => $meta) {
            $values[$field] = setting($meta['key']);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<int, string>
     */
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
}
