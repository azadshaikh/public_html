<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Billing\Models\Invoice;
use Modules\Customers\Models\Customer;

class InvoiceController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        if (! module_enabled('billing')) {
            return to_route('agency.websites.index')
                ->with('error', 'Billing is not available.');
        }

        $user = auth()->user();
        $customer = $this->resolveCustomer($user);

        if (! $customer instanceof Customer) {
            return Inertia::render('agency/invoices/index', [
                'invoices' => [],
                'pagination' => $this->paginationPayload(new LengthAwarePaginator([], 0, 15)),
                'statusCounts' => ['total' => 0, 'paid' => 0, 'pending' => 0, 'overdue' => 0],
                'balanceDue' => 0.0,
            ]);
        }

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->latest('issue_date')
            ->paginate(15);

        $statusCounts = Invoice::query()
            ->where('customer_id', $customer->id)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $balanceDue = Invoice::query()
            ->where('customer_id', $customer->id)
            ->sum('amount_due');

        return Inertia::render('agency/invoices/index', [
            'invoices' => $invoices->through(fn (Invoice $invoice): array => $this->serializeInvoiceListItem($invoice))->values()->all(),
            'pagination' => $this->paginationPayload($invoices),
            'statusCounts' => [
                'total' => array_sum($statusCounts),
                'paid' => $statusCounts[Invoice::STATUS_PAID] ?? 0,
                'pending' => ($statusCounts[Invoice::STATUS_PENDING] ?? 0) + ($statusCounts[Invoice::STATUS_SENT] ?? 0),
                'overdue' => $statusCounts[Invoice::STATUS_OVERDUE] ?? 0,
            ],
            'balanceDue' => (float) $balanceDue,
        ]);
    }

    public function show(int $id): Response|RedirectResponse
    {
        if (! module_enabled('billing')) {
            return to_route('agency.websites.index')
                ->with('error', 'Billing is not available.');
        }

        $user = auth()->user();
        $customer = $this->resolveCustomer($user);

        if (! $customer instanceof Customer) {
            return to_route('agency.websites.index')
                ->with('error', 'Customer profile not found.');
        }

        $invoice = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('id', $id)
            ->with('items')
            ->firstOrFail();

        return Inertia::render('agency/invoices/show', [
            'invoice' => $this->serializeInvoiceDetail($invoice),
        ]);
    }

    private function resolveCustomer($user): ?Customer
    {
        return Customer::query()->where('user_id', $user->id)->first();
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function serializeInvoiceListItem(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? (string) $invoice->id,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'total' => (float) $invoice->total,
            'amount_due' => (float) $invoice->amount_due,
        ];
    }

    private function serializeInvoiceDetail(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number ?? (string) $invoice->id,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'status' => $invoice->status,
            'subtotal' => (float) $invoice->subtotal,
            'tax' => (float) $invoice->tax,
            'discount' => (float) $invoice->discount,
            'total' => (float) $invoice->total,
            'amount_due' => (float) $invoice->amount_due,
            'items' => $invoice->items->map(fn ($item): array => [
                'id' => $item->id,
                'description' => $item->description ?? 'N/A',
                'quantity' => (int) ($item->quantity ?? 1),
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
            ])->values()->all(),
        ];
    }
}
