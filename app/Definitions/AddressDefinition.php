<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Http\Requests\AddressRequest;
use App\Models\Address;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

/**
 * AddressDefinition - Scaffold definition for Address entity
 *
 * Defines all configuration for the Address CRUD:
 * - Columns for DataGrid
 * - Filters for search/filtering
 * - Actions (row + bulk)
 * - Status tabs
 * - Routes and permissions
 */
class AddressDefinition extends ScaffoldDefinition
{
    // Route uses dot notation, can't auto-derive
    protected string $routePrefix = 'app.masters.addresses';

    // Different from route prefix
    protected string $permissionPrefix = 'addresses';

    protected bool $requiresSuperUserAccess = true;

    // Addresses don't have a status field - they use soft deletes
    protected ?string $statusField = null;

    /**
     * Get the model class for this scaffold
     */
    public function getModelClass(): string
    {
        return Address::class;
    }

    public function getRequestClass(): ?string
    {
        return AddressRequest::class;
    }

    /**
     * Define table columns for DataGrid
     */
    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('full_name')
                ->label('Name')
                ->sortable('first_name') // Sort on actual DB column
                ->searchable(['first_name', 'last_name']) // Search on actual DB columns
                ->link('show_url') // Link to show page
                ->width('250px'),

            Column::make('type')
                ->label('Type')
                ->sortable()
                ->template('type_badge'),

            Column::make('city')
                ->label('City')
                ->sortable()
                ->searchable(),

            Column::make('state')
                ->label('State')
                ->sortable(),

            Column::make('country_code')
                ->label('Country')
                ->sortable(),

            Column::make('created_at')
                ->label('Created')
                ->sortable(),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    /**
     * Define available filters for DataGrid
     */
    public function filters(): array
    {
        return [
            Filter::select('type')
                ->label('Type')
                ->options([
                    'home' => 'Home',
                    'work' => 'Work',
                    'billing' => 'Billing',
                    'shipping' => 'Shipping',
                    'other' => 'Other',
                ])
                ->placeholder('All Types'),

            Filter::select('country_code')
                ->label('Country')
                ->placeholder('All Countries'),

            Filter::boolean('is_primary')
                ->label('Primary Only'),

            Filter::boolean('is_verified')
                ->label('Verified Only'),

            Filter::dateRange('created_at')
                ->label('Created Date'),
        ];
    }

    /**
     * Define status tabs (using soft deletes instead of status field)
     */
    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-list-check')
                ->color('primary')
                ->default(),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    /**
     * Enable notes for addresses
     */
    public function hasNotes(): bool
    {
        return true;
    }
}
