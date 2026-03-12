<?php

namespace App\Http\Requests;

use App\Enums\MediaUploadErrorType;
use App\Rules\StorageLimit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('add_media');
    }

    public function rules(): array
    {
        $accepted_file_types = config('media.media_allowed_file_types', 'image/png,image/jpg,image/jpeg,image/gif,image/webp,image/svg+xml,image/x-icon,image/bmp,video/mp4,video/webm,video/x-webm,video/avi,video/mov,video/wmv,video/x-matroska,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/plain,text/csv');
        $max_upload_size = config('media-library.max_file_size') / 1024;

        $rules = [
            'file' => [
                'required',
                'file',
                'max:'.$max_upload_size,
                'mimetypes:'.$accepted_file_types,
            ],
        ];

        // Add storage limit validation if file is present
        if ($this->hasFile('file')) {
            $rules['file'][] = new StorageLimit;
        }

        return $rules;
    }

    public function messages()
    {
        $max_upload_size = config('media-library.max_file_size') / (1024 * 1024);

        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The selected file is not valid.',
            'file.max' => 'File size cannot exceed '.$max_upload_size.'MB.',
            'file.mimetypes' => 'File type not allowed. Please upload images, videos (MP4, WebM, MKV, AVI, MOV, WMV), or documents (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX).',
        ];
    }

    /**
     * Get the error type for a specific validation failure
     * This can be used by controllers for additional error handling
     */
    public function getErrorTypeForField(string $error): MediaUploadErrorType
    {
        validator($this->all(), $this->rules(), $this->messages());

        return $this->determineErrorType($error);
    }

    /**
     * Handle a failed validation attempt.
     *
     * @return void
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        if ($this->expectsJson()) {
            $error = $validator->errors()->first();
            $errorType = $this->determineErrorType($error);

            Log::warning('Media upload validation failed', [
                'error' => $error,
                'error_type' => $errorType->value,
                'file_name' => $this->file('file')?->getClientOriginalName(),
                'file_size' => $this->file('file')?->getSize(),
                'user_id' => $this->user()?->id,
            ]);

            throw new HttpResponseException(
                response()->json([
                    'status' => 0,
                    'error' => $error,
                    'error_type' => $errorType->value,
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }

    /**
     * Determine the error type based on validation rules and error message
     */
    private function determineErrorType(string $error): MediaUploadErrorType
    {
        // StorageLimit rule always returns messages containing this marker.
        if (str_contains($error, 'Storage limit')) {
            return MediaUploadErrorType::STORAGE_LIMIT;
        }

        // Fallback to pattern matching for built-in Laravel validation rules
        return match (true) {
            str_contains($error, 'exceed') || str_contains($error, 'too large') => MediaUploadErrorType::FILE_SIZE,
            str_contains($error, 'not allowed') || str_contains($error, 'mimetypes') => MediaUploadErrorType::FILE_TYPE,
            default => MediaUploadErrorType::VALIDATION,
        };
    }
}
