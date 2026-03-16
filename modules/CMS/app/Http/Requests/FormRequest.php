<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\CMS\Definitions\FormDefinition;

class FormRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $templateKeys = array_keys(config('cms.forms.templates', []));
        $formTypeKeys = array_keys(config('cms.forms.form_types', []));

        $slugRule = $this->uniqueRule('slug')->whereNull('deleted_at');
        $shortcodeRule = $this->uniqueRule('shortcode')->whereNull('deleted_at');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slugRule,
            ],
            'shortcode' => [
                'nullable',
                'string',
                'max:255',
                $shortcodeRule,
            ],
            'template' => ['nullable', Rule::in($templateKeys)],
            'form_type' => ['nullable', Rule::in($formTypeKeys)],
            'html' => ['required', 'string'],
            'css' => ['nullable', 'string'],
            'store_in_database' => ['nullable', 'boolean'],
            'confirmation_type' => ['nullable', 'string', Rule::in(['message', 'redirect'])],
            'confirmation_message' => ['nullable', 'required_if:confirmation_type,message', 'string', 'max:500'],
            'redirect_url' => ['nullable', 'required_if:confirmation_type,redirect', 'string', 'url', 'max:500'],
            'feature_image_id' => ['nullable', 'integer', 'exists:media,id'],
            'status' => ['required', 'string', Rule::in(['draft', 'published'])],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'title.max' => 'The title must not exceed 255 characters.',
            'slug.unique' => 'This slug is already taken. Please choose a different one.',
            'slug.regex' => 'The slug must only contain lowercase letters, numbers, and hyphens.',
            'shortcode.unique' => 'This shortcode is already taken. Please choose a different one.',
            'html.required' => 'The form HTML is required.',
            'html.string' => 'The form HTML must be valid text.',
            'css.string' => 'The CSS must be valid text.',
            'confirmation_type.in' => 'The confirmation type must be either message or redirect.',
            'confirmation_message.required_if' => 'The confirmation message is required when type is message.',
            'confirmation_message.max' => 'The confirmation message must not exceed 500 characters.',
            'redirect_url.required_if' => 'The redirect URL is required when type is redirect.',
            'redirect_url.url' => 'The redirect URL must be a valid URL.',
            'redirect_url.max' => 'The redirect URL must not exceed 500 characters.',
            'store_in_database.boolean' => 'The store in database field must be true or false.',
            'feature_image_id.integer' => 'The featured image must be a valid selection.',
            'feature_image_id.exists' => 'The selected featured image does not exist.',
            'status.required' => 'The status is required.',
            'status.in' => 'The status must be either draft or published.',
            'published_at.date' => 'The published date must be a valid date.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'title',
            'slug' => 'slug',
            'shortcode' => 'shortcode',
            'html' => 'form HTML',
            'css' => 'CSS styles',
            'confirmation_type' => 'confirmation type',
            'confirmation_message' => 'confirmation message',
            'redirect_url' => 'redirect URL',
            'store_in_database' => 'store in database',
            'feature_image_id' => 'featured image',
            'status' => 'status',
            'published_at' => 'published date',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new FormDefinition;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('title') && ! $this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug((string) $this->title),
            ]);
        }

        if ($this->filled('title') && ! $this->filled('shortcode')) {
            $this->merge([
                'shortcode' => 'form_'.Str::slug((string) $this->title, '_'),
            ]);
        }

        if ($this->has('store_in_database')) {
            $this->merge([
                'store_in_database' => filter_var($this->store_in_database, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Convert published_at from user's timezone to UTC for storage
        if ($this->filled('published_at')) {
            $userTimezone = app_localization_timezone();
            $publishedAt = Date::parse($this->input('published_at'), $userTimezone);
            $this->merge(['published_at' => $publishedAt->utc()->toDateTimeString()]);
        }
    }
}
