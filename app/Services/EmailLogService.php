<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\EmailLogDefinition;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EmailLogService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new EmailLogDefinition;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => EmailLog::query()->whereNull('deleted_at')->count(),
            'sent' => EmailLog::query()->where('status', EmailLog::STATUS_SENT)->whereNull('deleted_at')->count(),
            'failed' => EmailLog::query()->where('status', EmailLog::STATUS_FAILED)->whereNull('deleted_at')->count(),
            'queued' => EmailLog::query()->where('status', EmailLog::STATUS_QUEUED)->whereNull('deleted_at')->count(),
        ];
    }

    // ================================================================
    // CUSTOM OPTIONS (for form filters)
    // ================================================================

    public function getProviderOptions(): array
    {
        return EmailProvider::query()
            ->select('id', 'name')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(fn ($item): array => [
                'value' => (string) $item->id,
                'label' => $item->name,
            ])
            ->all();
    }

    public function getTemplateOptions(): array
    {
        return EmailTemplate::query()
            ->select('id', 'name')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get()
            ->map(fn ($item): array => [
                'value' => (string) $item->id,
                'label' => $item->name,
            ])
            ->all();
    }

    protected function getResourceClass(): ?string
    {
        return EmailLogResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'template:id,name',
            'provider:id,name',
            'sender:id,first_name,last_name',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    // ================================================================
    // ⚠️ CRITICAL: OVERRIDE FOR STATUS TAB SUPPORT
    // ================================================================

    protected function buildListQuery(Request $request): Builder
    {
        $query = EmailLog::query();

        // ⚠️ CRITICAL: Check BOTH query param AND route parameter
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Handle status filtering
        if ($status === 'sent') {
            $query->where('status', EmailLog::STATUS_SENT)->whereNull('deleted_at');
        } elseif ($status === 'failed') {
            $query->where('status', EmailLog::STATUS_FAILED)->whereNull('deleted_at');
        } elseif ($status === 'queued') {
            $query->where('status', EmailLog::STATUS_QUEUED)->whereNull('deleted_at');
        } else {
            // 'all' - only non-deleted
            $query->whereNull('deleted_at');
        }

        // ⚠️ CRITICAL: Merge route status into request for filters
        if (! $request->has('status') && $request->route('status')) {
            $request->merge(['status' => $status]);
        }

        // Apply standard scaffold methods
        $this->applyEagerLoading($query);
        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);
        $this->customizeListQuery($query, $request);

        return $query;
    }

    // ================================================================
    // CUSTOM FILTER HANDLING
    // ================================================================

    protected function applyFilters(Builder $query, Request $request): void
    {
        // Provider filter
        if ($providerId = $request->input('email_provider_id')) {
            $query->where('email_provider_id', $providerId);
        }

        // Template filter
        if ($templateId = $request->input('email_template_id')) {
            $query->where('email_template_id', $templateId);
        }

        // Date range filter
        if ($from = $request->input('sent_at_from')) {
            $query->whereDate('sent_at', '>=', $from);
        }

        if ($to = $request->input('sent_at_to')) {
            $query->whereDate('sent_at', '<=', $to);
        }
    }

    // ================================================================
    // EMPTY STATE CONFIGURATION (Override to disable create action)
    // ================================================================

    /**
     * Get empty state configuration
     * Email logs are read-only, so no create button
     */
    protected function getEmptyStateConfig(): array
    {
        return [
            'icon' => 'ri-mail-close-line',
            'title' => 'No Email Logs Found',
            'message' => 'Email activity will appear here once messages are sent from the system.',
            'showAddButton' => false,
        ];
    }
}
