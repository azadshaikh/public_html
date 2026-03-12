<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\GroupDefinition;
use App\Http\Resources\GroupResource;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GroupService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new GroupDefinition;
    }

    // Form select options
    public function getStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ];
    }

    protected function getResourceClass(): ?string
    {
        return GroupResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
            'deletedBy:id,first_name,last_name',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        // Add withCount for items
        $query->withCount('items');
    }

    protected function prepareCreateData(array $data): array
    {
        // Set default status if not provided
        $data['status'] ??= 'active';

        return $data;
    }
}
