<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HelpdeskSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_helpdesk_settings') ?? false;
    }

    public function rules(): array
    {
        return [
            'ticket_prefix' => ['required', 'string', 'max:20'],
            'ticket_serial_number' => ['required', 'integer', 'min:1'],
            'ticket_digit_length' => ['required', 'integer', 'min:1', 'max:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_prefix.required' => 'Ticket prefix is required.',
            'ticket_serial_number.required' => 'Next ticket number is required.',
            'ticket_serial_number.integer' => 'Next ticket number must be a whole number.',
            'ticket_serial_number.min' => 'Next ticket number must be at least 1.',
            'ticket_digit_length.required' => 'Ticket number length is required.',
            'ticket_digit_length.integer' => 'Ticket number length must be a whole number.',
            'ticket_digit_length.min' => 'Ticket number length must be at least 1.',
            'ticket_digit_length.max' => 'Ticket number length may not exceed 6 digits.',
        ];
    }
}
