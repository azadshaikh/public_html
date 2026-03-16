<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\CMS\Definitions\CategoryDefinition;
use Modules\CMS\Models\CmsPost;

class CategoryRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $id = $this->getRouteParameter();

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => $this->getSlugRules($id),
            'content' => ['nullable', 'string'],
            'feature_image' => ['nullable', 'integer'],
            'status' => ['required', 'string', 'in:published,draft'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('cms_posts', 'id')->where('type', 'category'),
            ],
            'template' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new CategoryDefinition;
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
