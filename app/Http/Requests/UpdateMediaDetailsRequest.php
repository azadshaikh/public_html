<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('edit_media');
    }

    public function rules(): array
    {
        return [
            'media_name' => ['required', 'string', 'max:255'],
            'media_id' => ['required', 'exists:media,id'],
            'media_alt' => ['nullable', 'string', 'max:255'],
            'media_caption' => ['nullable', 'string', 'max:1000'],
            'media_description' => ['nullable', 'string', 'max:5000'],
            'media_tags' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages()
    {
        return [
            'media_name.required' => 'The media name is required.',
            'media_id.required' => 'Media ID is required.',
            'media_id.exists' => 'Media not found.',
            'media_alt.max' => 'Alt text may not be greater than :max characters.',
        ];
    }
}
