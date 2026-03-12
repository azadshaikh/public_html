<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $commonPassword = 'PassWord@1234';
        $now = now();

        // Always create Super User
        $users = [
            [
                'name' => 'Super User',
                'first_name' => 'Super',
                'last_name' => 'User',
                'username' => 'superuser',
                'email' => 'su@astero.in',
                'password' => $commonPassword,
                'password_confirmation' => $commonPassword,
                'status' => 'active',
                'roles' => [1], // super_user
                'email_verified' => true,
            ],
        ];

        // Add other users only in local environment
        if (app()->environment('local')) {
            $localUsers = [
                [
                    'name' => 'System Administrator',
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                    'username' => 'admin',
                    'email' => 'admin@example.com',
                    'password' => $commonPassword,
                    'password_confirmation' => $commonPassword,
                    'status' => 'active',
                    'roles' => [2], // administrator
                    'email_verified' => true,
                ],
                [
                    'name' => 'Operations Manager',
                    'first_name' => 'Operations',
                    'last_name' => 'Manager',
                    'username' => 'manager',
                    'email' => 'manager@example.com',
                    'password' => $commonPassword,
                    'password_confirmation' => $commonPassword,
                    'status' => 'active',
                    'roles' => [3], // manager
                    'email_verified' => true,
                ],
                [
                    'name' => 'Support Staff',
                    'first_name' => 'Support',
                    'last_name' => 'Staff',
                    'username' => 'staff',
                    'email' => 'staff@example.com',
                    'password' => $commonPassword,
                    'password_confirmation' => $commonPassword,
                    'status' => 'active',
                    'roles' => [5], // staff
                    'email_verified' => true,
                ],
                [
                    'name' => 'Primary Customer',
                    'first_name' => 'Primary',
                    'last_name' => 'Customer',
                    'username' => 'customer',
                    'email' => 'customer@example.com',
                    'password' => $commonPassword,
                    'password_confirmation' => $commonPassword,
                    'status' => 'active',
                    'roles' => [4], // customer
                    'email_verified' => true,
                ],
                [
                    'name' => 'Secondary Customer',
                    'first_name' => 'Secondary',
                    'last_name' => 'Customer',
                    'username' => 'customer2',
                    'email' => 'customer2@example.com',
                    'password' => $commonPassword,
                    'password_confirmation' => $commonPassword,
                    'status' => 'active',
                    'roles' => [4], // customer
                    'email_verified' => true,
                ],
                [
                    'name' => 'General User',
                    'first_name' => 'General',
                    'last_name' => 'User',
                    'username' => 'user',
                    'email' => 'user@example.com',
                    'password' => $commonPassword,
                    'password_confirmation' => $commonPassword,
                    'status' => 'active',
                    'roles' => [6], // user
                    'email_verified' => true,
                ],
            ];

            $users = array_merge($users, $localUsers);
        }

        foreach ($users as $userData) {
            $existingUserId = DB::table('users')
                ->where('email', $userData['email'])
                ->orWhere('username', $userData['username'])
                ->value('id');

            $payload = [
                'name' => $userData['name'],
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'status' => $userData['status'],
                'email_verified_at' => $userData['email_verified'] ? $now : null,
                'notifications_enabled' => true,
                'updated_at' => $now,
            ];

            if ($existingUserId) {
                DB::table('users')->where('id', $existingUserId)->update($payload);
                $userId = (int) $existingUserId;
                $action = 'Updated';
            } else {
                $userId = (int) DB::table('users')->insertGetId([
                    ...$payload,
                    'created_at' => $now,
                ]);
                $action = 'Created';
            }

            DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->delete();

            $pivotRows = collect($userData['roles'])
                ->map(fn ($roleId): array => [
                    'role_id' => (int) $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ])
                ->all();

            DB::table('model_has_roles')->insert($pivotRows);

            $roleNames = DB::table('roles')
                ->whereIn('id', $userData['roles'])
                ->pluck('name')
                ->implode(', ');

            $this->command->info(sprintf('%s user: %s (%s) with roles: %s', $action, $userData['email'], $userData['name'], $roleNames));
        }

        $this->command->info('User seeding completed successfully!');
    }
}
