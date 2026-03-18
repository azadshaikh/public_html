<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\AddressDefinition;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Scaffold\ScaffoldController;
use App\Services\AddressService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AddressController - Scaffold-based controller for Address CRUD
 *
 * Extends ScaffoldController for standard CRUD with DataGrid API.
 * Only custom behavior needs to be defined here.
 */
class AddressController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly AddressService $addressService
    ) {}

    /**
     * Middleware for permission control
     */
    public static function middleware(): array
    {
        return (new AddressDefinition)->getMiddleware();
    }

    /**
     * Get the service instance (only required method)
     */
    protected function service(): AddressService
    {
        return $this->addressService;
    }

    /**
     * Display a listing of addresses.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $perPage = $this->addressService->getScaffoldDefinition()->getPerPage();

        return Inertia::render($this->inertiaPage().'/index', [
            'config' => $this->addressService->getScaffoldDefinition()->toInertiaConfig(),
            'addresses' => $this->addressService->getPaginatedAddresses($request),
            'statistics' => $this->addressService->getStatistics(),
            'filters' => [
                'search' => $request->input('search', ''),
                'type' => $request->input('type', ''),
                'is_primary' => $request->input('is_primary', ''),
                'created_at' => $request->input('created_at', ''),
                'status' => $status,
                'sort' => $request->input('sort', 'created_at'),
                'direction' => $request->input('direction', 'desc'),
                'per_page' => (int) $request->input('per_page', $perPage),
                'view' => $request->input('view', 'table'),
            ],
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    protected function inertiaPage(): string
    {
        return 'masters/addresses';
    }

    /**
     * Get additional data for create/edit forms.
     *
     * Countries, states, and cities are loaded asynchronously by the
     * geo select components via the JSON geo API endpoints.
     */
    protected function getFormViewData(Model $model): array
    {
        return [
            'initialValues' => [
                'first_name' => (string) ($model->getAttribute('first_name') ?? ''),
                'last_name' => (string) ($model->getAttribute('last_name') ?? ''),
                'company' => (string) ($model->getAttribute('company') ?? ''),
                'type' => (string) ($model->getAttribute('type') ?? 'home'),
                'address1' => (string) ($model->getAttribute('address1') ?? ''),
                'address2' => (string) ($model->getAttribute('address2') ?? ''),
                'address3' => (string) ($model->getAttribute('address3') ?? ''),
                'country' => (string) ($model->getAttribute('country') ?? ''),
                'country_code' => (string) ($model->getAttribute('country_code') ?? ''),
                'state' => (string) ($model->getAttribute('state') ?? ''),
                'state_code' => (string) ($model->getAttribute('state_code') ?? ''),
                'city' => (string) ($model->getAttribute('city') ?? ''),
                'city_code' => (string) ($model->getAttribute('city_code') ?? ''),
                'zip' => (string) ($model->getAttribute('zip') ?? ''),
                'phone' => (string) ($model->getAttribute('phone') ?? ''),
                'phone_code' => (string) ($model->getAttribute('phone_code') ?? ''),
                'latitude' => (string) ($model->getAttribute('latitude') ?? ''),
                'longitude' => (string) ($model->getAttribute('longitude') ?? ''),
                'is_primary' => (bool) ($model->getAttribute('is_primary') ?? false),
                'is_verified' => (bool) ($model->getAttribute('is_verified') ?? false),
                'addressable_type' => (string) ($model->getAttribute('addressable_type') ?? ''),
                'addressable_id' => (string) ($model->getAttribute('addressable_id') ?? ''),
            ],
            'typeOptions' => $this->addressService->getTypeOptions(),
        ];
    }

    /**
     * Transform a model for the show page using AddressResource.
     */
    protected function transformModelForShow(Model $model): array
    {
        return (new AddressResource($model))->resolve();
    }

    /**
     * Transform a model for the edit page header (display data).
     */
    protected function transformModelForEdit(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'full_name' => $model->getAttribute('full_name'),
            'type' => $model->getAttribute('type'),
            'city' => $model->getAttribute('city'),
            'country_code' => $model->getAttribute('country_code'),
        ];
    }

    /**
     * Handle creation side effects
     */
    protected function handleCreationSideEffects(Model $model): void
    {
        if (! $model instanceof Address) {
            return;
        }

        // If this is marked as primary, unmark other primary addresses for same addressable
        if ($model->is_primary && $model->addressable_type && $model->addressable_id) {
            Address::query()->where('addressable_type', $model->addressable_type)
                ->where('addressable_id', $model->addressable_id)
                ->where('id', '!=', $model->id)
                ->update(['is_primary' => false]);
        }
    }

    /**
     * Handle update side effects
     */
    protected function handleUpdateSideEffects(Model $model): void
    {
        if (! $model instanceof Address) {
            return;
        }

        // If this is marked as primary, unmark other primary addresses for same addressable
        if ($model->is_primary && $model->addressable_type && $model->addressable_id) {
            Address::query()->where('addressable_type', $model->addressable_type)
                ->where('addressable_id', $model->addressable_id)
                ->where('id', '!=', $model->id)
                ->update(['is_primary' => false]);
        }
    }
}
