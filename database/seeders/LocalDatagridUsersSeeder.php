<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class LocalDatagridUsersSeeder extends Seeder
{
    public const USER_COUNT = 256;

    /**
     * Seed local-only users for datagrid testing.
     */
    public function run(): void
    {
        $roles = Role::query()
            ->where('name', '!=', Role::SUPER_USER)
            ->get();

        if ($roles->isEmpty()) {
            return;
        }

        User::query()
            ->where('email', 'like', 'datagrid-user-%@example.test')
            ->get()
            ->each(function (User $user): void {
                $user->syncRoles([]);
                $user->delete();
            });

        $users = User::factory()
            ->count(self::USER_COUNT)
            ->sequence(fn (Sequence $sequence): array => [
                'email' => sprintf('datagrid-user-%03d@example.test', $sequence->index + 1),
                'active' => $sequence->index % 4 !== 0,
                'email_verified_at' => $sequence->index % 5 === 0
                    ? null
                    : fake()->dateTimeBetween('-1 year'),
            ])
            ->create();

        $users->values()->each(function (User $user, int $index) use ($roles): void {
            $roleCount = $roles->count() > 1 && $index % 6 === 0 ? 2 : 1;

            $user->syncRoles(
                $roles->shuffle()->take(min($roleCount, $roles->count())),
            );
        });
    }
}
