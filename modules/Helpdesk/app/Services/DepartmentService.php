<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Enums\Status;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Modules\Helpdesk\Definitions\DepartmentDefinition;
use Modules\Helpdesk\Http\Resources\DepartmentResource;

class DepartmentService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new DepartmentDefinition;
    }

    public function getDepartmentHeadOptions(): array
    {
        return User::getActiveUsersForSelect();
    }

    public function getVisibilityOptions(): array
    {
        return config('helpdesk.visibility_options', []);
    }

    public function getStatusOptions(): array
    {
        return [
            ['label' => 'Active', 'value' => Status::ACTIVE->value],
            ['label' => 'Inactive', 'value' => Status::INACTIVE->value],
        ];
    }

    protected function getResourceClass(): ?string
    {
        return DepartmentResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'departmentHead:id,first_name,last_name,name',
            'createdBy:id,first_name,last_name,name',
            'updatedBy:id,first_name,last_name,name',
        ];
    }
}
