<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\EmailProviderDefinition;
use App\Http\Resources\EmailProviderResource;
use App\Models\EmailProvider;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EmailProviderService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new EmailProviderDefinition;
    }

    // ================================================================
    // STATISTICS (for tab counts)
    // ================================================================

    public function getStatistics(): array
    {
        return [
            'total' => EmailProvider::query()->whereNull('deleted_at')->count(),
            'active' => EmailProvider::query()->where('status', 'active')->whereNull('deleted_at')->count(),
            'inactive' => EmailProvider::query()->where('status', 'inactive')->whereNull('deleted_at')->count(),
            'trash' => EmailProvider::onlyTrashed()->count(),
        ];
    }

    // ================================================================
    // CUSTOM OPTIONS (for forms)
    // ================================================================

    public function getStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }

    public function getEncryptionOptions(): array
    {
        return [
            'tls' => 'TLS',
            'ssl' => 'SSL',
            '' => 'None',
        ];
    }

    protected function getResourceClass(): ?string
    {
        return EmailProviderResource::class;
    }

    // ================================================================
    // EAGER LOADING
    // ================================================================

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    // ================================================================
    // STATUS FILTER OVERRIDE
    // The base Scaffoldable::applyFilters() handles Definition filters.
    // We only override applyStatusFilter() to handle navigation values.
    // ================================================================

    /**
     * Apply status filter to query
     * Override to properly handle navigation values vs actual status filters
     */
    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->input('status') ?? $request->route('status') ?? 'all';

        // Skip navigation values - 'all' shows everything, 'trash' is handled by buildListQuery
        if (in_array($status, ['all', 'trash'])) {
            return;
        }

        // Apply specific status filter (active/inactive)
        if (in_array($status, ['active', 'inactive'])) {
            $query->where('status', $status);
        }
    }

    // ================================================================
    // DATA PREPARATION
    // ================================================================

    protected function prepareCreateData(array $data): array
    {
        // Set defaults
        $data['status'] ??= 'active';
        $data['order'] ??= 0;

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        // Only update password if provided
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        }

        return $data;
    }
}
