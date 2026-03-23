<?php

namespace Modules\Helpdesk\Database\Seeders;

use App\Models\Settings;
use Illuminate\Database\Seeder;

class HelpdeskSettingsSeeder extends Seeder
{
    /**
     * Seed the helpdesk settings into the shared settings table.
     *
     * Uses the same flat-key convention as the master SettingsController:
     *   key = "{group}_{setting_name}"
     */
    public function run(): void
    {
        $defaults = [
            ['key' => 'helpdesk_ticket_prefix', 'value' => 'TK', 'type' => 'string'],
            ['key' => 'helpdesk_ticket_serial_number', 'value' => '11', 'type' => 'integer'],
            ['key' => 'helpdesk_ticket_digit_length', 'value' => '4', 'type' => 'integer'],
        ];

        foreach ($defaults as $setting) {
            Settings::query()->firstOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type'],
                ]
            );
        }
    }
}
