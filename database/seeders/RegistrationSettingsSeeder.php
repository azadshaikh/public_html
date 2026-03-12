<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegistrationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $auditUserId = DB::table('users')->orderBy('id')->value('id');
        $defaultRoleId = DB::table('roles')->where('name', 'customer')->value('id');

        $settings = [
            [
                'key' => 'registration_enable_registration',
                'value' => true,
                'type' => 'boolean',
            ],
            [
                'key' => 'registration_default_role',
                'value' => $defaultRoleId,
                'type' => 'integer',
            ],
            [
                'key' => 'registration_require_email_verification',
                'value' => true,
                'type' => 'boolean',
            ],
            [
                'key' => 'registration_auto_approve',
                'value' => true,
                'type' => 'boolean',
            ],
        ];

        foreach ($settings as $settingData) {
            DB::table('settings')->updateOrInsert(
                ['key' => $settingData['key']],
                [
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
