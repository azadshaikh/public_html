<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Order Number Configuration Options
    |--------------------------------------------------------------------------
    */
    'order_digit_length_options' => [
        ['label' => '3 (e.g. 001)', 'value' => 3],
        ['label' => '4 (e.g. 0001)', 'value' => 4],
        ['label' => '5 (e.g. 00001)', 'value' => 5],
        ['label' => '6 (e.g. 000001)', 'value' => 6],
    ],

    'order_format_options' => [
        ['value' => 'date_sequence',       'label' => 'Date + Sequence',        'example' => 'ORD-20260226-0001'],
        ['value' => 'year_sequence',       'label' => 'Year + Sequence',        'example' => 'ORD-2026-0001'],
        ['value' => 'year_month_sequence', 'label' => 'Year-Month + Sequence',  'example' => 'ORD-202602-0001'],
        ['value' => 'sequence_only',       'label' => 'Sequence Only',          'example' => 'ORD-0001'],
    ],
];
