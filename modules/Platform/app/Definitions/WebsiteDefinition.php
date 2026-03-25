<?php

declare(strict_types=1);

namespace Modules\Platform\Definitions;

use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Illuminate\Support\Facades\App;
use Modules\Platform\Http\Requests\WebsiteRequest;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Throwable;

class WebsiteDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'platform.websites';

    protected string $permissionPrefix = 'websites';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Website::class;
    }

    public function getRequestClass(): ?string
    {
        return WebsiteRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')->label('')->checkbox()->width('40px')->excludeFromExport(),

            Column::make('uid')
                ->label('UID')
                ->sortable()
                ->searchable()
                ->template('platform_uid')
                ->width('110px'),

            Column::make('name')
                ->label('Name')
                ->sortable()
                ->searchable()
                ->template('platform_website_name')
                ->width('250px'),

            Column::make('customer_name')
                ->label('Customer')
                ->sortable('customer_ref'),

            Column::make('agency_name')
                ->label('Agency')
                ->template('badge')
                ->sortable('agency_id')
                ->width('160px'),

            Column::make('server_name')
                ->label('Server')
                ->template('badge')
                ->sortable('server_id')
                ->width('160px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('dns_mode')
                ->label('DNS')
                ->template('badge')
                ->sortable()
                ->width('110px'),

            Column::make('cdn_status')
                ->label('CDN')
                ->template('badge')
                ->width('90px'),

            Column::make('created_at')
                ->label('Created')
                ->sortable()
                ->width('120px'),

            Column::make('_actions')->label('Actions')->template('actions')->excludeFromExport()->width('90px'),
        ];
    }

    public function filters(): array
    {
        $serverOptions = [];
        $agencyOptions = [];

        try {
            $serverOptions = Server::query()->orderBy('name')->pluck('name', 'id')->toArray();
            $agencyOptions = Agency::query()->orderBy('name')->pluck('name', 'id')->toArray();
        } catch (Throwable $throwable) {
            if (! App::runningInConsole()) {
                throw $throwable;
            }
        }

        return [
            Filter::select('customer_ref')->label('Customer')->placeholder('All Customers'),
            Filter::select('plan_ref')->label('Plan')->placeholder('All Plans'),
            Filter::select('server_id')
                ->label('Server')
                ->placeholder('All Servers')
                ->options($serverOptions),
            Filter::select('agency_id')
                ->label('Agency')
                ->placeholder('All Agencies')
                ->options($agencyOptions),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')->label('All')->icon('ri-list-check')->color('primary')->default(),
            StatusTab::make('failed')->label('Prov/Failed')->icon('ri-error-warning-line')->color('danger'),
            StatusTab::make('active')->label('Active')->icon('ri-checkbox-circle-line')->color('success')->value('active'),
            StatusTab::make('suspended')->label('Suspended')->icon('ri-pause-circle-line')->color('warning')->value('suspended'),
            StatusTab::make('expired')->label('Expired')->icon('ri-time-line')->color('danger')->value('expired'),
            StatusTab::make('trash')->label('Trash')->icon('ri-delete-bin-line')->color('danger'),
        ];
    }

    public function hasNotes(): bool
    {
        return true;
    }

    public function actions(): array
    {
        $prefix = $this->getPermissionPrefix();
        $routePrefix = $this->getRoutePrefix();

        return [
            // Row-only: View/Show
            Action::make('show')
                ->label('View')
                ->icon('ri-eye-line')
                ->route($routePrefix.'.show')
                ->permission('view_'.$prefix)
                ->forRow(),

            // Row-only: Edit
            Action::make('edit')
                ->label('Edit')
                ->icon('ri-pencil-line')
                ->route($routePrefix.'.edit')
                ->permission('edit_'.$prefix)
                ->forRow(),

            // Both row and bulk: Suspend (hidden for already-suspended or trashed)
            Action::make('suspend')
                ->label('Suspend')
                ->icon('ri-pause-circle-line')
                ->warning()
                ->method('POST')
                ->confirm('Are you sure you want to suspend this website?')
                ->confirmBulk('Suspend {count} websites? They will show a suspended page.')
                ->permission('edit_'.$prefix)
                ->hideOnStatus(['suspended', 'trash'])
                ->forBoth(),

            // Both row and bulk: Unsuspend (only on suspended tab)
            Action::make('unsuspend')
                ->label('Unsuspend')
                ->icon('ri-play-circle-line')
                ->success()
                ->method('POST')
                ->confirm('Are you sure you want to unsuspend this website?')
                ->confirmBulk('Unsuspend {count} websites? They will become active again.')
                ->permission('edit_'.$prefix)
                ->showOnStatus('suspended')
                ->forBoth(),

            // Both row and bulk: Delete (move to trash)
            Action::make('delete')
                ->label('Move to Trash')
                ->icon('ri-delete-bin-line')
                ->danger()
                ->route($routePrefix.'.destroy')
                ->method('DELETE')
                ->confirm('Are you sure you want to move this item to trash?')
                ->confirmBulk('Move {count} items to trash?')
                ->permission('delete_'.$prefix)
                ->hideOnStatus('trash')
                ->forBoth(),

            // Both row and bulk: Restore (only on trash)
            Action::make('restore')
                ->label('Restore')
                ->icon('ri-refresh-line')
                ->success()
                ->route($routePrefix.'.restore')
                ->method('PATCH')
                ->confirm('Are you sure you want to restore this item?')
                ->confirmBulk('Restore {count} items?')
                ->permission('restore_'.$prefix)
                ->showOnStatus('trash')
                ->forBoth(),

            // Both row and bulk: Remove from server (only on trash)
            Action::make('remove_from_server')
                ->label('Remove from Server')
                ->icon('ri-server-line')
                ->warning()
                ->route($routePrefix.'.remove-from-server')
                ->method('POST')
                ->confirm('⚠️ This will delete the Hestia user, files and database from the server. The website record will be kept for historical tracking.')
                ->confirmBulk('⚠️ Remove {count} websites from server? This will delete Hestia users and files. Records will be kept.')
                ->permission('delete_'.$prefix)
                ->showOnStatus('trash')
                ->forBoth(),

            // Both row and bulk: Force delete (only on trash)
            Action::make('force_delete')
                ->label('Delete Permanently')
                ->icon('ri-delete-bin-fill')
                ->danger()
                ->route($routePrefix.'.force-delete')
                ->method('DELETE')
                ->confirm('⚠️ This cannot be undone!')
                ->confirmBulk('⚠️ Permanently delete {count} items? This cannot be undone!')
                ->permission('delete_'.$prefix)
                ->showOnStatus('trash')
                ->forBoth(),
        ];
    }
}
