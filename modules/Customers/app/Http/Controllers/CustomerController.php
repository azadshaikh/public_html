<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers;

use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Customers\Definitions\CustomerDefinition;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerService;

class CustomerController extends ScaffoldController implements HasMiddleware
{
    public function __construct(private readonly CustomerService $customerService) {}

    public static function middleware(): array
    {
        return (new CustomerDefinition)->getMiddleware();
    }

    public function show(int|string $id): Response
    {
        $this->enforcePermission('view');

        /** @var Customer $customer */
        $customer = $this->findModel((int) $id);
        $customer->load([
            'contacts',
            'addresses',
            'user:id,first_name,last_name,email,status,email_verified_at',
            'accountManager:id,first_name,last_name,email',
        ]);

        $activities = ActivityLog::query()
            ->forModel(Customer::class, $customer->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return Inertia::render($this->inertiaPage().'/show', [
            'customer' => $this->transformCustomerForShow($customer),
            'customerSummary' => $this->customerService->getCustomerSummary($customer->id),
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->id,
                'description' => $activity->description,
                'causer_name' => $activity->causer?->name ?? 'System',
                'created_at' => $activity->created_at?->diffForHumans(),
            ])->all(),
        ]);
    }

    protected function service(): CustomerService
    {
        return $this->customerService;
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Customer $customer */
        $customer = $model;

        return [
            'initialValues' => [
                'type' => (string) ($customer->type ?? 'company'),
                'company_name' => (string) ($customer->company_name ?? ''),
                'contact_first_name' => (string) ($customer->contact_first_name ?? ''),
                'contact_last_name' => (string) ($customer->contact_last_name ?? ''),
                'email' => (string) ($customer->email ?? ''),
                'phone' => (string) ($customer->phone ?? ''),
                'phone_code' => (string) ($customer->phone_code ?? ''),
                'billing_email' => (string) ($customer->billing_email ?? ''),
                'billing_phone' => (string) ($customer->billing_phone ?? ''),
                'tax_id' => (string) ($customer->tax_id ?? ''),
                'website' => (string) ($customer->website ?? ''),
                'description' => (string) ($customer->description ?? ''),
                'status' => (string) ($customer->status?->value ?? 'active'),
                'source' => (string) ($customer->source?->value ?? ''),
                'tier' => (string) ($customer->tier?->value ?? 'bronze'),
                'customer_group' => (string) ($customer->customer_group?->value ?? ''),
                'industry' => (string) ($customer->industry?->value ?? ''),
                'org_size' => (string) ($customer->org_size?->value ?? ''),
                'revenue' => (string) ($customer->revenue?->value ?? ''),
                'account_manager_id' => $customer->account_manager_id ? (string) $customer->account_manager_id : '',
                'currency' => (string) ($customer->currency ?? ''),
                'language' => (string) ($customer->language ?? ''),
                'tags' => (string) (is_array($customer->tags) ? implode(', ', $customer->tags) : ($customer->tags ?? '')),
                'opt_in_marketing' => (bool) ($customer->opt_in_marketing ?? false),
                'do_not_call' => (bool) ($customer->do_not_call ?? false),
                'do_not_email' => (bool) ($customer->do_not_email ?? false),
                'next_action_date' => $customer->next_action_date?->format('Y-m-d') ?? '',
                'user_action' => 'none',
                'user_id' => $customer->user_id ? (string) $customer->user_id : '',
                'user_password' => '',
                'user_password_confirmation' => '',
            ],
            'typeOptions' => $this->customerService->getTypeOptions(),
            'statusOptions' => $this->customerService->getStatusOptions(),
            'sourceOptions' => $this->customerService->getSourceOptions(),
            'tierOptions' => $this->customerService->getTierOptions(),
            'groupOptions' => $this->customerService->getGroupOptions(),
            'industryOptions' => $this->customerService->getIndustryOptions(),
            'accountManagerOptions' => $this->customerService->getAccountManagerOptions(),
            'languageOptions' => $this->customerService->getLanguageOptions(),
            'orgSizeOptions' => $this->customerService->getOrgSizeOptions(),
            'annualRevenueOptions' => $this->customerService->getAnnualRevenueOptions(),
            'userOptions' => $this->customerService->getUserAccountOptions($customer->exists ? $customer : null),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Customer $model */
        return [
            'id' => $model->id,
            'name' => $model->company_name_display,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformCustomerForShow(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'unique_id' => $customer->unique_id,
            'type' => $customer->type,
            'company_name' => $customer->company_name,
            'company_name_display' => $customer->company_name_display,
            'contact_first_name' => $customer->contact_first_name,
            'contact_last_name' => $customer->contact_last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'phone_code' => $customer->phone_code,
            'billing_email' => $customer->billing_email,
            'billing_phone' => $customer->billing_phone,
            'website' => $customer->website,
            'tax_id' => $customer->tax_id,
            'description' => $customer->description,
            'status' => $customer->status?->value,
            'status_label' => $customer->status_label,
            'tier' => $customer->tier?->value,
            'tier_label' => $customer->tier?->label(),
            'source' => $customer->source?->value,
            'source_label' => $customer->source?->label(),
            'customer_group' => $customer->customer_group?->value,
            'customer_group_label' => $customer->customer_group?->label(),
            'industry_name' => $customer->industry?->name,
            'org_size' => $customer->org_size?->value,
            'org_size_label' => $customer->org_size?->label(),
            'revenue' => $customer->revenue?->value,
            'revenue_label' => $customer->revenue?->label(),
            'account_manager_name' => $customer->accountManager
                ? $customer->accountManager->first_name.' '.$customer->accountManager->last_name
                : null,
            'user' => $customer->user ? [
                'id' => $customer->user->id,
                'name' => $customer->user->first_name.' '.$customer->user->last_name,
                'email' => $customer->user->email,
                'status' => $customer->user->status,
            ] : null,
            'contacts' => $customer->contacts->map(fn ($contact): array => [
                'id' => $contact->id,
                'full_name' => $contact->full_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'position' => $contact->position,
                'is_primary' => $contact->is_primary,
                'status' => $contact->status?->value,
            ])->all(),
            'addresses' => $customer->addresses->map(fn ($address): array => [
                'id' => $address->id,
                'type' => $address->type,
                'is_primary' => $address->is_primary,
                'address1' => $address->address1,
                'address2' => $address->address2,
                'city' => $address->city,
                'state' => $address->state,
                'zip' => $address->zip,
                'country' => $address->country,
            ])->all(),
            'tags' => $customer->tags,
            'opt_in_marketing' => $customer->opt_in_marketing,
            'do_not_call' => $customer->do_not_call,
            'do_not_email' => $customer->do_not_email,
            'language' => $customer->language,
            'currency' => $customer->currency,
            'last_contacted_at' => $customer->last_contacted_at?->diffForHumans(),
            'next_action_date' => $customer->next_action_date?->format('Y-m-d'),
            'created_at' => $customer->created_at?->format('M d, Y'),
            'updated_at' => $customer->updated_at?->format('M d, Y'),
            'deleted_at' => $customer->deleted_at?->format('M d, Y'),
            'is_trashed' => $customer->trashed(),
        ];
    }
}
