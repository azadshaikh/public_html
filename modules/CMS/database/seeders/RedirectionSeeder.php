<?php

namespace Modules\CMS\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\CMS\Models\Redirection;

class RedirectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userId = User::query()->first()?->id ?? 1;

        Redirection::query()->create([
            'source_url' => '/go-to-google',
            'target_url' => 'https://www.google.com',
            'redirect_type' => 301,
            'url_type' => 'external',
            'match_type' => 'exact',
            'status' => 'active',
            'notes' => 'Example redirect to demonstrate external URL redirection',
            'hits' => 0,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
