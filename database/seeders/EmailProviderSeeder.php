<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmailProviderSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {
        $auditUserId = DB::table('users')->orderBy('id')->value('id');

        $providers = [
            0 => [
                'id' => 3,
                'name' => 'AWS - Production',
                'description' => 'AWS SES Production Details.',
                'sender_name' => 'Astero',
                'sender_email' => 'no-reply@astero.in',
                'smtp_host' => 'email-smtp.us-east-1.amazonaws.com',
                'smtp_user' => 'AKIATSDQQRACYBGSRVPC',
                'smtp_password' => 'BJJVQh+RBAYmSh9Mq71BRg9sv5lwHUCWWlJxQA3T7XsS',
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
                'status' => 'active',
                'reply_to' => null,
                'bcc' => 'mohd.azad.shaikh@gmail.com',
                'signature' => '<p>Regards,</p>
                    <p><strong>Astero Team.</strong></p>',
                'created_by' => 1,
                'updated_by' => 1,
                'deleted_by' => null,
                'created_at' => '2021-03-26 06:13:11',
                'updated_at' => '2023-07-19 16:48:33',
                'deleted_at' => null,
            ],
        ];

        $providers = collect($providers)
            ->map(function (array $provider) use ($auditUserId): array {
                if ($provider['smtp_encryption'] === 'tsl') {
                    $provider['smtp_encryption'] = 'tls';
                }

                $provider['status'] = strtolower($provider['status']);
                $provider['created_by'] = $auditUserId;
                $provider['updated_by'] = $auditUserId;
                $provider['deleted_by'] = null;

                return $provider;
            })
            ->all();

        DB::table('email_providers')->insertOrIgnore($providers);

        // Reset PostgreSQL sequence to avoid duplicate key errors on next insert
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('email_providers', 'id'), COALESCE((SELECT MAX(id) FROM email_providers), 1))");
        }
    }
}
