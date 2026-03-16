<?php

namespace Modules\CMS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDefaultPagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_default_pages') ?? false;
    }

    public function rules(): array
    {
        return [
            'home_page' => ['nullable', 'integer', 'exists:cms_posts,id'],
            'blogs_page' => ['nullable', 'integer', 'exists:cms_posts,id'],
            'blog_base_url' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9-]*$/'],
            'contact_page' => ['nullable', 'integer', 'exists:cms_posts,id'],
            'about_page' => ['nullable', 'integer', 'exists:cms_posts,id'],
            'privacy_policy_page' => ['nullable', 'integer', 'exists:cms_posts,id'],
            'terms_of_service_page' => ['nullable', 'integer', 'exists:cms_posts,id'],
            'blog_same_as_home' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'blog_base_url.regex' => 'The blog URL slug may only contain lowercase letters, numbers, and hyphens.',
            'blog_base_url.max' => 'The blog URL slug must not exceed 50 characters.',
            'home_page.exists' => 'The selected homepage is invalid.',
            'blogs_page.exists' => 'The selected blog page is invalid.',
            'contact_page.exists' => 'The selected contact page is invalid.',
            'about_page.exists' => 'The selected about page is invalid.',
            'privacy_policy_page.exists' => 'The selected privacy policy page is invalid.',
            'terms_of_service_page.exists' => 'The selected terms of service page is invalid.',
        ];
    }

    public function attributes(): array
    {
        return [
            'home_page' => 'homepage',
            'blogs_page' => 'blog page',
            'blog_base_url' => 'blog URL slug',
            'contact_page' => 'contact page',
            'about_page' => 'about page',
            'privacy_policy_page' => 'privacy policy page',
            'terms_of_service_page' => 'terms of service page',
            'blog_same_as_home' => 'blog on homepage',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'blog_same_as_home' => $this->boolean('blog_same_as_home'),
            'blog_base_url' => trim((string) $this->input('blog_base_url', '')),
        ]);
    }
}
