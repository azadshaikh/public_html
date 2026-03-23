<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Helpdesk\Definitions\TicketDefinition;

class TicketRequest extends ScaffoldRequest
{
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

        $statusRule = ['nullable', Rule::in($statusValues)];
        if ($this->isUpdate()) {
            $statusRule = ['required', Rule::in($statusValues)];
        }

        return [
            'ticket_number' => ['required', 'string', 'max:150', $this->uniqueRule('ticket_number')],
            'user_id' => ['required', $this->existsRule('users', 'id')],
            'department_id' => ['required', $this->existsRule('helpdesk_departments', 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in($priorityValues)],
            'assigned_to' => ['required', $this->existsRule('users', 'id')],
            'status' => $statusRule,

            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'attachments_urls' => ['nullable', 'array'],
            'attachments_urls.*' => ['string'],
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new TicketDefinition;
    }
}
