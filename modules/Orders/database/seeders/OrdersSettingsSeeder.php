<?php

declare(strict_types=1);

namespace Modules\Orders\Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class OrdersSettingsSeeder extends Seeder
{
    /**
     * Seed default order settings.
     * Uses create-if-not-exists to avoid overwriting user-configured values.
     */
    public function run(): void
    {
        $defaults = [
            ['key' => 'orders_order_prefix',        'value' => 'ORD-',                 'type' => 'string'],
            ['key' => 'orders_order_serial_number',  'value' => '1',                    'type' => 'integer'],
            ['key' => 'orders_order_digit_length',   'value' => '4',                    'type' => 'integer'],
            ['key' => 'orders_order_format',         'value' => 'year_month_sequence',  'type' => 'string'],
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
