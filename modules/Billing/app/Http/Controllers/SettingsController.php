<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\RecacheApplication;
use App\Models\Settings;
use App\Traits\ActivityTrait;
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
    use ActivityTrait;

    /**
     * Map of form field names to their settings table key and type.
     */
    private const array INVOICE_FIELDS = [
        'invoice_prefix' => ['key' => 'billing_invoice_prefix',       'type' => 'string'],
        'invoice_serial_number' => ['key' => 'billing_invoice_serial_number', 'type' => 'integer'],
        'invoice_digit_length' => ['key' => 'billing_invoice_digit_length', 'type' => 'integer'],
        'invoice_format' => ['key' => 'billing_invoice_format',       'type' => 'string'],
    ];

    public function settings(): Response|RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_billing_settings'), 401);

        if (! request()->has('section')) {
            return to_route('app.billing.settings.index', ['section' => 'invoice']);
        }

        $section = request()->get('section', 'invoice');

        return Inertia::render('billing/settings/index', [
            'section' => $section,
            'invoiceSettings' => [
                'invoice_prefix' => setting('billing_invoice_prefix', 'INV'),
                'invoice_serial_number' => (int) setting('billing_invoice_serial_number', 1),
                'invoice_digit_length' => (int) setting('billing_invoice_digit_length', 5),
                'invoice_format' => setting('billing_invoice_format', 'date_sequence'),
            ],
            'stripeSettings' => [
                'stripe_key' => config('cashier.key') ? '••••'.substr((string) config('cashier.key'), -4) : '',
                'stripe_secret' => config('cashier.secret') ? '••••'.substr((string) config('cashier.secret'), -4) : '',
                'stripe_webhook_secret' => config('cashier.webhook.secret') ? '••••'.substr((string) config('cashier.webhook.secret'), -4) : '',
            ],
            'invoiceDigitLengthOptions' => config('billing.invoice_digit_length_options', []),
            'invoiceFormatOptions' => config('billing.invoice_format_options', []),
        ]);
    }

    public function updateInvoicePrefix(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_billing_settings'), 401);

        $request->validate([
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'invoice_serial_number' => ['required', 'integer', 'min:1'],
            'invoice_digit_length' => ['required', 'integer'],
            'invoice_format' => ['required', 'string', 'in:date_sequence,year_sequence,year_month_sequence,sequence_only'],
        ], [
            'invoice_prefix.required' => 'Invoice Prefix is required',
            'invoice_serial_number.required' => 'Next Invoice Number is required',
            'invoice_digit_length.required' => 'Invoice Number Length is required',
            'invoice_format.required' => 'Invoice Number Format is required',
        ]);

        $previousValues = $this->getCurrentFieldValues(self::INVOICE_FIELDS);

        $newValues = [
            'invoice_prefix' => $request->invoice_prefix,
            'invoice_serial_number' => (int) $request->invoice_serial_number,
            'invoice_digit_length' => (int) $request->invoice_digit_length,
            'invoice_format' => $request->invoice_format,
        ];

        $userId = Auth::id();

        foreach (self::INVOICE_FIELDS as $field => $meta) {
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
            'Billing invoice prefix settings updated',
            $previousValues,
            [
                'module' => 'Billing',
                'section' => 'invoice',
                'changed_fields' => $this->getChangedFields($previousValues, $newValues),
            ]
        );

        return to_route('app.billing.settings.index', ['section' => 'invoice'])
            ->with('success', 'Invoice settings updated successfully.');
    }

    public function updateStripe(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->can('manage_billing_settings'), 401);

        $request->validate([
            'stripe_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        // Only write non-empty fields — an empty submission means "leave unchanged".
        // Matches the same pattern used by Social Auth settings.
        if (filled($request->stripe_key)) {
            set_env_value('STRIPE_KEY', $request->stripe_key, false);
        }

        if (filled($request->stripe_secret)) {
            set_env_value('STRIPE_SECRET', $request->stripe_secret, false);
        }

        if (filled($request->stripe_webhook_secret)) {
            set_env_value('STRIPE_WEBHOOK_SECRET', $request->stripe_webhook_secret, false);
        }

        // Clear the config cache synchronously so the redirect reads fresh .env values,
        // then dispatch an async job to rebuild it — same pattern as social auth settings.
        try {
            Artisan::call('config:clear');
        } catch (Throwable $throwable) {
            Log::warning('Failed to clear config cache after Stripe settings update.', ['exception' => $throwable]);
        }

        dispatch(new RecacheApplication('Billing stripe settings updated'));

        return to_route('app.billing.settings.index', ['section' => 'stripe'])
            ->with('success', 'Stripe settings updated successfully.');
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
