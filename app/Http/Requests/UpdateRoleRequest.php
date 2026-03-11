<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('edit_roles') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where('guard_name', 'web'),
            ],
            'display_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'display_name')->ignore($role->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Role names may only contain lowercase letters, numbers, and underscores.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim(strtolower($this->input('name'))) : $this->input('name'),
            'display_name' => is_string($this->input('display_name')) ? trim($this->input('display_name')) : $this->input('display_name'),
            'description' => is_string($this->input('description')) ? trim($this->input('description')) : $this->input('description'),
        ]);
    }
}
