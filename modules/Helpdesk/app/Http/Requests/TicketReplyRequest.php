<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $statusValues = collect(config('helpdesk.ticket_status_options', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        $priorityValues = collect(config('helpdesk.priority_options', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        $statusValues = $statusValues ?: ['open', 'pending', 'resolved', 'on_hold', 'closed', 'cancelled'];
        $priorityValues = $priorityValues ?: ['low', 'medium', 'high', 'critical'];

        return [
            'department_id' => ['required', 'exists:helpdesk_departments,id'],
            'assigned_to' => ['required', 'exists:users,id'],
            'priority' => ['required', Rule::in($priorityValues)],
            'status' => ['required', Rule::in($statusValues)],
            'content' => ['required', 'string'],
            'is_internal' => ['required', 'in:0,1'],

            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'department_id.required' => 'Please select a department.',
            'department_id.exists' => 'The selected department is invalid.',
            'assigned_to.required' => 'Please select an assignee.',
            'assigned_to.exists' => 'The selected assignee is invalid.',
            'priority.required' => 'Please select a priority.',
            'priority.in' => 'The selected priority is invalid.',
            'status.required' => 'Please select a status.',
            'status.in' => 'The selected status is invalid.',
            'content.required' => 'Please enter a reply.',
        ];
    }
}
