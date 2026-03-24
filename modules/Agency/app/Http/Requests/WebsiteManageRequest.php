<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Modules\Agency\Definitions\WebsiteManageDefinition;
use Modules\Agency\Models\AgencyWebsite;

class WebsiteManageRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            // Website identity
            'name' => ['required', 'string', 'max:255'],

            // Business data (Agency-managed)
            'type' => ['required', 'string', 'in:trial,free,paid,internal,special'],
            'plan' => ['nullable', 'string', 'max:100'],
            'expired_on' => ['nullable', 'date'],

            // Customer info
            'customer_ref' => ['nullable', 'string', 'max:255'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_company' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],

            // Plan info
            'plan_ref' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Website Name',
            'type' => 'Website Type',
            'plan' => 'Plan',
            'expired_on' => 'Expiry Date',
            'customer_ref' => 'Customer Reference',
            'customer_name' => 'Customer Name',
            'customer_email' => 'Customer Email',
            'customer_company' => 'Customer Company',
            'customer_phone' => 'Customer Phone',
            'plan_ref' => 'Plan Reference',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new WebsiteManageDefinition;
    }

    protected function getModelClass(): string
    {
        return AgencyWebsite::class;
    }
}
