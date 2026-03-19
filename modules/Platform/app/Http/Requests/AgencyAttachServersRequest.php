<?php

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgencyAttachServersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server_ids' => ['present', 'array'],
            'server_ids.*' => ['integer', 'exists:platform_servers,id'],
            'primary_server_id' => ['nullable', 'integer', 'exists:platform_servers,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $primaryServerId = $this->input('primary_server_id');
            $serverIds = collect($this->input('server_ids', []))
                ->map(fn ($id): int => (int) $id)
                ->all();

            if ($primaryServerId !== null && ! in_array((int) $primaryServerId, $serverIds, true)) {
                $validator->errors()->add('primary_server_id', 'The selected primary server must be included in server_ids.');
            }
        });
    }
}
