<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Definitions\GroupDefinition;
use App\Models\Group;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;

class GroupRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:125'],
            'slug' => [
                'nullable',
                'string',
                'max:125',
                'regex:/^[a-z0-9-]+$/',
                $this->uniqueRule('slug'),
            ],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new GroupDefinition;
    }

    protected function getModelClass(): string
    {
        return Group::class;
    }

    protected function prepareForValidation(): void
    {
        $this->trimField('name');
        $this->trimField('slug');
    }
}
