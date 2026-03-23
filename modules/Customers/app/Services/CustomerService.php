<?php

declare(strict_types=1);

namespace Modules\Customers\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Billing\Contracts\BillingAggregator;
use Modules\Customers\Contracts\BelongsToCustomer;
use Modules\Customers\Contracts\CustomerAggregator;
use Modules\Customers\Definitions\CustomerDefinition;
use Modules\Customers\Enums\AnnualRevenue;
use Modules\Customers\Enums\CustomerGroup;
use Modules\Customers\Enums\CustomerSource;
use Modules\Customers\Enums\CustomerTier;
use Modules\Customers\Enums\Industry;
use Modules\Customers\Enums\OrganizationSize;
use Modules\Customers\Http\Resources\CustomerResource;
use Modules\Customers\Models\Customer;
use Modules\Subscriptions\Contracts\Subscribable;
use Throwable;

class CustomerService implements BelongsToCustomer, CustomerAggregator, ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new CustomerDefinition;
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ];
    }

    public function getTypeOptions(): array
    {
        return [
            ['value' => 'company', 'label' => 'Company'],
            ['value' => 'person', 'label' => 'Person'],
        ];
    }

    public function getSourceOptions(): array
    {
        return array_map(
            fn (CustomerSource $case): array => ['value' => $case->value, 'label' => $case->label()],
            CustomerSource::cases()
        );
    }

    public function getTierOptions(): array
    {
        return array_map(
            fn (CustomerTier $case): array => ['value' => $case->value, 'label' => $case->label()],
            CustomerTier::cases()
        );
    }

    public function getOrgSizeOptions(): array
    {
        return array_map(
            fn (OrganizationSize $case): array => ['value' => $case->value, 'label' => $case->label()],
            OrganizationSize::cases()
        );
    }

    public function getAnnualRevenueOptions(): array
    {
        return array_map(
            fn (AnnualRevenue $case): array => ['value' => $case->value, 'label' => $case->label()],
            AnnualRevenue::cases()
        );
    }

    public function getGroupOptions(): array
    {
        return array_map(
            fn (CustomerGroup $case): array => ['value' => $case->value, 'label' => $case->label()],
            CustomerGroup::cases()
        );
    }

    public function getIndustryOptions(): array
    {
        return array_map(
            fn (Industry $case): array => ['value' => $case->value, 'label' => $case->label()],
            Industry::cases()
        );
    }

    public function getCustomerOptions(): array
    {
        return Customer::query()
            ->select('id', 'company_name', 'contact_first_name', 'contact_last_name', 'email')
            ->orderBy('company_name')
            ->get()
            ->map(fn (Customer $customer): array => [
                'value' => $customer->id,
                'label' => $customer->company_name
                    ?: $customer->contact_name
                    ?: $customer->email
                    ?: 'Customer #'.$customer->id,
            ])
            ->values()
            ->all();
    }

    public function getUserAccountOptions(?Customer $customer = null): array
    {
        $linkedUserIds = Customer::query()
            ->whereNotNull('user_id')
            ->when($customer?->user_id, fn ($query) => $query->where('user_id', '!=', $customer->user_id))
            ->pluck('user_id');

        $query = User::query()
            ->select('id', 'first_name', 'last_name', 'email')
            ->where('status', 'active')
            ->whereDoesntHave('roles', function ($q): void {
                $q->where('roles.id', User::superUserRoleId());
            })
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($linkedUserIds->isNotEmpty()) {
            $query->whereNotIn('id', $linkedUserIds);
        }

        return $query->get()
            ->map(function (User $user): array {
                $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                $label = $name !== '' && $name !== '0' ? $name : ($user->email ?: 'User #'.$user->id);

                if ($user->email && $label !== $user->email) {
                    $label .= ' — '.$user->email;
                }

                return [
                    'value' => $user->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->toArray();
    }

    public function getCustomer(int $customerId): ?Customer
    {
        return Customer::query()->find($customerId);
    }

    public function getCustomerId(?Customer $customer = null): ?int
    {
        return $customer?->id;
    }

    public function setCustomer(array $data, ?Customer $customer = null): Customer
    {
        $payload = $customer instanceof Customer
            ? $this->prepareUpdateData($data)
            : $this->prepareCreateData($data);

        if ($customer instanceof Customer) {
            $customer->update($payload);
        } else {
            $customer = Customer::query()->create($payload);
        }

        $this->saveAddressesForCustomer($customer, $data);
        $this->handleUserAccount($customer, $data);

        return $customer;
    }

    public function deleteCustomer(int $customerId): bool
    {
        $customer = $this->getCustomer($customerId);

        return $customer && (bool) $customer->delete();
    }

    public function getCustomerSummary(int $customerId): array
    {
        $customer = $this->getCustomer($customerId);

        if (! $customer instanceof Customer) {
            return [];
        }

        return [
            'customer' => [
                'id' => $customer->id,
                'unique_id' => $customer->unique_id,
                'type' => $customer->type,
                'company_name' => $customer->company_name,
                'contact_name' => $customer->contact_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status?->value,
                'tier' => $customer->tier?->value,
            ],
            'billing' => $this->getCustomerBillingSummary($customerId),
            'subscriptions' => $this->getCustomerSubscriptionSummary($customerId),
            'activity' => $this->getCustomerActivitySummary($customerId),
        ];
    }

    public function getCustomerBillingSummary(int $customerId): array
    {
        $fallback = [
            'total_spent' => 0,
            'outstanding_balance' => 0,
            'currency' => 'USD',
            'invoice_count' => 0,
            'last_payment_date' => null,
            'last_payment_amount' => null,
        ];

        return $this->resolveModuleSummary(BillingAggregator::class, 'getCustomerBillingSummary', $customerId, $fallback);
    }

    public function getCustomerSubscriptionSummary(int $customerId): array
    {
        $fallback = [
            'active_plans' => [],
            'renewal_date' => null,
            'status' => 'none',
        ];

        return $this->resolveModuleSummary(Subscribable::class, 'getCustomerSubscriptionSummary', $customerId, $fallback);
    }

    public function getCustomerActivitySummary(int $customerId): array
    {
        return [
            'activity_count' => 0,
            'last_activity_at' => null,
        ];
    }

    // -- Options Helpers --

    public function getAccountManagerOptions(): array
    {
        return User::query()
            ->select('id', 'first_name', 'last_name')
            ->active()
            ->whereHas('roles', fn ($q) => $q->where('name', 'Staff'))
            ->orderBy('first_name')
            ->get()
            ->map(fn ($user): array => [
                'value' => $user->id,
                'label' => trim($user->first_name.' '.$user->last_name),
            ])
            ->toArray();
    }

    public function getLanguageOptions(): array
    {
        return collect(config('constants.languages', []))
            ->map(fn (array $item, string $key): array => [
                'value' => $item['value'] ?? $key,
                'label' => $item['label'] ?? strtoupper($key),
            ])
            ->values()
            ->all();
    }

    protected function getResourceClass(): ?string
    {
        return CustomerResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'user:id,first_name,last_name,email,status,email_verified_at',
            'accountManager:id,first_name,last_name,email',
            'primaryAddress:id,addressable_id,addressable_type,phone,phone_code,country,country_code,city,state,zip,is_primary',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $query->withCount('contacts');
    }

    protected function prepareCreateData(array $data): array
    {
        return $this->preparePayload($data);
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->preparePayload($data);
    }

    protected function afterCreate(Model $model, array $data): void
    {
        if ($model instanceof Customer) {
            $this->saveAddressesForCustomer($model, $data);
            $this->handleUserAccount($model, $data);
        }
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        if ($model instanceof Customer) {
            $this->saveAddressesForCustomer($model, $data);
            $this->handleUserAccount($model, $data);
        }
    }

    private function saveAddressesForCustomer(Customer $customer, array $data): void
    {
        if (! isset($data['addresses']) || ! is_array($data['addresses'])) {
            return;
        }

        $addresses = $data['addresses'];
        foreach ($addresses as &$addr) {
            $addr['first_name'] ??= $data['contact_first_name'] ?? null;
            $addr['last_name'] ??= $data['contact_last_name'] ?? null;
            $addr['company'] ??= $data['company_name'] ?? null;
        }

        unset($addr);
        $customer->saveAddresses($addresses);
    }

    private function handleUserAccount(Customer $customer, array $data): void
    {
        $action = $this->resolveUserAction($data, $customer);

        if ($action === 'none') {
            if ($customer->user_id) {
                $customer->forceFill(['user_id' => null])->saveQuietly();
            }

            return;
        }

        if ($action === 'associate') {
            $userId = $data['user_id'] ?? null;

            if (! $userId) {
                return;
            }

            $user = User::query()->find($userId);

            if (! $user) {
                return;
            }

            $isNewLink = $customer->user_id !== $user->id;

            if ($isNewLink) {
                $customer->forceFill(['user_id' => $user->id])->saveQuietly();
            }

            $syncService = resolve(CustomerUserSyncService::class);
            $syncService->ensureCustomerRole($user);

            if ($isNewLink) {
                $syncService->syncCustomerFromUser($user, $customer);
            } else {
                $syncService->syncUserFromCustomer($customer, $user);
            }

            return;
        }

        if ($action === 'create') {
            $syncService = resolve(CustomerUserSyncService::class);
            $user = $syncService->createUserForCustomer($customer, $data);

            if (! $user) {
                return;
            }

            if ($customer->user_id !== $user->id) {
                $customer->forceFill(['user_id' => $user->id])->saveQuietly();
            }

            $syncService->syncUserFromCustomer($customer, $user);

            return;
        }

        if ($action === 'keep' && $customer->user_id) {
            resolve(CustomerUserSyncService::class)->syncUserFromCustomer($customer);
        }
    }

    private function resolveUserAction(array $data, Customer $customer): string
    {
        $action = $data['user_action'] ?? null;

        if (is_string($action) && $action !== '') {
            return $action;
        }

        if (array_key_exists('user_id', $data)) {
            return 'associate';
        }

        return $customer->user_id ? 'keep' : 'none';
    }

    private function preparePayload(array $data): array
    {
        // Metadata handling: merge existing with new specific fields
        $metaFields = ['additional_emails', 'additional_phones', 'social_links'];
        $metadata = $data['metadata'] ?? [];

        foreach ($metaFields as $field) {
            if (isset($data[$field])) {
                $metadata[$field] = $this->sanitizeArray($data[$field]);
            }
        }

        return [
            'user_id' => $data['user_id'] ?? null,
            'industry' => $data['industry'] ?? null,
            'customer_group' => $data['customer_group'] ?? null,
            'account_manager_id' => $data['account_manager_id'] ?? null,
            'type' => $data['type'] ?? 'company',
            // unique_id is auto-generated if null
            'company_name' => $data['company_name'] ?? null,
            'contact_first_name' => $data['contact_first_name'] ?? null,
            'contact_last_name' => $data['contact_last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone_code' => $data['phone_code'] ?? null,
            'billing_email' => $data['billing_email'] ?? null,
            'billing_phone' => $data['billing_phone'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'website' => $data['website'] ?? null,
            'logo' => $data['logo'] ?? null,
            'currency' => $data['currency'] ?? null,
            'language' => $data['language'] ?? 'en',
            'description' => $data['description'] ?? null,
            'org_size' => $data['org_size'] ?? null,
            'revenue' => $data['revenue'] ?? null,
            'status' => $data['status'] ?? 'active',
            'source' => $data['source'] ?? null,
            'tier' => $data['tier'] ?? 'bronze',
            'tags' => $this->sanitizeArray($data['tags'] ?? null),
            'opt_in_marketing' => $data['opt_in_marketing'] ?? false,
            'do_not_call' => $data['do_not_call'] ?? false,
            'do_not_email' => $data['do_not_email'] ?? false,
            'next_action_date' => $data['next_action_date'] ?? null,
            'metadata' => $metadata, // Updated metadata
        ];
    }

    /**
     * @param  array<int|string, mixed>|null  $values
     * @return array<int|string, mixed>|null
     */
    private function sanitizeArray(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $filtered = array_filter($values, fn ($value): bool => $value !== null && $value !== '');

        if ($filtered === []) {
            return null;
        }

        $keys = array_keys($filtered);
        $isSequential = $keys === range(0, count($keys) - 1);

        return $isSequential ? array_values($filtered) : $filtered;
    }

    private function resolveModuleSummary(string $contract, string $method, int $customerId, array $fallback): array
    {
        if (! interface_exists($contract)) {
            return $fallback;
        }

        if (! app()->bound($contract)) {
            return $fallback;
        }

        try {
            $service = resolve($contract);

            if (! method_exists($service, $method)) {
                return $fallback;
            }

            $result = $service->{$method}($customerId);

            return is_array($result) ? $result : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
