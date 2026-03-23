<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Models\Tax;

class TaxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxes = [
            // US Federal (none, but placeholder)
            // US State Taxes
            [
                'name' => 'California Sales Tax',
                'code' => 'CA-ST',
                'description' => 'California state sales tax',
                'rate' => 7.25,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'US',
                'state' => 'CA',
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'name' => 'New York Sales Tax',
                'code' => 'NY-ST',
                'description' => 'New York state sales tax',
                'rate' => 4.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'US',
                'state' => 'NY',
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'name' => 'Texas Sales Tax',
                'code' => 'TX-ST',
                'description' => 'Texas state sales tax',
                'rate' => 6.25,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'US',
                'state' => 'TX',
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'name' => 'Florida Sales Tax',
                'code' => 'FL-ST',
                'description' => 'Florida state sales tax',
                'rate' => 6.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'US',
                'state' => 'FL',
                'is_active' => true,
                'priority' => 1,
            ],
            // Canada
            [
                'name' => 'Canadian GST',
                'code' => 'CA-GST',
                'description' => 'Canadian Goods and Services Tax',
                'rate' => 5.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'CA',
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'name' => 'Ontario HST',
                'code' => 'CA-ON-HST',
                'description' => 'Ontario Harmonized Sales Tax',
                'rate' => 13.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'CA',
                'state' => 'ON',
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'name' => 'British Columbia PST',
                'code' => 'CA-BC-PST',
                'description' => 'British Columbia Provincial Sales Tax',
                'rate' => 7.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'CA',
                'state' => 'BC',
                'is_active' => true,
                'priority' => 2,
                'is_compound' => true,
            ],
            // UK
            [
                'name' => 'UK VAT Standard',
                'code' => 'GB-VAT',
                'description' => 'UK Value Added Tax - Standard Rate',
                'rate' => 20.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'GB',
                'is_active' => true,
                'priority' => 1,
            ],
            // EU
            [
                'name' => 'German VAT',
                'code' => 'DE-VAT',
                'description' => 'German Value Added Tax (Mehrwertsteuer)',
                'rate' => 19.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'DE',
                'is_active' => true,
                'priority' => 1,
            ],
            [
                'name' => 'French VAT',
                'code' => 'FR-VAT',
                'description' => 'French Value Added Tax (TVA)',
                'rate' => 20.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'FR',
                'is_active' => true,
                'priority' => 1,
            ],
            // Australia
            [
                'name' => 'Australian GST',
                'code' => 'AU-GST',
                'description' => 'Australian Goods and Services Tax',
                'rate' => 10.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'AU',
                'is_active' => true,
                'priority' => 1,
            ],
            // India
            [
                'name' => 'India GST 18%',
                'code' => 'IN-GST-18',
                'description' => 'India Goods and Services Tax - Standard Rate',
                'rate' => 18.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'IN',
                'is_active' => true,
                'priority' => 1,
            ],
            // Japan
            [
                'name' => 'Japan Consumption Tax',
                'code' => 'JP-CT',
                'description' => 'Japan Consumption Tax',
                'rate' => 10.00,
                'type' => Tax::TYPE_PERCENTAGE,
                'country' => 'JP',
                'is_active' => true,
                'priority' => 1,
            ],
        ];

        foreach ($taxes as $taxData) {
            Tax::query()->updateOrCreate(['code' => $taxData['code']], $taxData);
        }
    }
}
