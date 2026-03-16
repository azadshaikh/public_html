<?php

declare(strict_types=1);

namespace Modules\CMS\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\CMS\Http\Requests\CategoryRequest;
use Modules\CMS\Models\CmsPost;

class CategoryDefinition extends ScaffoldDefinition
{
    protected string $entityName = 'Category';

    protected string $routePrefix = 'cms.categories';

    protected string $permissionPrefix = 'categories';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return CmsPost::class;
    }

    public function getRequestClass(): ?string
    {
        return CategoryRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('title_with_meta')
                ->label('Title')
                ->sortable('title')
                ->searchable(['title', 'slug', 'parent.title'])
                ->template('category_title_meta')
                ->width('350px'),

            Column::make('posts_count')
                ->label('Posts')
                ->sortable()
                ->width('100px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('display_date')
                ->label('Date')
                ->sortable('created_at')
                ->template('term_date')
                ->width('160px'),

            Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport()->width('80px'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('statuses')
                ->label('Status')
                ->placeholder('All Statuses')
                ->options([])
                ->multiple()
                ->meta(['apply' => 'filterByStatuses']),

            Filter::select('parent_id')->label('Parent')->placeholder('All Parents')->options([]),

            Filter::select('author_id')->label('Author')->placeholder('All Authors')->options([]),

            Filter::dateRange('created_at')->label('Created Date'),

            Filter::dateRange('updated_at')->label('Last Updated'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('published')->label('Published')->icon('ri-checkbox-circle-line')->color('success')->value('published'),
            StatusTab::make('draft')->label('Draft')->icon('ri-file-line')->color('warning')->value('draft'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
