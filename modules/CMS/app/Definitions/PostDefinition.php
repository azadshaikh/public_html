<?php

declare(strict_types=1);

namespace Modules\CMS\Definitions;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\CMS\Http\Requests\PostRequest;
use Modules\CMS\Models\CmsPost;

class PostDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'cms.posts';

    protected string $permissionPrefix = 'posts';

    protected ?string $statusField = 'status';

    /**
     * Default sort by date (WordPress-style sorting is handled in PostService)
     */
    protected ?string $defaultSort = 'published_at';

    public function getModelClass(): string
    {
        return CmsPost::class;
    }

    public function getRequestClass(): ?string
    {
        return PostRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('featured_image')
                ->label('')
                ->template('post_featured_image')
                ->width('88px'),

            Column::make('title_with_meta')
                ->label('Title')
                ->sortable('title')
                ->searchable(['title', 'slug', 'excerpt', 'content', 'author.name', 'categories.title'])
                ->template('post_title_meta')
                ->width('400px'),

            Column::make('categories_display')
                ->label('Categories')
                ->template('post_categories')
                ->width('180px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('display_date')
                ->label('Date')
                ->sortable('published_at')
                ->template('post_date')
                ->width('180px'),

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

            Filter::select('author_id')
                ->label('Author')
                ->placeholder('All Authors')
                ->options([]),

            Filter::select('category_ids')
                ->label('Categories')
                ->placeholder('All Categories')
                ->options([])
                ->multiple()
                ->meta(['apply' => 'filterByCategory']),

            Filter::select('tag_ids')
                ->label('Tags')
                ->placeholder('All Tags')
                ->options([])
                ->multiple()
                ->meta(['apply' => 'filterByTags']),

            Filter::dateRange('published_at')
                ->label('Published Date'),

            Filter::dateRange('updated_at')
                ->label('Last Updated'),
        ];
    }

    public function actions(): array
    {
        $defaults = collect($this->defaultActions())->keyBy(fn ($action): string => $action->key);

        return [
            $defaults['show'],
            $defaults['edit'],

            Action::make('builder')
                ->label('Edit in Builder')
                ->icon('ri-layout-3-line')
                ->route('cms.builder.edit')
                ->permission('edit_'.$this->permissionPrefix)
                ->hideOnStatus('trash')
                ->attributes([
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                    'up-follow' => 'false',
                ])
                ->forRow(),

            Action::make('duplicate')
                ->label('Duplicate')
                ->icon('ri-file-copy-line')
                ->route($this->routePrefix.'.duplicate')
                ->method('POST')
                ->permission('add_'.$this->permissionPrefix)
                ->confirm('Create a copy of this post as a draft?')
                ->hideOnStatus('trash')
                ->forRow(),

            $defaults['delete'],
            $defaults['restore'],
            $defaults['force_delete'],
        ];
    }

    public function statusTabs(): array
    {
        $tabs = [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
        ];

        // Generate tabs from config (single source of truth)
        foreach (config('cms.post_status', []) as $key => $status) {
            $tabs[] = StatusTab::make($key)
                ->label($status['label'])
                ->icon($status['icon'] ?? 'ri-checkbox-blank-circle-line')
                ->color($status['color'] ?? 'secondary')
                ->value($key);
        }

        // Trash tab (uses soft deletes, not a status value)
        $tabs[] = StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger');

        return $tabs;
    }
}
