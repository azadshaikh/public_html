<?php

declare(strict_types=1);

namespace Modules\CMS\Definitions;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\CMS\Http\Requests\RedirectionRequest;
use Modules\CMS\Models\Redirection;

class RedirectionDefinition extends ScaffoldDefinition
{
    protected string $entityName = 'Redirection';

    protected string $entityPlural = 'Redirections';

    protected string $routePrefix = 'cms.redirections';

    protected string $permissionPrefix = 'redirections';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Redirection::class;
    }

    public function getRequestClass(): ?string
    {
        return RedirectionRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('source_url')
                ->label('From')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('260px'),

            Column::make('target_url')
                ->label('To')
                ->sortable()
                ->searchable()
                ->width('260px'),

            Column::make('redirect_type')
                ->label('HTTP')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('url_type')
                ->label('Target')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('match_type')
                ->label('Match')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('hits')
                ->label('Hits')
                ->sortable()
                ->right()
                ->width('90px'),

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

    public function filters(): array
    {
        return [
            Filter::select('redirect_type')->label('Redirect Type')->placeholder('All Redirect Types')->options([]),
            Filter::select('url_type')->label('URL Type')->placeholder('All URL Types')->options([]),
            Filter::select('match_type')->label('Match Type')->placeholder('All Match Types')->options([]),
        ];
    }

    public function actions(): array
    {
        return [
            Action::make('edit')
                ->label('Edit')
                ->icon('ri-pencil-line')
                ->route($this->routePrefix.'.edit')
                ->permission('edit_'.$this->permissionPrefix)
                ->forRow(),

            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->route($this->routePrefix.'.destroy')
                ->method('DELETE')
                ->danger()
                ->confirm('Move to trash?')
                ->confirmBulk('Move {count} items to trash?')
                ->permission('delete_'.$this->permissionPrefix)
                ->hideOnStatus('trash')
                ->forBoth(),

            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->route($this->routePrefix.'.restore')
                ->method('PATCH')
                ->success()
                ->confirm('Restore?')
                ->confirmBulk('Restore {count} items?')
                ->permission('restore_'.$this->permissionPrefix)
                ->showOnStatus('trash')
                ->forBoth(),

            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-fill')
                ->route($this->routePrefix.'.force-delete')
                ->method('DELETE')
                ->danger()
                ->confirm('⚠️ Cannot be undone!')
                ->confirmBulk('⚠️ Permanently delete {count} items?')
                ->permission('delete_'.$this->permissionPrefix)
                ->showOnStatus('trash')
                ->forBoth(),
        ];
    }

    public function getShowRoute(): ?string
    {
        return null;
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success')->value('active'),
            StatusTab::make('inactive')->label('Inactive')->icon('ri-pause-circle-line')->color('secondary')->value('inactive'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }
}
