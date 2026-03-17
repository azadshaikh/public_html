<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Platform\Definitions\TldDefinition;
use Modules\Platform\Http\Resources\TldResource;
use Modules\Platform\Models\Tld;

class TldService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new TldDefinition;
    }

    public function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('tld')) {
            $search = $this->escapeLike($request->string('tld')->toString());
            $query->where('tld', 'ilike', sprintf('%%%s%%', $search));
        }
    }

    public function getStatistics(): array
    {
        return [
            'total' => Tld::query()->count(),
            'active' => Tld::query()->where('status', true)->count(),
            'inactive' => Tld::query()->where('status', false)->count(),
            'trash' => Tld::onlyTrashed()->count(),
        ];
    }

    protected function getResourceClass(): ?string
    {
        return TldResource::class;
    }

    protected function prepareCreateData(array $data): array
    {
        return $this->prepareData($data);
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareData($data);
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $status = $request->input('status');

        if ($status === 'active') {
            $query->where('status', true);
        } elseif ($status === 'inactive') {
            $query->where('status', false);
        }
    }

    private function prepareData(array $data): array
    {
        return [
            'tld' => $data['tld'] ?? null,
            'whois_server' => $data['whois_server'] ?? null,
            'pattern' => $data['pattern'] ?? null,
            'is_main' => $data['is_main'] ?? false,
            'is_suggested' => $data['is_suggested'] ?? false,
            'price' => $data['price'] ?? null,
            'sale_price' => $data['sale_price'] ?? null,
            'affiliate_link' => $data['affiliate_link'] ?? null,
            'status' => isset($data['status']) ? (bool) $data['status'] : true,
            'tld_order' => $data['tld_order'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
