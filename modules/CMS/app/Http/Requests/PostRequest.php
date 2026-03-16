<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Closure;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\CMS\Definitions\PostDefinition;
use Modules\CMS\Models\CmsPost;

class PostRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $id = $this->getRouteParameter();

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => $this->getSlugRules($id),

            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'feature_image' => ['nullable', 'integer'],
            'is_featured' => ['nullable', 'boolean'],

            'status' => ['required', 'string', 'in:'.implode(',', array_keys(config('cms.post_status', [])))],
            'visibility' => ['nullable', 'string', 'in:public,private,password'],
            'post_password' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($id): void {
                    $visibility = $this->input('visibility');
                    if ($visibility !== 'password') {
                        return;
                    }

                    $hasNewPassword = ! in_array(trim((string) $value), ['', '0'], true);
                    if (! $this->isUpdate()) {
                        if (! $hasNewPassword) {
                            $fail('A password is required for password-protected content.');
                        }

                        return;
                    }

                    if ($hasNewPassword) {
                        return;
                    }

                    if (! $id) {
                        $fail('A password is required for password-protected content.');

                        return;
                    }

                    $existingPassword = CmsPost::query()->whereKey($id)->value('post_password');
                    if (empty($existingPassword)) {
                        $fail('A password is required for password-protected content.');
                    }
                },
            ],
            'password_hint' => ['nullable', 'string', 'max:255'],
            'author_id' => ['required', 'integer', 'exists:users,id'],
            'published_at' => [
                'nullable',
                'date',
                'required_if:status,scheduled',
                function ($attribute, $value, $fail): void {
                    if (empty($value)) {
                        return;
                    }

                    $status = $this->input('status');
                    $publishDate = Date::parse($value); // Already UTC from prepareForValidation
                    $now = now(); // UTC

                    if ($status === 'scheduled' && $publishDate->lessThanOrEqualTo($now)) {
                        $fail('Scheduled posts must be set to a future date and time.');
                    }

                    if ($status === 'published' && $publishDate->greaterThan($now)) {
                        $fail('Published posts cannot be set to a future date. Use "Scheduled" status instead.');
                    }
                },
            ],

            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_robots' => ['nullable', 'string'],

            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'url', 'max:500'],
            'og_url' => ['nullable', 'url', 'max:500'],
            'schema' => ['nullable', 'string'],
            'template' => ['nullable', 'string', 'max:100'],

            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => [
                'required',
                'integer',
                Rule::exists('cms_posts', 'id')->where('type', 'category'),
            ],
            'tags' => ['nullable', 'array'],
            'tags.*' => [
                'nullable',
                'integer',
                Rule::exists('cms_posts', 'id')->where('type', 'tag'),
            ],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new PostDefinition;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge(['status' => 'draft']);
        }

        // Sanitize title only (content/excerpt are not sanitized to allow custom HTML/CSS/JS)
        if ($this->filled('title')) {
            $this->merge(['title' => trim(strip_tags((string) $this->input('title')))]);
        }

        if ($this->has('title') && ! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug(strip_tags((string) $this->input('title')))]);
        }

        // The datepicker sends dates in ISO format (YYYY-MM-DD HH:mm:ss) in the user's timezone
        // We need to convert it to UTC for storage
        if ($this->filled('published_at')) {
            $userTimezone = app_localization_timezone();
            $publishedAt = Date::parse($this->input('published_at'), $userTimezone);
            $this->merge(['published_at' => $publishedAt->utc()->toDateTimeString()]);
        }
    }

    private function getSlugRules(int|string|null $id): array
    {
        return [
            'nullable',
            'string',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($id): void {
                if (empty($value)) {
                    return;
                }

                $existingPost = CmsPost::query()
                    ->where('slug', $value)
                    ->when($id, fn ($q) => $q->where('id', '!=', $id))
                    ->first();

                if ($existingPost) {
                    $typeLabel = ucfirst(Str::plural((string) $existingPost->getAttribute('type')));
                    $fail(sprintf('This slug is already used by a content item in %s.', $typeLabel));
                }
            },
        ];
    }
}
