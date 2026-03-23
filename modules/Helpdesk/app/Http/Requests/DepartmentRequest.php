<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Modules\Helpdesk\Definitions\DepartmentDefinition;

class DepartmentRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueRule('name')],
            'description' => ['nullable', 'string'],
            'department_head' => ['required', $this->existsRule('users', 'id')],
            'visibility' => ['required', 'in:public,private'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new DepartmentDefinition;
    }
}
