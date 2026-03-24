<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\AIRegistry\Http\Requests\AiModelRequest;
use Modules\AIRegistry\Models\AiModel;

class AiModelDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'ai-registry.models';

    protected string $permissionPrefix = 'ai_models';

    protected ?string $statusField = null;

    public function getModelClass(): string
    {
        return AiModel::class;
    }

    public function getRequestClass(): ?string
    {
        return AiModelRequest::class;
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
                ->label('Model')
                ->sortable()
                ->searchable()
                ->link('edit_url')
                ->width('200px'),

            Column::make('provider_name')
                ->label('Provider')
                ->template('badge')
                ->width('130px'),

            Column::make('context_window_formatted')
                ->label('Context')
                ->sortable('context_window')
                ->width('90px'),

            Column::make('input_cost_per_1m')
                ->label('Input $/1M')
                ->sortable()
                ->width('100px'),

            Column::make('output_cost_per_1m')
                ->label('Output $/1M')
                ->sortable()
                ->width('100px'),

            Column::make('is_active')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('90px'),

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
        return [
            Filter::select('provider_id')
                ->label('Provider')
                ->options([])  // populated dynamically in service
                ->placeholder('All Providers'),

            Filter::select('capability')
                ->label('Capability')
                ->options([])
                ->placeholder('All Capabilities'),

            Filter::select('category')
                ->label('Category')
                ->options(
                    collect(config('airegistry::options.categories', []))
                        ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                        ->values()
                        ->all()
                )
                ->placeholder('All Categories'),

            Filter::boolean('is_moderated')
                ->label('Moderated')
                ->placeholder('All Moderation States'),
        ];
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
        return 'airegistry::models';
    }
}
