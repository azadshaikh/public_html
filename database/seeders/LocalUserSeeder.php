<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class LocalUserSeeder extends Seeder
{
    /**
     * Seed a local-only verified active user.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'su@astero.in'],
            [
                'name' => 'Local User',
                'password' => 'PassWord@1234',
                'active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
