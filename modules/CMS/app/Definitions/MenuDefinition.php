<?php

declare(strict_types=1);

namespace Modules\CMS\Definitions;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\CMS\Http\Requests\MenuFormRequest;
use Modules\CMS\Models\Menu;

/**
 * MenuDefinition - Scaffold configuration for Menu containers
 *
 * Note: statusField is null because Menu uses is_active boolean instead of status enum.
 * Status tabs filter by is_active field directly.
 */
class MenuDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'cms.appearance.menus';

    protected string $permissionPrefix = 'menus';

    protected ?string $inertiaPagePrefix = 'cms/menus';

    protected ?string $statusField = null; // Uses is_active boolean, not status column

    public function getModelClass(): string
    {
        return Menu::class;
    }

    public function getRequestClass(): ?string
    {
        return MenuFormRequest::class;
    }

    // ================================================================
    // DATAGRID COLUMNS
    // ================================================================

    public function columns(): array
    {
        return [
            // Bulk select checkbox - ALWAYS FIRST
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            // Primary identifier - links to edit page (menu builder)
            Column::make('name')
                ->label('Menu')
                ->template('menu_title')
                ->sortable()
                ->searchable()
                ->width('280px'),

            Column::make('location')
                ->label('Location')
                ->template('menu_location')
                ->sortable()
                ->searchable(),

            Column::make('items_count')
                ->label('Items')
                ->template('badge')
                ->sortable('all_items_count')
                ->center(),

            Column::make('is_active')
                ->label('Status')
                ->template('active_status')
                ->center(),

            Column::make('updated_at')
                ->label('Updated')
                ->template('datetime')
                ->sortable(),

            // Actions column - ALWAYS LAST
            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    // ================================================================
    // DATAGRID FILTERS (None - simple entity)
    // ================================================================

    public function filters(): array
    {
        return [];
    }

    // ================================================================
    // ROW ACTIONS (Custom for Menu - includes duplicate)
    // ================================================================

    public function actions(): array
    {
        return [
            // Edit - goes to menu builder
            Action::make('edit')
                ->label('Edit')
                ->icon('ri-pencil-line')
                ->route("{$this->routePrefix}.edit")
                ->permission('edit_'.$this->permissionPrefix)
                ->forRow(),

            // Duplicate menu - hidden in trash
            Action::make('duplicate')
                ->label('Duplicate')
                ->icon('ri-file-copy-line')
                ->route("{$this->routePrefix}.duplicate")
                ->method('POST')
                ->permission('add_'.$this->permissionPrefix)
                ->confirm('Create a copy of this menu (without location assignment)?')
                ->hideOnStatus('trash')
                ->forRow(),

            // Soft delete - hidden in trash
            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->route("{$this->routePrefix}.destroy")
                ->method('DELETE')
                ->permission('delete_'.$this->permissionPrefix)
                ->confirm('Are you sure you want to move this menu to trash?')
                ->hideOnStatus('trash')
                ->forRow(),

            // Restore from trash - shown only in trash
            Action::make('restore')
                ->label('Restore')
                ->icon('ri-loop-right-line')
                ->route("{$this->routePrefix}.restore")
                ->method('PATCH')
                ->permission('restore_'.$this->permissionPrefix)
                ->confirm('Are you sure you want to restore this menu?')
                ->showOnStatus('trash')
                ->forRow(),

            // Force delete in trash - shown only in trash
            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-line')
                ->route("{$this->routePrefix}.force-delete")
                ->method('DELETE')
                ->permission('delete_'.$this->permissionPrefix)
                ->danger()
                ->confirm('⚠️ This cannot be undone! Permanently delete this menu?')
                ->showOnStatus('trash')
                ->forRow(),

            // Bulk: Move to Trash
            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->permission('delete_'.$this->permissionPrefix)
                ->confirmBulk('Move {count} menus to trash?')
                ->hideOnStatus('trash')
                ->forBulk(),

            // Bulk: Restore
            Action::make('restore')
                ->label('Restore')
                ->icon('ri-loop-right-line')
                ->permission('restore_'.$this->permissionPrefix)
                ->confirmBulk('Restore {count} menus?')
                ->showOnStatus('trash')
                ->forBulk(),

            // Bulk: Delete Permanently
            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-line')
                ->permission('delete_'.$this->permissionPrefix)
                ->danger()
                ->confirmBulk('⚠️ Permanently delete {count} menus? This cannot be undone!')
                ->showOnStatus('trash')
                ->forBulk(),
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
                ->icon('ri-menu-line')
                ->color('primary')
                ->default(),

            StatusTab::make('active')
                ->label('Active')
                ->icon('ri-checkbox-circle-line')
                ->color('success'),

            StatusTab::make('inactive')
                ->label('Inactive')
                ->icon('ri-pause-circle-line')
                ->color('warning'),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }
}
