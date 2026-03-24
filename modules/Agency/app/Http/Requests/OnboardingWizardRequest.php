<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingWizardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'goal' => ['nullable', 'string', 'max:150'],
            'plan' => ['nullable', Rule::in(['starter', 'pro', 'business'])],
            'team_size' => ['nullable', Rule::in(['solo', 'small', 'medium', 'enterprise'])],
            'launch_timeline' => ['nullable', Rule::in(['immediately', '30_days', '60_days', '90_days_plus'])],
            'preferred_domain' => ['nullable', 'string', 'max:255'],
            'contact_first_name' => ['nullable', 'string', 'max:255'],
            'contact_last_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'opt_in_marketing' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.in' => 'Please select a valid plan.',
            'team_size.in' => 'Please select a valid team size.',
            'launch_timeline.in' => 'Please select a valid launch timeline.',
        ];
    }
}
