<?php

declare(strict_types=1);

namespace App\Http\Controllers\Masters;

use App\Definitions\AddressDefinition;
use App\Models\Address;
use App\Scaffold\ScaffoldController;
use App\Services\AddressService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controllers\HasMiddleware;

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

    protected function inertiaPage(): string
    {
        return 'masters/addresses';
    }

    /**
     * Get additional data for create/edit forms
     */
    protected function getFormViewData(Model $model): array
    {
        return [
            'typeOptions' => $this->addressService->getTypeOptions(),
            'countryOptions' => $this->addressService->getCountryOptions(),
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
