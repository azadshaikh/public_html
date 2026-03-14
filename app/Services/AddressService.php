<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\AddressDefinition;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Http\Request;

/**
 * AddressService - Service for Address CRUD operations
 *
 * Uses Scaffoldable trait for standard CRUD + DataGrid API.
 * The base Scaffoldable::buildListQuery() handles:
 *   - Trash status (onlyTrashed() for ?status=trash)
 *   - Eager loading, search, filters, sorting
 *
 * Custom methods can be added as needed.
 */
class AddressService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function __construct(
        private readonly GeoDataService $geoDataService
    ) {}

    /**
     * Get the scaffold definition for this service.
     */
    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new AddressDefinition;
    }

    /**
     * Override statistics to handle soft deletes (no status field)
     *
     * Since Address uses soft deletes without a status column,
     * we override to provide only total and trash counts.
     */
    public function getStatistics(): array
    {
        return [
            'total' => Address::query()->count(),
            'trash' => Address::onlyTrashed()->count(),
        ];
    }

    // Note: prepareUpdateData() not overridden - base trait returns $data unchanged

    /**
     * Get available address types as an indexed array of {value, label} objects.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getTypeOptions(): array
    {
        return [
            ['value' => 'home', 'label' => 'Home'],
            ['value' => 'work', 'label' => 'Work'],
            ['value' => 'billing', 'label' => 'Billing'],
            ['value' => 'shipping', 'label' => 'Shipping'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }

    /**
     * Get all countries as select options via GeoDataService.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getCountryOptions(): array
    {
        $countries = $this->geoDataService->getAllCountries();

        return array_map(fn (array $country): array => [
            'value' => $country['iso2'],
            'label' => $country['name'],
        ], $countries);
    }

    /**
     * Get addresses as a PaginatedData-compatible array for Inertia index pages.
     *
     * @return array<string, mixed>
     */
    public function getPaginatedAddresses(Request $request): array
    {
        $query = $this->buildListQuery($request);
        $paginator = $query->paginate($this->getPerPage($request))->onEachSide(1);

        $paginatedArray = $paginator->toArray();
        $paginatedArray['data'] = AddressResource::collection($paginator->items())->resolve(request());

        return $paginatedArray;
    }

    /**
     * Get resource class for JSON transformation
     */
    protected function getResourceClass(): ?string
    {
        return AddressResource::class;
    }

    /**
     * Get relationships to eager load
     */
    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    // =========================================================================
    // Note: We do NOT override buildListQuery() or applyFilters() here.
    // The base Scaffoldable trait handles:
    //   - Trash status detection from query/route params
    //   - Filter application based on AddressDefinition::filters()
    //   - Search, sorting, eager loading
    // Use customizeListQuery() for any Address-specific query logic.
    //
    // Also: prepareUpdateData() returns $data unchanged, so no need to override.
    // Only override prepareCreateData() when you need to set defaults.
    // =========================================================================

    /**
     * Prepare data for creation
     */
    protected function prepareCreateData(array $data): array
    {
        // Set defaults for polymorphic relationship
        // When creating addresses from the admin CRUD, associate with current user
        $data['addressable_type'] ??= User::class;
        $data['addressable_id'] ??= auth()->id();

        // Set defaults
        $data['is_primary'] ??= false;
        $data['is_verified'] ??= false;

        return $data;
    }
}
