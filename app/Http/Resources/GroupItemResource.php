<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\GroupItemDefinition;
use App\Models\GroupItem;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use Exception;
use RuntimeException;

class GroupItemResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        $groupId = $this->groupId();

        // Pass group_id for nested route generation
        return new GroupItemDefinition($groupId);
    }

    protected function customFields(): array
    {
        $groupItem = $this->groupItem();
        $group = $groupItem->relationLoaded('group') ? $groupItem->getRelation('group') : null;
        $groupId = $this->groupId();
        $isDefault = (bool) $groupItem->getAttribute('is_default');

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', [
                'group' => $groupId,
                'id' => $groupItem->getKey(),
            ]),
            'group_name' => $this->whenLoaded('group', fn () => $group?->getAttribute('name')),
            'is_default_label' => $isDefault ? 'Yes' : 'No',
            'is_default_class' => $isDefault ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary',
        ];
    }

    /**
     * Override getActions to handle nested route parameters
     */
    protected function getActions(): array
    {
        $groupItem = $this->groupItem();
        $groupId = $this->groupId();
        $isTrashed = $groupItem->getAttribute('deleted_at') !== null;
        $status = $isTrashed ? 'trash' : 'all';
        $id = $groupItem->getKey();

        $definedActions = collect($this->scaffold()->actions())
            ->filter(fn ($action): bool => $action->authorized())
            ->filter(fn ($action): bool => $action->isForRow())
            ->filter(fn ($action): bool => $action->shouldShow($status));

        $actions = [];

        foreach ($definedActions as $action) {
            $actionData = $action->toArray();
            $key = $actionData['key'];

            $url = null;
            if (! empty($actionData['route'])) {
                try {
                    // For nested routes, pass both group and id parameters
                    $url = route($actionData['route'], [
                        'group' => $groupId,
                        'id' => $id,
                    ]);
                } catch (Exception) {
                    continue;
                }
            }

            $actions[$key] = [
                'url' => $url,
                'label' => $actionData['label'],
                'icon' => $actionData['icon'] ?? null,
                'method' => $actionData['method'] ?? 'GET',
                'confirm' => $actionData['confirm'] ?? null,
                'variant' => $actionData['variant'] ?? 'default',
                'fullReload' => $actionData['fullReload'] ?? false,
                'attributes' => $actionData['attributes'] ?? null,
            ];

            if (($actionData['variant'] ?? null) === 'danger') {
                $actions[$key]['danger'] = true;
            }
        }

        return $actions;
    }

    private function groupItem(): GroupItem
    {
        throw_unless($this->resource instanceof GroupItem, RuntimeException::class, 'GroupItemResource expects a GroupItem model instance.');

        return $this->resource;
    }

    private function groupId(): ?int
    {
        $groupId = $this->groupItem()->getAttribute('group_id');

        return $groupId === null ? null : (int) $groupId;
    }
}
