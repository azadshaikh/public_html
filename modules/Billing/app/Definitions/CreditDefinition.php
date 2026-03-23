<?php

declare(strict_types=1);

namespace Modules\Billing\Definitions;

use App\Scaffold\Column;
use App\Scaffold\Filter;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\StatusTab;
use Modules\Billing\Http\Requests\CreditRequest;
use Modules\Billing\Models\Credit;

class CreditDefinition extends ScaffoldDefinition
{
    protected string $routePrefix = 'app.billing.credits';

    protected string $permissionPrefix = 'credits';

    protected ?string $statusField = 'status';

    public function getModelClass(): string
    {
        return Credit::class;
    }

    public function getRequestClass(): ?string
    {
        return CreditRequest::class;
    }

    public function columns(): array
    {
        return [
            Column::make('_bulk_select')
                ->label('')
                ->checkbox()
                ->width('40px')
                ->excludeFromExport(),

            Column::make('credit_number')
                ->label('Credit #')
                ->sortable()
                ->searchable()
                ->link('show_url')
                ->width('150px'),

            Column::make('customer_display')
                ->label('Customer')
                ->width('200px'),

            Column::make('formatted_amount')
                ->label('Amount')
                ->sortable('amount')
                ->width('120px'),

            Column::make('formatted_remaining')
                ->label('Remaining')
                ->sortable('amount_remaining')
                ->width('120px'),

            Column::make('type')
                ->label('Type')
                ->template('badge')
                ->sortable()
                ->width('140px'),

            Column::make('status')
                ->label('Status')
                ->template('badge')
                ->sortable()
                ->width('120px'),

            Column::make('expires_at')
                ->label('Expires')
                ->template('date')
                ->sortable()
                ->width('120px'),

            Column::make('_actions')
                ->label('Actions')
                ->template('actions')
                ->excludeFromExport(),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('type')
                ->label('Type')
                ->options([
                    ['value' => Credit::TYPE_CREDIT_NOTE, 'label' => 'Credit Note'],
                    ['value' => Credit::TYPE_REFUND_CREDIT, 'label' => 'Refund Credit'],
                    ['value' => Credit::TYPE_PROMO_CREDIT, 'label' => 'Promotional Credit'],
                    ['value' => Credit::TYPE_GOODWILL, 'label' => 'Goodwill Credit'],
                ])
                ->placeholder('All Types'),

            Filter::dateRange('expires_at')
                ->label('Expiry Date'),
        ];
    }

    public function statusTabs(): array
    {
        return [
            StatusTab::make('all')
                ->label('All')
                ->icon('ri-list-check')
                ->color('primary')
                ->default(),

            StatusTab::make(Credit::STATUS_ACTIVE)
                ->label('Active')
                ->icon('ri-checkbox-circle-line')
                ->color('success')
                ->value(Credit::STATUS_ACTIVE),

            StatusTab::make(Credit::STATUS_EXHAUSTED)
                ->label('Exhausted')
                ->icon('ri-subtract-line')
                ->color('secondary')
                ->value(Credit::STATUS_EXHAUSTED),

            StatusTab::make(Credit::STATUS_EXPIRED)
                ->label('Expired')
                ->icon('ri-time-line')
                ->color('warning')
                ->value(Credit::STATUS_EXPIRED),

            StatusTab::make(Credit::STATUS_CANCELLED)
                ->label('Cancelled')
                ->icon('ri-close-circle-line')
                ->color('danger')
                ->value(Credit::STATUS_CANCELLED),

            StatusTab::make('trash')
                ->label('Trash')
                ->icon('ri-delete-bin-line')
                ->color('danger'),
        ];
    }

    public function getViewPath(): string
    {
        return 'billing::credits';
    }
}
