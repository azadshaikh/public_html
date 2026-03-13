<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Definitions\RoleDefinition;
use App\Models\Role;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;

class RoleRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                $this->uniqueRule('name'),
            ],
            'display_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9\s]+$/',
                $this->uniqueRule('display_name'),
            ],
            'guard_name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Role name can only contain lowercase letters, numbers, and underscores.',
            'name.unique' => 'This role name is already taken.',
            'display_name.unique' => 'This display name is already taken.',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new RoleDefinition;
    }

    protected function getModelClass(): string
    {
        return Role::class;
    }

    protected function prepareForValidation(): void
    {
        $this->trimField('name');
        $this->trimField('display_name');

        if ($this->has('name') && ! empty($this->name)) {
            $this->merge([
                'name' => strtolower((string) $this->name),
            ]);
        }

        if (! $this->has('guard_name') || empty($this->guard_name)) {
            $this->merge([
                'guard_name' => 'web',
            ]);
        }

        if (! $this->has('status') || empty($this->status)) {
            $this->merge([
                'status' => 'active',
            ]);
        }
    }
}
