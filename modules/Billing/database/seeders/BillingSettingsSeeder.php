<?php

declare(strict_types=1);

namespace Modules\Billing\Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class BillingSettingsSeeder extends Seeder
{
    /**
     * Seed default billing settings.
     * Uses create-if-not-exists to avoid overwriting user-configured values.
     */
    public function run(): void
    {
        $defaults = [
            ['key' => 'billing_invoice_prefix',      'value' => 'INV-', 'type' => 'string'],
            ['key' => 'billing_invoice_serial_number', 'value' => '1',    'type' => 'integer'],
            ['key' => 'billing_invoice_digit_length', 'value' => '3',    'type' => 'integer'],
            ['key' => 'billing_invoice_format',       'value' => 'date_sequence', 'type' => 'string'],
        ];

        foreach ($defaults as $data) {
            $existing = Settings::query()->where('key', $data['key'])->first();

            if (! $existing) {
                Settings::query()->create([
                    'group' => null,
                    'key' => $data['key'],
                    'value' => $data['value'],
                    'type' => $data['type'],
                    'created_by' => 1,
                    'updated_by' => 1,
                ]);
            }
        }
    }
}
