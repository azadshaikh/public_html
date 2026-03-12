<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $auditUserId = DB::table('users')->orderBy('id')->value('id');

        $settings = [
            // Login security settings (keys stored with login_security_ prefix in database)
            ['group' => null, 'key' => 'login_security_admin_login_url_slug', 'value' => 'admin', 'type' => 'string'],
            ['group' => null, 'key' => 'login_security_limit_login_attempts_enabled', 'value' => 'true', 'type' => 'string'],
            ['group' => null, 'key' => 'login_security_limit_login_attempts', 'value' => '5', 'type' => 'string'],
            ['group' => null, 'key' => 'login_security_lockout_time', 'value' => '60', 'type' => 'string'],

            // Localization settings (keys stored with localization_ prefix in database)
            ['group' => null, 'key' => 'localization_language', 'value' => 'en', 'type' => 'string'],
            ['group' => null, 'key' => 'localization_timezone', 'value' => 'Asia/Kolkata', 'type' => 'string'],
            ['group' => null, 'key' => 'localization_date_format', 'value' => 'Y/m/d', 'type' => 'string'],
            ['group' => null, 'key' => 'localization_time_format', 'value' => 'g:i a', 'type' => 'string'],

            // Social authentication is env-only — no DB rows needed.

            // SEO settings
        ];

        foreach ($settings as $settingData) {
            DB::table('settings')->updateOrInsert(
                ['key' => $settingData['key']],
                [
                    'group' => $settingData['group'],
                    'value' => $settingData['value'],
                    'type' => $settingData['type'],
                    'created_by' => $auditUserId,
                    'updated_by' => $auditUserId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
