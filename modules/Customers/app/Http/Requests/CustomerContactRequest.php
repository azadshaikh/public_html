<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Modules\Customers\Definitions\CustomerContactDefinition;

class CustomerContactRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', $this->existsRule('customers_customers')],
            'user_id' => ['nullable', 'integer', $this->existsRule('users')],
            'first_name' => ['required', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', $this->uniqueRule('email')->withoutTrashed()],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'position' => ['nullable', 'string', 'max:150'],
            'is_primary' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'metadata' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Select a customer for this contact.',
            'email.unique' => 'The email address is already in use.',
            'status.in' => 'Select a valid status option.',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new CustomerContactDefinition;
    }
}
