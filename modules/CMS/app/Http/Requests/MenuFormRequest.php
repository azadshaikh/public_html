<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Modules\CMS\Definitions\MenuDefinition;

class MenuFormRequest extends ScaffoldRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'location' => [
                'nullable',
                'string',
                'max:100',
                $this->uniqueRule('location'),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The menu name is required.',
            'name.max' => 'The menu name cannot exceed 255 characters.',
            'location.unique' => 'This location is already assigned to another menu.',
            'location.max' => 'The location cannot exceed 100 characters.',
            'description.max' => 'The description cannot exceed 500 characters.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'menu name',
            'location' => 'menu location',
            'description' => 'description',
            'is_active' => 'status',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new MenuDefinition;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->prepareBooleanField('is_active');
        $this->trimField('name');
        $this->trimField('slug');
    }
}
