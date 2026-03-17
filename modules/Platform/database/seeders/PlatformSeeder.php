<?php

namespace Modules\Platform\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

abstract class PlatformSeeder extends Seeder
{
    protected function resolveAuditUserId(): ?int
    {
        return User::query()->orderBy('id')->value('id');
    }

    /**
     * @return array{created_by?: int, updated_by?: int}
     */
    protected function auditColumns(?int $userId = null): array
    {
        $auditUserId = $userId ?? $this->resolveAuditUserId();

        if ($auditUserId === null) {
            return [];
        }

        return [
            'created_by' => $auditUserId,
            'updated_by' => $auditUserId,
        ];
    }

    protected function writeInfo(string $message): void
    {
        if ($this->command !== null) {
            $this->command->info($message);
        }
    }

    protected function writeWarning(string $message): void
    {
        if ($this->command !== null) {
            $this->command->warn($message);
        }
    }
}
