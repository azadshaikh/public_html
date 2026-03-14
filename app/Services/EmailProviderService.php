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

    public function getPaginatedEmailProviders(Request $request): array
    {
        $query = $this->buildListQuery($request);
        $paginator = $query->paginate($this->getPerPage($request))->onEachSide(1);
        $paginatedArray = $paginator->toArray();

        $paginatedArray['data'] = EmailProviderResource::collection($paginator->items())
            ->resolve(request());

        return $paginatedArray;
    }

    // ================================================================
    // CUSTOM OPTIONS (for forms)
    // ================================================================

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ];
    }

    public function getEncryptionOptions(): array
    {
        return [
            ['value' => 'tls', 'label' => 'TLS'],
            ['value' => 'ssl', 'label' => 'SSL'],
            ['value' => 'none', 'label' => 'None'],
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

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($createdAt = $request->input('created_at')) {
            $dates = explode(',', $createdAt, 2);

            if (! empty($dates[0])) {
                $query->whereDate('created_at', '>=', $dates[0]);
            }

            if (! empty($dates[1])) {
                $query->whereDate('created_at', '<=', $dates[1]);
            }

            return;
        }

        if ($from = $request->input('created_at_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->input('created_at_to')) {
            $query->whereDate('created_at', '<=', $to);
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
        $data['smtp_encryption'] = ($data['smtp_encryption'] ?? 'none') === 'none'
            ? null
            : $data['smtp_encryption'];

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        // Only update password if provided
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        }

        if (array_key_exists('smtp_encryption', $data)) {
            $data['smtp_encryption'] = $data['smtp_encryption'] === 'none'
                ? null
                : $data['smtp_encryption'];
        }

        return $data;
    }
}
