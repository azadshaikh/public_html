<?php

namespace Modules\Todos\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Todos\Models\TodoTask;

class TodoTaskRequest extends FormRequest
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
            'details' => $this->nullableString('details'),
            'status' => $this->nullableString('status') ?? 'backlog',
            'priority' => $this->nullableString('priority') ?? 'medium',
            'owner' => $this->nullableString('owner'),
            'due_date' => $this->nullableString('due_date'),
            'is_blocked' => $this->boolean('is_blocked'),
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
                Rule::unique(TodoTask::class, 'slug')->ignore($this->todoTask()?->getKey()),
            ],
            'details' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(array_keys(TodoTask::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(TodoTask::PRIORITIES))],
            'owner' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'is_blocked' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        return Arr::only($this->validated(), [
            'title',
            'slug',
            'details',
            'status',
            'priority',
            'owner',
            'due_date',
            'is_blocked',
        ]);
    }

    protected function todoTask(): ?TodoTask
    {
        $todoTask = $this->route('todoTask');

        return $todoTask instanceof TodoTask ? $todoTask : null;
    }

    protected function nullableString(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }
}
