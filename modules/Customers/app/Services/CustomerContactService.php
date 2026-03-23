<?php

declare(strict_types=1);

namespace Modules\Customers\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Modules\Customers\Definitions\CustomerContactDefinition;
use Modules\Customers\Http\Resources\CustomerContactResource;

class CustomerContactService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new CustomerContactDefinition;
    }

    protected function getResourceClass(): ?string
    {
        return CustomerContactResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'customer:id,company_name',
        ];
    }
}
