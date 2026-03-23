<?php

declare(strict_types=1);

namespace Modules\Billing\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Billing\Http\Requests\CouponRequest;
use Modules\Billing\Models\Coupon;

class CouponDefinition extends ScaffoldDefinition
{
    // ================================================================
    // CORE CONFIGURATION
    // ================================================================

    protected string $routePrefix = 'app.billing.coupons';

    protected string $permissionPrefix = 'coupons';

    protected ?string $statusField = null;

    public function getModelClass(): string
    {
        return Coupon::class;
    }

    public function getRequestClass(): ?string
    {
        return CouponRequest::class;
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

            Column::make('code')
                ->label('Code')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('140px'),

            Column::make('name')
                ->label('Name')
                ->sortable()
                ->searchable()
                ->width('200px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('100px'),

            Column::make('value_display')
                ->label('Value')
                ->width('100px'),

            Column::make('discount_duration')
                ->label('Duration')
                ->template('badge')
                ->width('110px'),

            Column::make('uses_count')
                ->label('Uses')
                ->sortable()
                ->width('80px'),

            Column::make('max_uses_display')
                ->label('Limit')
                ->width('80px'),

            Column::make('expires_at_display')
                ->label('Expires')
                ->width('120px'),

            Column::make('is_active')
                ->label('Active')
                ->template('badge')
                ->sortable()
                ->width('90px'),

            Column::make('created_at')
                ->label('Created')
                ->template('datetime')
                ->sortable()
                ->width('140px'),

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
            Filter::select('type')
                ->label('Type')
                ->options([
                    ['value' => Coupon::TYPE_PERCENT, 'label' => 'Percentage'],
                    ['value' => Coupon::TYPE_FIXED, 'label' => 'Fixed Amount'],
                ])
                ->placeholder('All Types'),

            Filter::select('discount_duration')
                ->label('Duration')
                ->options([
                    ['value' => Coupon::DURATION_ONCE, 'label' => 'Once'],
                    ['value' => Coupon::DURATION_REPEATING, 'label' => 'Repeating'],
                    ['value' => Coupon::DURATION_FOREVER, 'label' => 'Forever'],
                ])
                ->placeholder('All Durations'),
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
                ->icon('ri-close-circle-line')
                ->color('secondary')
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
        return 'billing::coupons';
    }
}
