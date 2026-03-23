<?php

namespace Modules\Customers\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Customers\Models\Customer;
use Modules\Customers\Models\CustomerContact;

class CustomerContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        Customer::query()
            ->take(10)
            ->get()
            ->each(function (Customer $customer): void {
                CustomerContact::factory()
                    ->count(2)
                    ->create([
                        'customer_id' => $customer->id,
                    ]);
            });
    }
}
