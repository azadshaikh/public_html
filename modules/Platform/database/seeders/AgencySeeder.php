<?php

namespace Modules\Platform\Database\Seeders;

use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Server;

class AgencySeeder extends PlatformSeeder
{
    public function run(): void
    {
        $ownerId = $this->resolveAuditUserId();

        if ($ownerId === null) {
            $this->writeWarning('No users found. Skipping Platform agency seed data.');

            return;
        }

        /** @var Agency $agency */
        $agency = Agency::query()->updateOrCreate(
            ['email' => 'platform-demo-agency@example.test'],
            [
                'name' => 'Platform Demo Agency',
                'type' => 'premium',
                'plan' => 'reseller',
                'website_id_prefix' => 'PDA',
                'website_id_zero_padding' => 4,
                'owner_id' => $ownerId,
                'status' => 'active',
                'webhook_url' => 'https://platform-demo-agency.example.test/api/agency/v1/webhooks/platform',
                'metadata' => [
                    'branding_name' => 'Platform Demo Agency',
                    'branding_logo' => 'https://platform-demo-agency.example.test/logo.svg',
                    'branding_icon' => 'https://platform-demo-agency.example.test/icon.svg',
                    'branding_website' => 'https://platform-demo-agency.example.test',
                ],
                ...$this->auditColumns($ownerId),
            ],
        );

        if (! $agency->uid) {
            $agency->assignUid();
        }

        if (! $agency->secret_key) {
            $agency->generateSecretKey();
        }

        /** @var Server|null $primaryServer */
        $primaryServer = Server::query()->orderBy('id')->first();

        if ($primaryServer instanceof Server) {
            $agency->servers()->syncWithoutDetaching([
                $primaryServer->getKey() => ['is_primary' => true],
            ]);
        }

        $this->writeInfo('Seeded Platform agency: '.$agency->name);
    }
}
