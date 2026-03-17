<?php

namespace Modules\Platform\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates PATCH /api/platform/v1/websites/{siteId}/customer.
 *
 * Semantics: top-level key replacement.
 * - Keys present in the request replace the corresponding stored value wholesale.
 * - Keys absent from the request are left unchanged.
 * - An empty body {} is rejected — at least one key must be provided.
 *
 * Individual fields may be set to null to explicitly clear them.
 * Passing null for ref removes the customer association from the website entirely.
 */
class UpdateCustomerRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Reject requests with no keys supplied at all.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $keys = ['ref', 'email', 'name', 'company', 'phone'];
            $hasAny = collect($keys)->contains(fn (string $k) => $this->has($k));

            if (! $hasAny) {
                $v->errors()->add('_body', 'Request body must contain at least one customer field to update.');
            }
        });
    }
}
