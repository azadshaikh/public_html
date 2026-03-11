<?php

namespace Modules\ChatBot\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\ChatBot\Models\PromptTemplate;

class PromptTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name', ''));
        $slug = trim((string) $this->input('slug', ''));

        $this->merge([
            'name' => $name,
            'slug' => $slug !== '' ? Str::slug($slug) : Str::slug($name),
            'purpose' => trim((string) $this->input('purpose', '')),
            'model' => $this->nullableString('model') ?? 'gpt-4.1-mini',
            'tone' => $this->nullableString('tone') ?? 'supportive',
            'system_prompt' => trim((string) $this->input('system_prompt', '')),
            'notes' => $this->nullableString('notes'),
            'status' => $this->nullableString('status') ?? 'draft',
            'is_default' => $this->boolean('is_default'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique(PromptTemplate::class, 'slug')->ignore($this->promptTemplate()?->getKey()),
            ],
            'purpose' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:100'],
            'tone' => ['required', Rule::in(array_keys(PromptTemplate::TONES))],
            'system_prompt' => ['required', 'string', 'min:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(array_keys(PromptTemplate::STATUSES))],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function promptAttributes(): array
    {
        return Arr::only($this->validated(), [
            'name',
            'slug',
            'purpose',
            'model',
            'tone',
            'system_prompt',
            'notes',
            'status',
            'is_default',
        ]);
    }

    protected function promptTemplate(): ?PromptTemplate
    {
        $promptTemplate = $this->route('promptTemplate');

        return $promptTemplate instanceof PromptTemplate ? $promptTemplate : null;
    }

    protected function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
