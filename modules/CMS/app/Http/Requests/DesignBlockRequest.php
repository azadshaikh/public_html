<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\CMS\Definitions\DesignBlockDefinition;
use Modules\CMS\Models\CmsPost;

class DesignBlockRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $designTypes = array_keys(config('cms.design_types', []));

        $id = $this->getRouteParameter();

        return [
            'title' => ['required', 'string', 'max:125'],
            'slug' => [
                'nullable',
                'string',
                'max:150',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
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
            ],
            'description' => ['nullable', 'string', 'max:1000'],

            'design_type' => ['required', 'string', Rule::in($designTypes)],
            'category_id' => ['required', 'string', 'max:255'],
            'design_system' => ['required', 'string', 'max:50'],

            'html' => ['nullable', 'string'],
            'preview_image_url' => ['nullable', 'string', 'max:500'],

            'status' => ['required', 'string', Rule::in(['draft', 'published'])],
            'attributes' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'The title field is required.',
            'title.max' => 'The title must not exceed 125 characters.',

            'slug.unique' => 'This slug is already taken. Please choose a different one.',
            'slug.regex' => 'The slug must only contain lowercase letters, numbers, and hyphens.',

            'design_type.required' => 'Please select a design type.',
            'design_type.in' => 'Invalid design type selected.',

            'category_id.required' => 'Please select a category.',

            'design_system.required' => 'Please select a design system.',

            'status.required' => 'Please select a status.',
            'status.in' => 'Invalid status selected.',
        ];
    }

    public function attributes(): array
    {
        return [
            'design_type' => 'design type',
            'category_id' => 'category',
            'design_system' => 'design system',
            'preview_image_url' => 'preview image URL',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new DesignBlockDefinition;
    }

    protected function prepareForValidation(): void
    {
        // Intentionally do not auto-generate a slug for design blocks.
        // Blocks are reusable fragments and should not accidentally become routable content.

        if (! $this->filled('block_type')) {
            $this->merge(['block_type' => 'static']);
        }
    }
}
