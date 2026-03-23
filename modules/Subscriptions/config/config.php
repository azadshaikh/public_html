<?php

use Modules\Customers\Models\Customer;

return [
    'name' => 'Subscriptions',

    /*
    |--------------------------------------------------------------------------
    | Customer Model
    |--------------------------------------------------------------------------
    |
    | The fully qualified class name of the customer model that will be used
    | for subscriptions. This allows flexible integration with any customer
    | or user model in your application.
    |
    */
    'customer_model' => Customer::class,

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency code to use for new subscriptions.
    |
    */
    'default_currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Default Grace Period
    |--------------------------------------------------------------------------
    |
    | The default number of days a subscription remains accessible after
    | cancellation before it fully expires.
    |
    */
    'default_grace_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Default Trial Period
    |--------------------------------------------------------------------------
    |
    | The default number of trial days for new subscriptions when not
    | specified by the plan.
    |
    */
    'default_trial_days' => 14,
];
