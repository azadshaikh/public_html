<?php

namespace Modules\Customers\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Customers\Models\Customer;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Customer::factory()
            ->count(10)
            ->create();
    }
}
