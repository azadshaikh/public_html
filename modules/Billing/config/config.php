<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Billing Module Configuration
    |--------------------------------------------------------------------------
    */

    'name' => 'Billing',

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */
    'default_currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    */
    'currencies' => [
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimals' => 2],
        'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'decimals' => 2],
        'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'decimals' => 2],
        'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'decimals' => 2],
        'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'decimals' => 2],
        'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'decimals' => 0],
        'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'decimals' => 2],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Settings
    |--------------------------------------------------------------------------
    */
    'invoice' => [
        'prefix' => 'INV-',
        'default_due_days' => 30,
        'number_format' => '{prefix}{date}{sequence}', // INV-202601290001
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Number Digit Length Options
    |--------------------------------------------------------------------------
    */
    'invoice_digit_length_options' => [
        ['label' => '3 (e.g. 001)', 'value' => 3],
        ['label' => '4 (e.g. 0001)', 'value' => 4],
        ['label' => '5 (e.g. 00001)', 'value' => 5],
        ['label' => '6 (e.g. 000001)', 'value' => 6],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Number Format Options
    |--------------------------------------------------------------------------
    */
    'invoice_format_options' => [
        ['value' => 'date_sequence',       'label' => 'Date + Sequence',        'example' => 'INV-202602260001'],
        ['value' => 'year_sequence',       'label' => 'Year + Sequence',        'example' => 'INV-2026-0001'],
        ['value' => 'year_month_sequence', 'label' => 'Year-Month + Sequence',  'example' => 'INV-202602-0001'],
        ['value' => 'sequence_only',       'label' => 'Sequential Only',        'example' => 'INV-0001'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Settings
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'prefix' => 'PAY-',
        'gateways' => [
            'stripe' => [
                'enabled' => true,
                'name' => 'Stripe',
            ],
            'manual' => [
                'enabled' => true,
                'name' => 'Manual Payment',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Settings
    |--------------------------------------------------------------------------
    */
    'tax' => [
        'enabled' => true,
        'inclusive' => false, // Whether prices include tax by default
        'compound' => false, // Whether to calculate tax on tax
    ],
];
