<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LocalUserSeeder extends Seeder
{
    /**
     * Seed a local-only verified active user.
     */
    public function run(): void
    {
        $now = now();
        $existingUserId = DB::table('users')
            ->where('email', 'su@astero.in')
            ->orWhere('username', 'superuser')
            ->value('id');

        $payload = [
            'name' => 'Super User',
            'first_name' => 'Super',
            'last_name' => 'User',
            'username' => 'superuser',
            'email' => 'su@astero.in',
            'password' => Hash::make('PassWord@1234'),
            'status' => 'active',
            'email_verified_at' => $now,
            'notifications_enabled' => true,
            'updated_at' => $now,
        ];

        if ($existingUserId) {
            DB::table('users')->where('id', $existingUserId)->update($payload);
            $userId = (int) $existingUserId;
        } else {
            $userId = (int) DB::table('users')->insertGetId([
                ...$payload,
                'created_at' => $now,
            ]);
        }

        $superUserRoleId = DB::table('roles')->where('name', 'super_user')->value('id');

        if ($superUserRoleId === null) {
            return;
        }

        DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', $userId)
            ->delete();

        DB::table('model_has_roles')->insert([
            'role_id' => $superUserRoleId,
            'model_type' => 'App\\Models\\User',
            'model_id' => $userId,
        ]);
    }
}
