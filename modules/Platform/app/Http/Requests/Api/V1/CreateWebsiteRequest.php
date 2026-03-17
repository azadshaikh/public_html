<?php

namespace Modules\Platform\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the POST /api/platform/v1/websites request.
 *
 * Customer and plan objects are optional — websites can be created without
 * customer association (agency-managed, demo, internal).
 */
class CreateWebsiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Core website fields
            'domain' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:trial,paid'],
            'plan_tier' => ['nullable', 'string', 'in:free,trial,basic,pro,enterprise'],
            'is_www' => ['boolean'],
            'dns_mode' => ['nullable', 'string', 'in:subdomain,managed,external'],
            'skip_dns' => ['boolean'],
            'skip_cdn' => ['boolean'],
            'skip_ssl_issue' => ['boolean'],
            'primary_category_id' => ['nullable', 'integer'],
            'sub_category_id' => ['nullable', 'integer'],

            // Customer snapshot (all optional, stored in customer_data JSON)
            'customer' => ['nullable', 'array'],
            'customer.ref' => ['nullable', 'string', 'max:255'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.company' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],

            // Plan snapshot (all optional, stored in plan_data JSON)
            'plan' => ['nullable', 'array'],
            'plan.ref' => ['nullable', 'string', 'max:255'],
            'plan.name' => ['nullable', 'string', 'max:255'],
            'plan.quotas' => ['nullable', 'array'],
            'plan.quotas.storage_mb' => ['nullable', 'integer', 'min:0'],
            'plan.quotas.bandwidth_mb' => ['nullable', 'integer', 'min:0'],
            'plan.features' => ['nullable', 'array'],
        ];
    }
}
