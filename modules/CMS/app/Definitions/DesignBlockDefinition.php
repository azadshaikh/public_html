<?php

declare(strict_types=1);

namespace Modules\CMS\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\CMS\Http\Requests\DesignBlockRequest;
use Modules\CMS\Models\DesignBlock;

class DesignBlockDefinition extends ScaffoldDefinition
{
    protected string $entityName = 'Design Block';

    protected string $entityPlural = 'Design Blocks';

    protected string $routePrefix = 'cms.designblock';

    protected string $permissionPrefix = 'design_blocks';

    protected ?string $inertiaPagePrefix = 'cms/design-blocks';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return DesignBlock::class;
    }

    public function getRequestClass(): ?string
    {
        return DesignBlockRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('title')
                ->label('Design Block')
                ->sortable()
                ->searchable(['title', 'slug', 'excerpt'])
                ->link('edit_url')
                ->width('340px'),

            Column::make('design_type')
                ->label('Design Type')
                ->template('badge')
                ->sortable('metadata->design_type')
                ->width('140px'),

            Column::make('category_name')
                ->label('Category')
                ->sortable('metadata->category')
                ->width('160px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('created_at')
                ->label('Created')
                ->sortable()
                ->width('140px'),

            Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport()->width('80px'),
        ];
    }

    public function actions(): array
    {
        $defaults = collect($this->defaultActions())->keyBy(fn ($action): string => $action->key);

        return [
            $defaults['edit'],
            $defaults['delete'],
            $defaults['restore'],
            $defaults['force_delete'],
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('design_type')->label('Design Type')->placeholder('All Types')->options([]),
            Filter::select('category_id')->label('Category')->placeholder('All Categories')->options([]),
            Filter::select('design_system')->label('Design System')->placeholder('All Systems')->options([]),
            Filter::dateRange('created_at')->label('Created Date'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('draft')->label('Draft')->icon('ri-file-line')->color('warning')->value('draft'),
            StatusTab::make('published')->label('Published')->icon('ri-checkbox-circle-line')->color('success')->value('published'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
