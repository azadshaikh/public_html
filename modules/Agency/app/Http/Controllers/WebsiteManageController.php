<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Agency\Definitions\WebsiteManageDefinition;
use Modules\Agency\Enums\WebsiteStatus;
use Modules\Agency\Models\AgencyWebsite;
use Modules\Agency\Services\WebsiteManageService;

/**
 * Admin-facing website management controller.
 *
 * Provides full Scaffold CRUD for agency websites plus
 * lifecycle actions (suspend, unsuspend, sync, retry) via Platform API.
 *
 * The customer-facing read-only WebsiteController remains separate.
 */
class WebsiteManageController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly WebsiteManageService $websiteManageService,
    ) {}

    public static function middleware(): array
    {
        return [
            ...(new WebsiteManageDefinition)->getMiddleware(),
            new Middleware('permission:view_agency_websites', only: ['data', 'showWebsite']),
            new Middleware('permission:edit_agency_websites', only: ['suspend', 'unsuspend', 'sync', 'retryProvision']),
            new Middleware('permission:delete_agency_websites', only: ['destroyWebsite', 'forceDeleteWebsite']),
            new Middleware('permission:restore_agency_websites', only: ['restoreWebsite']),
        ];
    }

    // ──────────────────────────────────────────────────────────
    // Show Override (richer detail view)
    // ──────────────────────────────────────────────────────────

    public function showWebsite(string|int $id): Response|JsonResponse|RedirectResponse
    {
        $website = AgencyWebsite::withTrashed()->with('owner')->findOrFail((int) $id);

        // Cross-module billing data
        $customer = $website->findCustomer();
        $subscription = $website->findSubscription();
        $subscription?->load(['plan', 'planPrice']);
        $invoices = $website->findInvoices();
        $payments = $website->findPayments();

        return Inertia::render('agency/admin/websites/show', [
            'website' => [
                'id' => $website->id,
                'name' => $website->name ?? $website->domain,
                'domain' => $website->domain,
                'status' => $website->status->value,
                'status_label' => $website->status->label(),
                'status_badge' => $website->status->badgeClass(),
                'type_label' => $website->type_label,
                'plan' => $website->plan,
                'site_id' => $website->site_id,
                'server_name' => $website->server_name,
                'astero_version' => $website->astero_version,
                'expired_on' => $website->expired_on?->toDateString(),
                'created_at' => $website->created_at?->toDateString(),
                'deleted_at' => $website->deleted_at?->toDateString(),
            ],
            'customer' => $customer ? [
                'name' => $customer->name,
                'email' => $customer->email,
                'company_name' => $customer->company_name,
            ] : null,
            'subscription' => $subscription ? [
                'plan_name' => $subscription->plan?->name ?? $website->plan ?? 'N/A',
                'formatted_price' => $subscription->planPrice
                    ? strtoupper((string) ($subscription->planPrice->currency ?? $subscription->currency)).' '.number_format((float) ($subscription->planPrice->price ?? $subscription->price), 2)
                    : null,
                'billing_cycle' => $subscription->planPrice->billing_cycle ?? $subscription->billing_cycle ?? 'month',
                'status_label' => $subscription->status_label,
                'status_class' => $subscription->status_class,
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

    // ──────────────────────────────────────────────────────────
    // Lifecycle Actions (delegated to service → Platform API)
    // ──────────────────────────────────────────────────────────

    /**
     * Suspend a website.
     *
     * POST /admin/websites/{id}/suspend
     */
    public function suspend(int $id): RedirectResponse|JsonResponse
    {
        $website = AgencyWebsite::query()->findOrFail($id);
        $result = $this->websiteManageService->suspendWebsite($website);

        if (request()->expectsJson()) {
            return response()->json($result, $result['status'] === 'success' ? 200 : 422);
        }

        return back()->with($result['status'], $result['message']);
    }

    /**
     * Unsuspend a website.
     *
     * POST /admin/websites/{id}/unsuspend
     */
    public function unsuspend(int $id): RedirectResponse|JsonResponse
    {
        $website = AgencyWebsite::query()->findOrFail($id);
        $result = $this->websiteManageService->unsuspendWebsite($website);

        if (request()->expectsJson()) {
            return response()->json($result, $result['status'] === 'success' ? 200 : 422);
        }

        return back()->with($result['status'], $result['message']);
    }

    /**
     * Sync website info from Platform.
     *
     * POST /admin/websites/{id}/sync
     */
    public function sync(int $id): RedirectResponse|JsonResponse
    {
        $website = AgencyWebsite::query()->findOrFail($id);
        $result = $this->websiteManageService->syncFromPlatform($website);

        if (request()->expectsJson()) {
            return response()->json($result, $result['status'] === 'success' ? 200 : 500);
        }

        return back()->with($result['status'], $result['message']);
    }

    /**
     * Retry failed provisioning.
     *
     * POST /admin/websites/{id}/retry-provision
     */
    public function retryProvision(int $id): RedirectResponse|JsonResponse
    {
        $website = AgencyWebsite::query()->findOrFail($id);

        if ($website->status !== WebsiteStatus::Failed) {
            $msg = 'Website is not in a failed state.';
            if (request()->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => $msg], 400);
            }

            return back()->with('error', $msg);
        }

        $result = $this->websiteManageService->retryProvision($website);

        if (request()->expectsJson()) {
            return response()->json($result, $result['status'] === 'success' ? 200 : 422);
        }

        return back()->with($result['status'], $result['message']);
    }

    /**
     * Override destroy to go through Platform API.
     */
    public function destroyWebsite(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $website = AgencyWebsite::query()->findOrFail((int) $id);
        $result = $this->websiteManageService->trashWebsite($website);

        if ($request->expectsJson()) {
            return response()->json($result, $result['status'] === 'success' ? 200 : 422);
        }

        return to_route('agency.admin.websites.index', 'all')
            ->with($result['status'], $result['message']);
    }

    /**
     * Permanently delete a trashed website.
     */
    public function forceDeleteWebsite(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $website = AgencyWebsite::withTrashed()->findOrFail((int) $id);
        $result = $this->websiteManageService->forceDeleteWebsite($website);

        if ($request->expectsJson()) {
            return response()->json($result, $result['status'] === 'success' ? 200 : 422);
        }

        return to_route('agency.admin.websites.index', 'trash')
            ->with($result['status'], $result['message']);
    }

    /**
     * Override restore to go through Platform API.
     */
    public function restoreWebsite(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $website = AgencyWebsite::withTrashed()->findOrFail((int) $id);
        $result = $this->websiteManageService->restoreWebsite($website);

        if ($request->expectsJson()) {
            return response()->json($result, $result['status'] === 'success' ? 200 : 422);
        }

        return to_route('agency.admin.websites.index', 'all')
            ->with($result['status'], $result['message']);
    }

    protected function service(): WebsiteManageService
    {
        return $this->websiteManageService;
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'typeOptions' => $this->websiteManageService->getTypeOptions(),
            'statusOptions' => $this->websiteManageService->getStatusOptions(),
        ];
    }
}
