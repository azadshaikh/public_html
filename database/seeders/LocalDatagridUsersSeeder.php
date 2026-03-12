<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LocalDatagridUsersSeeder extends Seeder
{
    public const USER_COUNT = 256;

    /**
     * Seed local-only users for datagrid testing.
     */
    public function run(): void
    {
        $roles = DB::table('roles')
            ->where('name', '!=', 'super_user')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($roles === []) {
            return;
        }

        $existingUserIds = DB::table('users')
            ->where('email', 'like', 'datagrid-user-%@example.test')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($existingUserIds !== []) {
            DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('model_id', $existingUserIds)
                ->delete();

            DB::table('users')->whereIn('id', $existingUserIds)->delete();
        }

        $now = now();
        $password = Hash::make('PassWord@1234');
        $rows = collect(range(1, self::USER_COUNT))->map(function (int $number) use ($now, $password): array {
            $index = $number - 1;

            return [
                'name' => sprintf('Datagrid User %03d', $number),
                'first_name' => 'Datagrid',
                'last_name' => sprintf('User %03d', $number),
                'username' => sprintf('datagrid-user-%03d', $number),
                'email' => sprintf('datagrid-user-%03d@example.test', $number),
                'password' => $password,
                'status' => $index % 4 === 0 ? 'inactive' : 'active',
                'email_verified_at' => $index % 5 === 0
                    ? null
                    : fake()->dateTimeBetween('-1 year'),
                'notifications_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        foreach ($rows->chunk(100) as $chunk) {
            DB::table('users')->insert($chunk->all());
        }

        $users = DB::table('users')
            ->select('id', 'email')
            ->where('email', 'like', 'datagrid-user-%@example.test')
            ->orderBy('email')
            ->get();

        $pivotRows = $users->values()->flatMap(function ($user, int $index) use ($roles): array {
            $roleIds = $roles;
            shuffle($roleIds);

            $roleCount = count($roleIds) > 1 && $index % 6 === 0 ? 2 : 1;

            return collect(array_slice($roleIds, 0, min($roleCount, count($roleIds))))
                ->map(fn (int $roleId): array => [
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $user->id,
                ])
                ->all();
        });

        foreach ($pivotRows->chunk(200) as $chunk) {
            DB::table('model_has_roles')->insert($chunk->all());
        }
    }
}
