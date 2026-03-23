<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Customers\Definitions\CustomerContactDefinition;
use Modules\Customers\Models\CustomerContact;
use Modules\Customers\Services\CustomerContactService;
use Modules\Customers\Services\CustomerService;

class CustomerContactController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly CustomerContactService $customerContactService,
        private readonly CustomerService $customerService
    ) {}

    public static function middleware(): array
    {
        return (new CustomerContactDefinition)->getMiddleware();
    }

    protected function service(): CustomerContactService
    {
        return $this->customerContactService;
    }

    public function show(int|string $id): Response
    {
        $this->enforcePermission('view');

        /** @var CustomerContact $contact */
        $contact = $this->findModel($id);
        $contact->load(['customer:id,company_name,type,contact_first_name,contact_last_name']);

        return Inertia::render($this->inertiaPage().'/show', [
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'full_name' => $contact->full_name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'phone_code' => $contact->phone_code,
                'position' => $contact->position,
                'is_primary' => $contact->is_primary,
                'status' => $contact->status?->value,
                'status_label' => $contact->status_label,
                'customer_name' => $contact->customer?->company_name,
                'customer_id' => $contact->customer_id,
                'created_at' => $contact->created_at?->format('M d, Y'),
                'updated_at' => $contact->updated_at?->format('M d, Y'),
                'deleted_at' => $contact->deleted_at?->format('M d, Y'),
                'is_trashed' => $contact->trashed(),
            ],
        ]);
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var CustomerContact $contact */
        $contact = $model;

        return [
            'initialValues' => [
                'customer_id' => $contact->customer_id ? (string) $contact->customer_id : '',
                'first_name' => (string) ($contact->first_name ?? ''),
                'last_name' => (string) ($contact->last_name ?? ''),
                'email' => (string) ($contact->email ?? ''),
                'phone' => (string) ($contact->phone ?? ''),
                'phone_code' => (string) ($contact->phone_code ?? ''),
                'position' => (string) ($contact->position ?? ''),
                'is_primary' => (bool) ($contact->is_primary ?? false),
                'status' => (string) ($contact->status?->value ?? 'active'),
            ],
            'statusOptions' => $this->customerService->getStatusOptions(),
            'customerOptions' => $this->customerService->getCustomerOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var CustomerContact $contact */
        $contact = $model;

        return [
            'id' => $contact->id,
            'name' => $contact->full_name,
        ];
    }
}
