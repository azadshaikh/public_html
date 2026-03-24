<?php

declare(strict_types=1);

namespace Modules\Agency\Definitions;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Agency\Http\Requests\WebsiteManageRequest;
use Modules\Agency\Models\AgencyWebsite;

/**
 * Definition for the Agency admin website management DataGrid.
 *
 * This is the admin-facing CRUD for managing all websites in the agency.
 * The customer-facing read-only view remains in the original WebsiteController.
 */
class WebsiteManageDefinition extends ScaffoldDefinition
{
    protected string $entityName = 'Agency Website';

    protected string $routePrefix = 'agency.admin.websites';

    protected string $permissionPrefix = 'agency_websites';

    protected ?string $statusField = 'status';

    // Views are nested under admin/websites — must override explicitly
    // because resolveViewPath() only uses the last route prefix segment.
    public function getIndexView(): string
    {
        return 'agency/admin/websites/index';
    }

    public function getEditView(): string
    {
        return 'agency/admin/websites/edit';
    }

    public function getShowView(): string
    {
        return 'agency/admin/websites/show';
    }

    public function getModelClass(): string
    {
        return AgencyWebsite::class;
    }

    public function getRequestClass(): ?string
    {
        return WebsiteManageRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('site_id')
                ->label('Site ID')
                ->template('platform_uid')
                ->sortable()
                ->width('110px'),

            Column::make('name')
                ->label('Website')
                ->sortable()
                ->searchable()
                ->template('platform_website_name')
                ->width('300px'),

            Column::make('customer_name')
                ->label('Customer')
                ->sortable('customer_ref')
                ->width('180px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('plan')
                ->label('Plan')
                ->sortable()
                ->width('120px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('130px'),

            Column::make('expired_on')
                ->label('Expiry')
                ->template('datetime')
                ->sortable()
                ->width('130px'),

            Column::make('created_at')
                ->label('Created')
                ->template('datetime')
                ->sortable()
                ->width('120px'),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport()
                ->width('90px'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('type')
                ->label('Type')
                ->placeholder('All Types')
                ->options([
                    ['value' => 'trial', 'label' => 'Trial'],
                    ['value' => 'free', 'label' => 'Free'],
                    ['value' => 'paid', 'label' => 'Paid'],
                    ['value' => 'internal', 'label' => 'Internal'],
                    ['value' => 'special', 'label' => 'Special'],
                ]),
            Filter::select('plan')->label('Plan')->placeholder('All Plans'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('failed')->label('Prov/Failed')->icon('ri-error-warning-line')->color('danger'),
            StatusTab::make('suspended')->label('Suspended')->icon('ri-pause-circle-line')->color('warning')->value('suspended'),
            StatusTab::make('expired')->label('Expired')->icon('ri-time-line')->color('danger')->value('expired'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }

    public function actions(): array
    {
        $prefix = $this->getPermissionPrefix();
        $routePrefix = $this->getRoutePrefix();

        return [
            Action::make('show')
                ->label('View')
                ->icon('ri-eye-line')
                ->route($routePrefix.'.show')
                ->permission('view_'.$prefix)
                ->forRow(),

            Action::make('edit')
                ->label('Edit')
                ->icon('ri-pencil-line')
                ->route($routePrefix.'.edit')
                ->permission('edit_'.$prefix)
                ->forRow(),

            Action::make('suspend')
                ->label('Suspend')
                ->icon('ri-pause-circle-line')
                ->route($routePrefix.'.suspend')
                ->warning()
                ->method('POST')
                ->confirm('Are you sure you want to suspend this website?')
                ->confirmBulk('Suspend {count} websites?')
                ->permission('edit_'.$prefix)
                ->hideOnStatus(['suspended', 'trash'])
                ->forBoth(),

            Action::make('unsuspend')
                ->label('Unsuspend')
                ->icon('ri-play-circle-line')
                ->route($routePrefix.'.unsuspend')
                ->success()
                ->method('POST')
                ->confirm('Are you sure you want to unsuspend this website?')
                ->confirmBulk('Unsuspend {count} websites?')
                ->permission('edit_'.$prefix)
                ->showOnStatus('suspended')
                ->forBoth(),

            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->danger()
                ->route($routePrefix.'.destroy')
                ->method('DELETE')
                ->confirm('Are you sure you want to trash this website?')
                ->confirmBulk('Move {count} websites to trash?')
                ->permission('delete_'.$prefix)
                ->hideOnStatus('trash')
                ->forBoth(),

            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->success()
                ->route($routePrefix.'.restore')
                ->method('PATCH')
                ->confirm('Are you sure you want to restore this website?')
                ->confirmBulk('Restore {count} websites?')
                ->permission('restore_'.$prefix)
                ->showOnStatus('trash')
                ->forBoth(),

            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-fill')
                ->danger()
                ->route($routePrefix.'.force-delete')
                ->method('DELETE')
                ->confirm('⚠️ This will permanently delete this website. This cannot be undone!')
                ->confirmBulk('⚠️ Permanently delete {count} websites? This cannot be undone!')
                ->permission('delete_'.$prefix)
                ->showOnStatus('trash')
                ->forBoth(),
        ];
    }
}
