<?php

namespace Modules\Platform\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Platform\Enums\WebsiteStatus;

class ChangeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validStatuses = array_column(WebsiteStatus::cases(), 'value');

        return [
            'status' => ['required', 'string', 'in:'.implode(',', $validStatuses)],
        ];
    }
}
