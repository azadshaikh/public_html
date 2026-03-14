<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\EmailTemplateDefinition;
use App\Http\Resources\EmailTemplateResource;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class EmailTemplateService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new EmailTemplateDefinition;
    }

    public function getStatistics(): array
    {
        return [
            'total' => EmailTemplate::query()->whereNull('deleted_at')->count(),
            'active' => EmailTemplate::query()->where('status', 'active')->whereNull('deleted_at')->count(),
            'inactive' => EmailTemplate::query()->where('status', 'inactive')->whereNull('deleted_at')->count(),
            'trash' => EmailTemplate::onlyTrashed()->count(),
        ];
    }

    public function getPaginatedEmailTemplates(Request $request): array
    {
        $query = $this->buildListQuery($request);
        $paginator = $query->paginate($this->getPerPage($request))->onEachSide(1);
        $paginatedArray = $paginator->toArray();

        $paginatedArray['data'] = EmailTemplateResource::collection($paginator->items())
            ->resolve(request());

        return $paginatedArray;
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ];
    }

    public function getProviderOptions(): array
    {
        return EmailProvider::getActiveProvidersForSelect();
    }

    protected function getResourceClass(): ?string
    {
        return EmailTemplateResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'provider:id,name',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function buildListQuery(Request $request): Builder
    {
        $query = EmailTemplate::query();

        $status = $request->input('status') ?? $request->route('status') ?? 'all';
        $status = in_array($status, ['all', 'active', 'inactive', 'trash'], true) ? $status : 'all';

        if ($status === 'trash') {
            $query->onlyTrashed();
        } elseif ($status !== 'all') {
            $query->where('status', $status)->whereNull('deleted_at');
        } else {
            $query->whereNull('deleted_at');
        }

        if (! $request->has('status') && $request->route('status')) {
            $request->merge(['status' => $status]);
        }

        $this->applyEagerLoading($query);
        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);
        $this->customizeListQuery($query, $request);

        return $query;
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->input('provider_id'));
        }

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

    protected function prepareCreateData(array $data): array
    {
        $allowedStatuses = ['active', 'inactive'];
        $data['status'] = in_array($data['status'] ?? null, $allowedStatuses, true)
            ? $data['status']
            : 'active';
        $data['is_raw'] = (bool) ($data['is_raw'] ?? false);

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        $allowedStatuses = ['active', 'inactive'];
        if (isset($data['status']) && ! in_array($data['status'], $allowedStatuses, true)) {
            $data['status'] = 'active';
        }

        $data['is_raw'] = (bool) ($data['is_raw'] ?? false);

        return $data;
    }

    protected function afterCreate(Model $model, array $data): void
    {
        if (method_exists($model, 'saveAddress')) {
            $model->saveAddress($data);
        }
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        if (method_exists($model, 'saveAddress')) {
            $model->saveAddress($data);
        }
    }
}
