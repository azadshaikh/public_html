<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Agency\Enums\WebsiteStatus;
use Modules\Agency\Models\AgencyWebsite;

class WebsiteController extends Controller
{
    // ─────────────────────────────────────────────────────────
    // Index (HTML + AJAX JSON for DataGrid)
    // ─────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $data = $this->buildDataPayload($request, includeStats: true);

        return Inertia::render('agency/websites/index', [
            'websites' => $data['items'],
            'pagination' => $data['pagination'],
            'statistics' => $data['statistics'] ?? [],
            'filters' => [
                'search' => trim((string) $request->input('search', '')),
                'status' => (string) $request->input('status', 'all'),
            ],
            'canCreateWebsite' => Route::has('agency.websites.create'),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Data endpoint (explicit JSON route)
    // ─────────────────────────────────────────────────────────

    public function data(Request $request): JsonResponse
    {
        return $this->dataResponse($request);
    }

    // ─────────────────────────────────────────────────────────
    // Show
    // ─────────────────────────────────────────────────────────

    public function show(int $id): Response|RedirectResponse
    {
        $user = auth()->user();

        $website = AgencyWebsite::query()
            ->withTrashed()
            ->where('owner_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        // Load subscription with plan details
        $subscription = $website->findSubscription();
        if ($subscription) {
            $subscription->load(['plan', 'planPrice']);
        }

        // Load invoices and payments for this website
        $invoices = $website->findInvoices();
        $payments = $website->findPayments();

        return Inertia::render('agency/websites/show', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name ?? $website->domain,
                'domain' => $website->domain,
                'site_url' => 'https://'.($website->is_www ? 'www.' : '').$website->domain,
                'admin_url' => $website->admin_slug ? 'https://'.$website->domain.'/'.$website->admin_slug.'/login' : null,
                'status' => $website->status->value,
                'status_label' => $website->status->label(),
                'status_badge' => $website->status->badgeClass(),
                'plan' => $website->plan,
                'type' => $website->type,
                'type_label' => $website->type_label,
                'astero_version' => $website->astero_version,
                'created_at' => $website->created_at?->toDateString(),
                'provisioned_at' => $website->provisioned_at?->toDateString(),
            ],
            'subscription' => $subscription ? [
                'plan_name' => $subscription->plan?->name ?? $website->plan ?? 'N/A',
                'formatted_price' => $subscription->planPrice
                    ? strtoupper((string) ($subscription->planPrice->currency ?? $subscription->currency)).' '.number_format((float) ($subscription->planPrice->price ?? $subscription->price), 2)
                    : null,
                'billing_cycle' => $subscription->planPrice->billing_cycle ?? $subscription->billing_cycle ?? 'month',
                'status_label' => $subscription->status_label,
                'status_class' => $subscription->status_class,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'current_period_start' => $subscription->current_period_start?->toDateString(),
                'current_period_end' => $subscription->current_period_end?->toDateString(),
                'created_at' => $subscription->created_at?->toDateString(),
                'canceled_at' => $subscription->canceled_at?->toDateString(),
                'cancels_at' => $subscription->cancels_at?->toDateString(),
                'on_grace_period' => (bool) $subscription->on_grace_period,
            ] : null,
            'invoices' => $invoices->map(fn ($invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number ?? (string) $invoice->id,
                'status' => $invoice->status,
                'total' => (float) $invoice->total,
                'issue_date' => $invoice->issue_date?->toDateString(),
            ])->values()->all(),
            'payments' => $payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'created_at' => $payment->created_at?->toDateString(),
            ])->values()->all(),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // Private: build JSON response for DataGrid
    // ─────────────────────────────────────────────────────────

    private function dataResponse(Request $request): JsonResponse
    {
        $includeStats = ! $request->ajax() || $request->boolean('include_stats');
        $data = $this->buildDataPayload($request, $includeStats);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Build the data payload that DataGrid expects.
     *
     * @return array{items: array, pagination: array, columns?: array, filters?: array, statistics?: array}
     */
    private function buildDataPayload(Request $request, bool $includeStats = false): array
    {
        $user = auth()->user();
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $perPage = min(max((int) $request->input('per_page', 15), 5), 100);

        $allowedStatuses = array_map(
            static fn (WebsiteStatus $ws): string => $ws->value,
            WebsiteStatus::cases()
        );

        if (! in_array($status, array_merge(['all'], $allowedStatuses), true)) {
            $status = 'all';
        }

        $query = AgencyWebsite::query()
            ->withTrashed()
            ->where('owner_id', $user->id);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $escaped = $this->escapeLikePattern($search);
            $pattern = sprintf('%%%s%%', $escaped);

            $query->where(function (Builder $q) use ($pattern): void {
                $q->where('name', 'ilike', $pattern)
                    ->orWhere('domain', 'ilike', $pattern);
            });
        }

        // Sorting (use sort_column/sort_direction to match DataGrid frontend contract)
        $sortBy = (string) $request->input('sort_column', 'created_at');
        $sortDir = strtolower((string) $request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortableColumns = ['name', 'domain', 'status', 'created_at'];
        if (in_array($sortBy, $sortableColumns, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->latest();
        }

        $paginator = $query->paginate($perPage);

        $items = collect($paginator->items())->map(function (AgencyWebsite $website): array {
            $isTrashed = $website->trashed();

            // Resolve manage URL
            $manageUrl = match (true) {
                $website->status === WebsiteStatus::Provisioning,
                $website->status === WebsiteStatus::WaitingForDns,
                $website->status === WebsiteStatus::Failed => route('agency.onboarding.provisioning.website', $website->id),
                default => route('agency.websites.show', $website->id),
            };

            return [
                'id' => $website->id,
                'name' => $website->name ?? 'Untitled Site',
                'domain' => $website->domain,
                'domain_url' => $website->domain_url,
                'status' => $website->status->value,
                'status_label' => $website->status->label(),
                'status_badge' => $website->status->badgeClass(),
                'plan' => $website->plan,
                'type' => $website->type,
                'type_label' => $website->type_label,
                'is_trashed' => $isTrashed,
                'manage_url' => $manageUrl,
                'created_at' => $website->created_at?->toIso8601String(),
            ];
        })->all();

        $payload = [
            'items' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
            'columns' => $this->getColumns(),
            'filters' => [],
        ];

        if ($includeStats) {
            $payload['statistics'] = $this->getStatistics($user->id);
        }

        return $payload;
    }

    // ─────────────────────────────────────────────────────────
    // DataGrid configuration
    // ─────────────────────────────────────────────────────────

    private function getDataGridConfig(): array
    {
        return [
            'columns' => $this->getColumns(),
            'filters' => [],
        ];
    }

    /**
     * @return list<array{key: string, label: string, sortable?: bool, template?: string, width?: string, class?: string, mobileHidden?: bool}>
     */
    private function getColumns(): array
    {
        return [
            [
                'key' => 'name',
                'label' => 'Website',
                'sortable' => true,
                'searchable' => true,
                'template' => 'agency_website_name',
                'width' => '320px',
                'mobilePrimary' => true,
            ],
            [
                'key' => 'status',
                'label' => 'Status',
                'sortable' => true,
                'template' => 'agency_website_status',
                'width' => '120px',
            ],
            [
                'key' => 'plan',
                'label' => 'Plan',
                'sortable' => false,
                'template' => 'agency_website_plan',
                'width' => '150px',
            ],
            [
                'key' => 'created_at',
                'label' => 'Created',
                'sortable' => true,
                'template' => 'date',
                'width' => '130px',
            ],
            [
                'key' => '_actions',
                'label' => '',
                'template' => 'agency_website_actions',
                'type' => 'actions',
                'width' => '120px',
                'class' => 'text-end',
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getStatistics(int $ownerId): array
    {
        $counts = AgencyWebsite::query()
            ->withTrashed()
            ->where('owner_id', $ownerId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => array_sum($counts),
            'active' => $counts[WebsiteStatus::Active->value] ?? 0,
            'provisioning' => $counts[WebsiteStatus::Provisioning->value] ?? 0,
            'suspended' => $counts[WebsiteStatus::Suspended->value] ?? 0,
            'trash' => $counts[WebsiteStatus::Trash->value] ?? 0,
        ];
    }

    private function escapeLikePattern(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
