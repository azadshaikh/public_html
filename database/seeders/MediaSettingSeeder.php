<?php

// Azad: See if this is required to not

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MediaSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $auditUserId = DB::table('users')->orderBy('id')->value('id');

        $mediaSettings = [
            [
                'key' => 'media_allowed_file_types',
                'value' => 'image/png,image/jpg,image/jpeg,image/gif,image/webp,image/svg+xml,image/x-icon,image/bmp,video/mp4,video/webm,video/avi,video/mov,video/wmv,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'media_max_upload_size',
                'value' => '10',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'media_generate_thumbnail',
                'value' => '1',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'media_auto_delete_trashed',
                'value' => '1',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'media_delete_trashed_days',
                'value' => '30',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($mediaSettings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    ...$setting,
                    'created_by' => $auditUserId,
                    'updated_by' => $auditUserId,
                ]
            );
        }
    }
}
