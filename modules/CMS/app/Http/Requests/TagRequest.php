<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Closure;
use Illuminate\Support\Str;
use Modules\CMS\Definitions\TagDefinition;
use Modules\CMS\Models\CmsPost;

class TagRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $id = $this->getRouteParameter();

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => $this->getSlugRules($id),
            'content' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'feature_image' => ['nullable', 'integer'],
            'status' => ['required', 'string', 'in:published,draft'],
            'template' => ['nullable', 'string', 'max:100'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_robots' => ['nullable', 'string'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:500'],
            'og_url' => ['nullable', 'string', 'max:500'],
            'schema' => ['nullable', 'string'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new TagDefinition;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge(['status' => 'draft']);
        }

        // Sanitize title only (content is not sanitized to allow custom HTML/CSS/JS)
        if ($this->filled('title')) {
            $this->merge(['title' => trim(strip_tags((string) $this->input('title')))]);
        }

        if ($this->has('title') && ! $this->filled('slug')) {
            $this->merge(['slug' => Str::slug(strip_tags((string) $this->input('title')))]);
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
