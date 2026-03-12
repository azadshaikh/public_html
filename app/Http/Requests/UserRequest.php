<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Definitions\UserDefinition;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;

class UserRequest extends ScaffoldRequest
{
    // ================================================================
    // VALIDATION RULES
    // ================================================================

    public function rules(): array
    {
        return [
            // ================================================================
            // ALWAYS REQUIRED FIELDS
            // ================================================================
            'email' => [
                'required',
                'email:rfc,dns',
                'max:255',
                $this->uniqueRule('email'),
            ],

            // ================================================================
            // OPTIONAL FIELDS (but required structure if provided)
            // ================================================================
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'username' => [
                'nullable',
                'string',
                'max:255',
                $this->uniqueRule('username'),
                'regex:/^[a-zA-Z0-9_-]+$/',
            ],

            // ================================================================
            // CONDITIONALLY OPTIONAL (password - required on create)
            // ================================================================
            'password' => $this->isUpdate()
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8', 'confirmed'],

            // ================================================================
            // PERSONAL INFORMATION
            // ================================================================
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'string', 'max:500'],

            // ================================================================
            // ADDRESS FIELDS
            // ================================================================
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:255'],
            'state_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'zip' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],

            // ================================================================
            // STATUS & ROLES
            // ================================================================
            'status' => ['required', 'string', 'in:active,pending,suspended,banned'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],

            // ================================================================
            // SOCIAL URLS
            // ================================================================
            'website_url' => ['nullable', 'url', 'regex:/^https?:\/\/.+/i', 'max:500'],
            'twitter_url' => ['nullable', 'url', 'regex:/^https?:\/\/(www\.)?(twitter\.com|x\.com)/i', 'max:500'],
            'facebook_url' => ['nullable', 'url', 'regex:/^https?:\/\/(www\.)?facebook\.com/i', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'regex:/^https?:\/\/(www\.)?instagram\.com/i', 'max:500'],
            'linkedin_url' => ['nullable', 'url', 'regex:/^https?:\/\/(www\.)?linkedin\.com/i', 'max:500'],
        ];
    }

    // ================================================================
    // FRIENDLY FIELD NAMES (for error messages)
    // ================================================================

    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'phone_code' => 'phone code',
            'birth_date' => 'birth date',
            'tagline' => 'tag line',
            'website_url' => 'website URL',
            'twitter_url' => 'X (Twitter) URL',
            'facebook_url' => 'Facebook URL',
            'instagram_url' => 'Instagram URL',
            'linkedin_url' => 'LinkedIn URL',
            'country_code' => 'country',
            'state_code' => 'state/province',
            'city_code' => 'city',
        ];
    }

    // ================================================================
    // CUSTOM VALIDATION MESSAGES
    // ================================================================

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
            'email.required' => 'Email address is required.',
            'username.unique' => 'This username is already taken. Please choose another.',
            'username.regex' => 'Username can only contain letters, numbers, underscores, and hyphens.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'birth_date.before' => 'Please enter a valid birth date.',
            'gender.in' => 'Please select a valid gender.',
            'status.in' => 'Please select a valid status.',
            'status.required' => 'Status is required.',
            'website_url.regex' => 'Please enter a valid website URL (must start with http:// or https://).',
            'twitter_url.regex' => 'Please enter a valid X (Twitter) profile URL.',
            'facebook_url.regex' => 'Please enter a valid Facebook profile URL.',
            'instagram_url.regex' => 'Please enter a valid Instagram profile URL.',
            'linkedin_url.regex' => 'Please enter a valid LinkedIn profile URL.',
        ];
    }

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    protected function definition(): ScaffoldDefinition
    {
        return new UserDefinition;
    }

    protected function getModelClass(): string
    {
        return User::class;
    }

    // ================================================================
    // PREPARE DATA BEFORE VALIDATION
    // ================================================================

    protected function prepareForValidation(): void
    {
        // Trim whitespace from string fields
        $this->trimField('first_name');
        $this->trimField('last_name');
        $this->trimField('username');
        $this->trimField('tagline');
        $this->trimField('bio');
        $this->trimField('avatar');
        $this->trimField('website_url');
        $this->trimField('twitter_url');
        $this->trimField('facebook_url');
        $this->trimField('instagram_url');
        $this->trimField('linkedin_url');

        // Lowercase email
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->email ?? '')),
            ]);
        }
    }
}
