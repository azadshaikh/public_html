<?php

namespace Modules\Platform\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /api/platform/v1/websites/{siteId}/plan.
 *
 * Semantics: top-level key replacement.
 * - Keys present in the request replace the corresponding stored value wholesale.
 * - Keys absent from the request are left unchanged.
 * - An empty body {} is rejected — at least one key must be provided.
 *
 * All plan fields are individually nullable so a key can be explicitly cleared by
 * passing null.  The empty-body guard is enforced in withValidator().
 */
class UpdatePlanRequest extends FormRequest
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
            'ref' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'quotas' => ['nullable', 'array'],
            'quotas.storage_mb' => ['nullable', 'integer', 'min:0'],
            'quotas.bandwidth_mb' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
        ];
    }

    /**
     * Reject requests with no keys supplied at all.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $keys = ['ref', 'name', 'quotas', 'features'];
            $hasAny = collect($keys)->contains(fn (string $k) => $this->has($k));

            if (! $hasAny) {
                $v->errors()->add('_body', 'Request body must contain at least one plan field to update.');
            }
        });
    }
}
