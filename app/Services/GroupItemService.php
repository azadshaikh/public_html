<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Definitions\GroupItemDefinition;
use App\Http\Resources\GroupItemResource;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GroupItemService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new GroupItemDefinition($this->getGroupId());
    }

    // Form select options (associative array for legacy view compatibility)
    public function getStatusOptions(): array
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }

    /**
     * Get group ID from route parameter
     */
    protected function getGroupId(): ?int
    {
        $groupId = request()->route('group');

        return $groupId ? (int) $groupId : null;
    }

    protected function getResourceClass(): ?string
    {
        return GroupItemResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'group:id,name',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
            'deletedBy:id,first_name,last_name',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        // Filter by group ID if provided in request
        if ($request->has('group_id')) {
            $query->where('group_id', $request->input('group_id'));
        }

        // Filter by group from route parameter
        $groupId = $request->route('group');
        if ($groupId) {
            $query->where('group_id', $groupId);
        }
    }

    protected function prepareCreateData(array $data): array
    {
        // Set default status if not provided
        $data['status'] ??= 'active';

        // Set is_default to false if not provided
        $data['is_default'] ??= false;

        return $data;
    }

    protected function prepareUpdateData(array $data): array
    {
        // Ensure is_default is boolean
        if (isset($data['is_default'])) {
            $data['is_default'] = (bool) $data['is_default'];
        }

        return $data;
    }

    /**
     * Override empty state config to include group parameter in nested route
     */
    protected function getEmptyStateConfig(): array
    {
        $groupId = $this->getGroupId();

        return [
            'icon' => 'ri-inbox-line',
            'title' => sprintf('No %s Found', $this->getEntityPlural()),
            'message' => sprintf('There are no %s to display.', $this->getEntityPlural()),
            'action' => [
                'label' => 'Create '.$this->getEntityName(),
                'url' => route($this->scaffold()->getCreateRoute(), ['group' => $groupId]),
            ],
        ];
    }
}
