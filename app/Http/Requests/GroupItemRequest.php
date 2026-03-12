<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Definitions\GroupItemDefinition;
use App\Models\GroupItem;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;

class GroupItemRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'group_id' => ['required', 'exists:groups,id'],
            'name' => ['required', 'string', 'max:125'],
            'value' => ['nullable', 'string', 'max:125'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new GroupItemDefinition;
    }

    protected function getModelClass(): string
    {
        return GroupItem::class;
    }

    protected function prepareForValidation(): void
    {
        $this->trimField('name');
        $this->trimField('value');
        $this->prepareBooleanField('is_default');

        // Get group_id from route if not in request
        if (! $this->has('group_id') && $this->route('group')) {
            $this->merge(['group_id' => $this->route('group')]);
        }
    }
}
