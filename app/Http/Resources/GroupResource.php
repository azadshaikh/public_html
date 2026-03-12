<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\GroupDefinition;
use App\Models\Group;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use RuntimeException;

class GroupResource extends ScaffoldResource
{
    protected function definition(): ScaffoldDefinition
    {
        return new GroupDefinition;
    }

    protected function customFields(): array
    {
        $group = $this->group();

        return [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', ['id' => $group->getKey()]),
            'items_count' => $group->getAttribute('items_count') ?? 0,
        ];
    }

    private function group(): Group
    {
        throw_unless($this->resource instanceof Group, RuntimeException::class, 'GroupResource expects a Group model instance.');

        return $this->resource;
    }
}
