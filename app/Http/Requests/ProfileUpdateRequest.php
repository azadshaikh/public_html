<?php

namespace App\Http\Requests;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users')->ignore(auth()->user()->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'in:male,female,other'],
            'status' => ['required', 'string', 'in:active,suspended,banned'],

            // Password fields (optional for updates)
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],

            // Address fields
            'address1' => ['nullable', 'string', 'max:255'],
            'address2' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'state' => ['nullable', 'string', 'max:255'],
            'state_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:255'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'zip' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'tagline' => ['nullable', 'string', 'max:255'],

            // Metadata fields
            'birth_date' => ['nullable', 'date', 'before:today'],
            'website_url' => ['nullable', 'url', 'regex:/^https?:\/\/.+/i', 'max:500'],
            'twitter_url' => ['nullable', 'url', 'regex:/^https?:\/\/(www\.)?(twitter\.com|x\.com)/i', 'max:500'],
            'facebook_url' => ['nullable', 'url', 'regex:/^https?:\/\/(www\.)?facebook\.com/i', 'max:500'],
            'instagram_url' => ['nullable', 'url', 'regex:/^https?:\/\/(www\.)?instagram\.com/i', 'max:500'],
            'linkedin_url' => ['nullable', 'url', 'regex:/^https?:\/\/(www\.)?linkedin\.com/i', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:2048'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'phone' => 'phone',
            'birth_date' => 'birth date',
            'tagline' => 'tagline',
            'website_url' => 'website URL',
            'twitter_url' => 'X (Twitter) URL',
            'facebook_url' => 'Facebook URL',
            'instagram_url' => 'Instagram URL',
            'linkedin_url' => 'LinkedIn URL',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
            'username.unique' => 'This username is already taken. Please choose another.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'birth_date.before' => 'Please enter a valid birth date.',
            'gender.in' => 'Please select a valid gender.',
            'website_url.regex' => 'Please enter a valid website URL (must start with http:// or https://).',
            'twitter_url.regex' => 'Please enter a valid X (Twitter) profile URL.',
            'facebook_url.regex' => 'Please enter a valid Facebook profile URL.',
            'instagram_url.regex' => 'Please enter a valid Instagram profile URL.',
            'linkedin_url.regex' => 'Please enter a valid LinkedIn profile URL.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $status = $user->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? '');

        // Set email and status from current user since profile updates can't change these
        $this->merge([
            'email' => $user->email,
            'status' => $statusValue,
        ]);

        $primaryAddress = $user->primaryAddress;

        $this->merge([
            'address1' => $primaryAddress?->getAttribute('address1'),
            'address2' => $primaryAddress?->getAttribute('address2'),
            'country' => $primaryAddress?->getAttribute('country'),
            'country_code' => $primaryAddress?->getAttribute('country_code'),
            'state' => $primaryAddress?->getAttribute('state'),
            'state_code' => $primaryAddress?->getAttribute('state_code'),
            'city' => $primaryAddress?->getAttribute('city'),
            'city_code' => $primaryAddress?->getAttribute('city_code'),
            'zip' => $primaryAddress?->getAttribute('zip'),
            'phone' => $this->input('phone')
                ?? $primaryAddress?->getAttribute('phone')
                ?? $user->getAttribute('phone'),
            'gender' => $user->gender,
            'birth_date' => $user->getBirthDate(),
        ]);

        if (function_exists('module_enabled') && module_enabled('CMS')) {
            $this->merge([
                'tagline' => $user->tagline,
                'bio' => $user->bio,
                'website_url' => $user->getWebsiteUrl(),
                'twitter_url' => $user->getTwitterUrl(),
                'facebook_url' => $user->getFacebookUrl(),
                'instagram_url' => $user->getInstagramUrl(),
                'linkedin_url' => $user->getLinkedinUrl(),
            ]);
        }

        // Trim whitespace from string fields
        $this->merge([
            'first_name' => trim($this->first_name ?? ''),
            'last_name' => trim($this->last_name ?? ''),
            'username' => trim($this->username ?? ''),
            'tagline' => trim($this->tagline ?? ''),
            'bio' => trim($this->bio ?? ''),
            'website_url' => trim($this->website_url ?? ''),
            'twitter_url' => trim($this->twitter_url ?? ''),
            'facebook_url' => trim($this->facebook_url ?? ''),
            'instagram_url' => trim($this->instagram_url ?? ''),
            'linkedin_url' => trim($this->linkedin_url ?? ''),
        ]);
    }
}
