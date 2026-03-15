<?php

declare(strict_types=1);

namespace Modules\Todos\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Support\Facades\Date;
use Modules\Todos\Definitions\TodoDefinition;
use Modules\Todos\Models\Todo;

class TodoRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:pending,in_progress,completed,on_hold,cancelled'],
            'priority' => ['required', 'string', 'in:low,medium,high,critical'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'visibility' => ['required', 'string', 'in:private,public'],
            'is_starred' => ['nullable', 'boolean'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'labels' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for the todo.',
            'status.required' => 'Select a status for the todo.',
            'status.in' => 'Select a valid status option.',
            'priority.required' => 'Select a priority for the todo.',
            'priority.in' => 'Select a valid priority option.',
            'due_date.after_or_equal' => 'The due date must be on or after the start date.',
            'assigned_to.exists' => 'The selected assignee is invalid.',
            'user_id.exists' => 'The selected owner is invalid.',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new TodoDefinition;
    }

    protected function getModelClass(): string
    {
        return Todo::class;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareBooleanField('is_starred');
        $this->trimField('title');
        $this->trimField('description');
        $this->trimField('labels');

        // Convert datetime fields from user's timezone to UTC for storage
        $userTimezone = app_localization_timezone();

        if ($this->filled('start_date')) {
            $startDate = Date::parse($this->input('start_date'), $userTimezone);
            $this->merge(['start_date' => $startDate->utc()->toDateTimeString()]);
        }

        if ($this->filled('due_date')) {
            $dueDate = Date::parse($this->input('due_date'), $userTimezone);
            $this->merge(['due_date' => $dueDate->utc()->toDateTimeString()]);
        }
    }
}
