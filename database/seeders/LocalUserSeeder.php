<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalUserSeeder extends Seeder
{
    /**
     * Seed a local-only verified active user.
     */
    public function run(): void
    {
        $user = User::query()->updateOrCreate(['email' => 'su@astero.in'], [
            'name' => 'Super User',
            'password' => 'PassWord@1234',
            'active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole(Role::SUPER_USER);
    }
}
