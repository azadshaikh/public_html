<?php

namespace Modules\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Cms\Models\CmsPage;

class CmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title', ''));
        $slug = trim((string) $this->input('slug', ''));

        $this->merge([
            'title' => $title,
            'slug' => $slug !== '' ? Str::slug($slug) : Str::slug($title),
            'summary' => $this->nullableString('summary'),
            'body' => trim((string) $this->input('body', '')),
            'status' => $this->nullableString('status') ?? 'draft',
            'published_at' => $this->nullableString('published_at'),
            'is_featured' => $this->boolean('is_featured'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique(CmsPage::class, 'slug')->ignore($this->page()?->getKey()),
            ],
            'summary' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string', 'min:30'],
            'status' => ['required', Rule::in(array_keys(CmsPage::STATUSES))],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pageAttributes(): array
    {
        return Arr::only($this->validated(), [
            'title',
            'slug',
            'summary',
            'body',
            'status',
            'published_at',
            'is_featured',
        ]);
    }

    protected function page(): ?CmsPage
    {
        $page = $this->route('cmsPage');

        return $page instanceof CmsPage ? $page : null;
    }

    protected function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
