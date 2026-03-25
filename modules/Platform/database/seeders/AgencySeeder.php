<?php

namespace Modules\Platform\Database\Seeders;

use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Server;

class AgencySeeder extends PlatformSeeder
{
    private const string DEMO_AGENCY_PRIMARY_SERVER_IP = '192.168.0.100';

    private const string LEGACY_AGENCY_PRIMARY_SERVER_IP = '192.168.0.150';

    public function run(): void
    {
        $ownerId = $this->resolveAuditUserId();

        if ($ownerId === null) {
            $this->writeWarning('No users found. Skipping Platform agency seed data.');

            return;
        }

        $auditColumns = $this->auditColumns($ownerId);

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
                ...$auditColumns,
            ],
        );

        if (! $agency->uid) {
            $agency->assignUid();
        }

        if (! $agency->secret_key) {
            $agency->generateSecretKey();
        }

        $this->syncPrimaryServer($agency, self::DEMO_AGENCY_PRIMARY_SERVER_IP);

        $this->writeInfo('Seeded Platform agency: '.$agency->name);

        /** @var Agency $legacyAgency */
        $legacyAgency = Agency::query()->updateOrCreate(
            ['email' => 'contact@breederspot.com'],
            [
                'name' => 'Breeder Spot LLC',
                'type' => 'premium',
                'plan' => 'reseller',
                'website_id_prefix' => 'BS',
                'website_id_zero_padding' => 4,
                'owner_id' => $ownerId,
                'status' => 'active',
                'webhook_url' => 'https://azadtwo.192.168.0.150.traefik.me/api/agency/v1/webhooks/platform',
                'metadata' => [
                    'branding_name' => 'BreederSpot',
                    'branding_logo' => 'https://breederspot.com/favicon/apple-touch-icon.png',
                    'branding_icon' => 'https://breederspot.com/favicon/apple-touch-icon.png',
                    'branding_website' => 'https://breederspot.com',
                ],
                ...$auditColumns,
            ],
        );

        if (! $legacyAgency->uid) {
            $legacyAgency->assignUid();
        }

        $legacyAgency->forceFill([
            'secret_key' => encrypt('UaXom83IHmbr85LQ1DoRRNGH6Lq4Zj1sfMzSp1g3wjHxWTeJlXe3zS9IwaEnCYaQ'),
        ])->save();

        $legacyAgency->saveAddress([
            'first_name' => 'Breeder Spot LLC',
            'company' => 'Breeder Spot LLC',
            'country' => 'United States',
            'country_code' => 'US',
            'type' => 'work',
            'is_primary' => true,
            'is_verified' => false,
        ], 'work');

        $this->syncPrimaryServer($legacyAgency, self::LEGACY_AGENCY_PRIMARY_SERVER_IP);

        $this->writeInfo('Seeded Platform agency: '.$legacyAgency->name);
    }

    private function syncPrimaryServer(Agency $agency, string $serverIp): void
    {
        /** @var Server|null $server */
        $server = Server::query()->where('ip', $serverIp)->first();

        if (! $server instanceof Server) {
            $this->writeWarning(
                sprintf(
                    'No Platform server found for agency %s at %s. Skipping server association.',
                    $agency->email,
                    $serverIp,
                ),
            );

            return;
        }

        $agency->servers()
            ->newPivotStatement()
            ->where('agency_id', $agency->getKey())
            ->update(['is_primary' => false]);

        $agency->servers()->syncWithoutDetaching([
            $server->getKey() => ['is_primary' => true],
        ]);
    }
}
