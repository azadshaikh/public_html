<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Definitions;

use App\Scaffold\Column;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\AIRegistry\Http\Requests\AiProviderRequest;
use Modules\AIRegistry\Models\AiProvider;

class AiProviderDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'ai-registry.providers';

    protected string $permissionPrefix = 'ai_providers';

    protected ?string $statusField = null;

    public function getModelClass(): string
    {
        return AiProvider::class;
    }

    public function getRequestClass(): ?string
    {
        return AiProviderRequest::class;
    }

    // ================================================================
    // DATAGRID COLUMNS
    // ================================================================

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('name')
                ->label('Provider')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('180px'),

            Column::make('capabilities')
                ->label('Capabilities')
                ->template('badge')
                ->width('300px'),

            Column::make('models_count')
                ->label('Models')
                ->width('80px'),

            Column::make('is_active')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('100px'),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    // ================================================================
    // DATAGRID FILTERS
    // ================================================================

    public function filters(): array
    {
        return [];
    }

    // ================================================================
    // STATUS TABS
    // ================================================================

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-list-check')
                ->color('primary')
                ->default(),

            StatusTab::make('active')
                ->label('Active')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value('active'),

            StatusTab::make('inactive')
                ->label('Inactive')
                ->icon('ri-pause-circle-line')
                ->color('warning')
                ->value('inactive'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    // ================================================================
    // VIEW CONFIGURATION
    // ================================================================

    public function getViewPath(): string
    {
        return 'airegistry::providers';
    }
}
