<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests;

use App\Enums\Status;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Customers\Definitions\CustomerDefinition;
use Modules\Customers\Enums\AnnualRevenue;
use Modules\Customers\Enums\CustomerGroup;
use Modules\Customers\Enums\CustomerSource;
use Modules\Customers\Enums\CustomerTier;
use Modules\Customers\Enums\Industry;
use Modules\Customers\Enums\OrganizationSize;
use Modules\Customers\Models\Customer;

class CustomerRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $userAction = $this->input('user_action', 'none');
        $customerId = $this->route('customer');
        $customerId = $customerId instanceof Customer ? $customerId->id : $customerId;

        $emailRules = [
            'required',
            'email',
            'max:255',
            $this->uniqueRule('email')->withoutTrashed(),
        ];

        if ($userAction === 'create') {
            $emailRules[] = Rule::unique('users', 'email');
        }

        return [
            'type' => ['required', 'string', 'in:person,company'],
            'user_action' => ['nullable', 'string', 'in:none,keep,create,associate'],
            'user_id' => [
                Rule::requiredIf($userAction === 'associate'),
                'nullable',
                'integer',
                $this->existsRule('users'),
                Rule::unique('customers_customers', 'user_id')
                    ->ignore($customerId)
                    ->whereNull('deleted_at'),
            ],
            'user_password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'account_manager_id' => ['nullable', 'integer', $this->existsRule('users')],

            'company_name' => ['nullable', 'string', 'max:255', 'required_if:type,company'],
            'contact_first_name' => ['nullable', 'string', 'max:150', 'required_if:type,person'],
            'contact_last_name' => ['nullable', 'string', 'max:150'],

            'email' => $emailRules,
            'phone' => ['required', 'string', 'max:50'],
            'phone_code' => ['nullable', 'string', 'max:10'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'billing_phone' => ['nullable', 'string', 'max:50'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],

            'currency' => ['nullable', 'string', 'max:10'],
            'language' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'org_size' => ['nullable', Rule::enum(OrganizationSize::class)],
            'revenue' => ['nullable', Rule::enum(AnnualRevenue::class)],
            'logo' => ['nullable', 'integer', 'exists:media,id'],

            'status' => ['required', 'string', Rule::enum(Status::class)],
            'source' => ['nullable', 'string', Rule::enum(CustomerSource::class)],
            'tier' => ['nullable', 'string', Rule::enum(CustomerTier::class)],
            'industry' => ['nullable', 'string', Rule::enum(Industry::class)],
            'customer_group' => ['nullable', 'string', Rule::enum(CustomerGroup::class)],

            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],

            'opt_in_marketing' => ['boolean'],
            'do_not_call' => ['boolean'],
            'do_not_email' => ['boolean'],
            'next_action_date' => ['nullable', 'date'],

            'metadata' => ['nullable', 'array'],
            'additional_emails' => ['nullable', 'array'],
            'additional_emails.*' => ['email'],
            'additional_phones' => ['nullable', 'array'],
            'additional_phones.*' => ['string', 'max:50'],
            'social_links' => ['nullable', 'array'],
            'social_links.twitter_url' => ['nullable', 'url', 'max:255'],
            'social_links.facebook_url' => ['nullable', 'url', 'max:255'],
            'social_links.instagram_url' => ['nullable', 'url', 'max:255'],
            'social_links.linkedin_url' => ['nullable', 'url', 'max:255'],

            'addresses' => ['nullable', 'array'],
            'addresses.*.type' => ['required_with:addresses', 'string'],
            'addresses.*.is_primary' => ['sometimes', 'boolean'],
            'addresses.*.address1' => ['nullable', 'string', 'max:255'],
            'addresses.*.address2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:120'],
            'addresses.*.city_code' => ['nullable', 'string', 'max:50'],
            'addresses.*.state' => ['nullable', 'string', 'max:120'],
            'addresses.*.state_code' => ['nullable', 'string', 'max:50'],
            'addresses.*.zip' => ['nullable', 'string', 'max:30'],
            'addresses.*.country' => ['nullable', 'string', 'max:120'],
            'addresses.*.country_code' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'The email address is already in use.',
            'status.in' => 'Select a valid status option.',
            'company_name.required_without' => 'Please provide a Company Name or a Contact First Name.',
            'contact_first_name.required_without' => 'Please provide a Contact First Name if no Company Name is entered.',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new CustomerDefinition;
    }

    protected function prepareForValidation(): void
    {
        $tags = $this->input('tags');
        if (is_string($tags) && $tags !== '') {
            $this->merge([
                'tags' => array_values(array_filter(array_map(trim(...), explode(',', $tags)))),
            ]);
        }

        if (! $this->has('user_action')) {
            $customer = $this->route('customer');
            $hasLinkedUser = $customer instanceof Customer && $customer->user_id !== null;

            $this->merge([
                'user_action' => $this->filled('user_id') ? 'associate' : ($hasLinkedUser ? 'keep' : 'none'),
            ]);
        }
    }
}
