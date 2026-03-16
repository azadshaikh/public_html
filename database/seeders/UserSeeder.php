<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Exception;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(UserService $UserService): void
    {
        $commonPassword = 'PassWord@1234';

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
            try {
                // Check if user already exists
                $existingUser = User::query()->where('email', $userData['email'])
                    ->orWhere('username', $userData['username'])
                    ->first();

                if ($existingUser) {
                    // Update existing user's roles
                    $roleIds = array_map(intval(...), $userData['roles']);
                    $roles = Role::query()->whereIn('id', $roleIds)->get();
                    $existingUser->syncRoles($roles);

                    $updateData = [
                        'username' => $userData['username'],
                        'email' => $userData['email'], // Ensure email is updated
                        'status' => $userData['status'],
                        'name' => $userData['name'],
                        'first_name' => $userData['first_name'],
                        'last_name' => $userData['last_name'],
                    ];

                    // Seed data always marks these users as verified.
                    $updateData['email_verified_at'] = now();

                    $existingUser->update($updateData);

                    $roleNames = $existingUser->roles->pluck('name')->implode(', ');
                    $this->command->info(sprintf('Updated existing user: %s (%s) with roles: %s', $userData['email'], $userData['name'], $roleNames));
                } else {
                    // Create new user
                    $user = $UserService->createUser($userData);
                    $roleNames = $user->roles->pluck('name')->implode(', ');
                    $this->command->info(sprintf('Created user: %s (%s) with roles: %s', $userData['email'], $userData['name'], $roleNames));
                }
            } catch (Exception $e) {
                // $this->command->error("Failed to process user {$userData['email']}: {$e->getMessage()}");
                // Fallback to error logging if command is not available (e.g. if run outside console, though Seeder is usually console)
                $this->command->error(sprintf('Failed to process user %s: %s', $userData['email'], $e->getMessage()));
            }
        }

        $this->command->info('User seeding completed successfully!');
    }
}
