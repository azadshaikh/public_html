<?php

declare(strict_types=1);

namespace App\Definitions;

use App\Enums\Status;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Scaffold\Action;
use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;

class UserDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    /**
     * Route prefix using dot notation
     */
    protected string $routePrefix = 'app.users';

    /**
     * Permission prefix (used in middleware)
     */
    protected string $permissionPrefix = 'users';

    /**
     * Status field name
     */
    protected ?string $statusField = 'status';

    /**
     * Return the Model class
     */
    public function getModelClass(): string
    {
        return User::class;
    }

    public function getRequestClass(): ?string
    {
        return UserRequest::class;
    }

    // ================================================================
    // DATAGRID COLUMNS
    // ================================================================

    public function columns(): array
    {
        return [
            // ⚠️ Bulk select checkbox - ALWAYS FIRST
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            // User with avatar - links to show page
            Column::make('name')
                ->label('User')
                ->template('user_info')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('250px'),

            // Email
            Column::make('email')
                ->label('Email')
                ->sortable()
                ->searchable(),

            // Email verification
            Column::make('email_verified')
                ->label('Email Verified')
                ->template('email_verified')
                ->width('140px'),

            // Roles
            Column::make('roles')
                ->label('Roles')
                ->template('roles_display'),

            // Status with badge
            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable(),

            // Created date
            Column::make('created_at')
                ->label('Created')
                ->sortable(),

            // ⚠️ Actions column - ALWAYS LAST
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
            // Role filter (dynamic, handled in service)
            Filter::select('role_id')
                ->label('Role')
                ->placeholder('All Roles'),

            // Email verification filter
            Filter::select('email_verified')
                ->label('Email Verification')
                ->options([
                    'verified' => 'Verified',
                    'unverified' => 'Unverified',
                ])
                ->placeholder('All Emails'),

            // Gender filter
            Filter::select('gender')
                ->label('Gender')
                ->options([
                    'male' => 'Male',
                    'female' => 'Female',
                    'other' => 'Other',
                ])
                ->placeholder('All Genders'),

            // Date range filter
            Filter::dateRange('created_at')
                ->label('Created Date'),
        ];
    }

    // ================================================================
    // ROW & BULK ACTIONS
    // ================================================================

    public function actions(): array
    {
        $defaults = collect($this->defaultActions())
            ->keyBy(fn ($action): string => $action->key);

        return [
            // --------------------------------------------------------
            // ROW-ONLY ACTIONS
            // --------------------------------------------------------

            $defaults['show'],
            $defaults['edit'],

            Action::make('impersonate')
                ->label('Impersonate')
                ->icon('ri-user-settings-line')
                ->route($this->routePrefix.'.impersonate')
                ->permission('impersonate_'.$this->permissionPrefix)
                ->fullReload() // Force full page reload to update session
                ->hideOnStatus('trash')
                ->forRow(),

            // --------------------------------------------------------
            // BOTH ROW & BULK ACTIONS
            // --------------------------------------------------------

            // Suspend action - HIDE on trash tab and banned users
            Action::make('suspend')
                ->label('Suspend')
                ->icon('ri-pause-circle-line')
                ->route($this->routePrefix.'.suspend')
                ->method('PATCH')
                ->warning()
                ->confirm('Suspend this user? They will be unable to log in.')
                ->confirmBulk('Suspend {count} users?')
                ->permission('edit_'.$this->permissionPrefix)
                ->hideOnStatus('trash')
                ->hideOnStatus('suspended')
                ->hideOnStatus('banned')
                ->forBoth(),

            // Ban action - HIDE on trash tab
            Action::make('ban')
                ->label('Ban')
                ->icon('ri-forbid-line')
                ->route($this->routePrefix.'.ban')
                ->method('PATCH')
                ->danger()
                ->confirm('Ban this user? They will be permanently blocked from the system.')
                ->confirmBulk('Ban {count} users?')
                ->permission('edit_'.$this->permissionPrefix)
                ->hideOnStatus('trash')
                ->hideOnStatus('banned')
                ->forBoth(),

            // Unban action - SHOW only for banned users
            Action::make('unban')
                ->label('Unban')
                ->icon('ri-checkbox-circle-line')
                ->route($this->routePrefix.'.unban')
                ->method('PATCH')
                ->success()
                ->confirm('Unban this user? They will be able to log in again.')
                ->confirmBulk('Unban {count} users?')
                ->permission('edit_'.$this->permissionPrefix)
                ->showOnStatus('banned')
                ->forBoth(),

            $defaults['delete'],
            $defaults['restore'],
            $defaults['force_delete'],
        ];
    }

    // ================================================================
    // STATUS TABS
    // ================================================================

    public function statusTabs(): array
    {
        $tabs = [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-dashboard-line')
                ->color('primary')
                ->default(),

            StatusTab::make('active')
                ->label('Active')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value(Status::ACTIVE->value),
            StatusTab::make('suspended')
                ->label('Suspended')
                ->icon('ri-pause-circle-line')
                ->color('warning')
                ->value(Status::SUSPENDED->value),

            StatusTab::make('banned')
                ->label('Banned')
                ->icon('ri-forbid-line')
                ->color('danger')
                ->value(Status::BANNED->value),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];

        if ($this->shouldShowPendingTab()) {
            array_splice($tabs, 2, 0, [
                StatusTab::make('pending')
                    ->label('Pending')
                    ->icon('ri-time-line')
                    ->color('info')
                    ->value(Status::PENDING->value),
            ]);
        }

        return $tabs;
    }

    protected function shouldShowPendingTab(): bool
    {
        $autoApprove = filter_var(setting('registration_auto_approve', true), FILTER_VALIDATE_BOOLEAN);

        return ! $autoApprove;
    }
}
