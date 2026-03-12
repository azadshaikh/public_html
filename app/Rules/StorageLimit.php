<?php

namespace App\Rules;

use App\Enums\MediaUploadErrorType;
use App\Models\CustomMedia;
use Exception;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Log;

class StorageLimit implements Rule
{
    private MediaUploadErrorType $errorType = MediaUploadErrorType::STORAGE_LIMIT;

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        try {
            Log::debug('StorageLimit validation started', [
                'request_headers' => request()->headers->all(),
                'is_ajax' => request()->ajax(),
                'expects_json' => request()->expectsJson(),
            ]);

            $storage_details = CustomMedia::getUsedStorageSize();

            // Check if unlimited storage is configured
            if ($storage_details['max_size_bytes'] <= 0) {
                Log::info('Storage limit check: Unlimited storage configured');

                return true;
            }

            // Get current file size
            $file_size = $value->getSize();

            // Check if adding this file would exceed storage limit
            $remaining_bytes = $storage_details['remaining_bytes'];

            $passes = $remaining_bytes >= $file_size;

            Log::info('Storage limit check', [
                'file_size' => $file_size,
                'remaining_bytes' => $remaining_bytes,
                'used_bytes' => $storage_details['used_size_bytes'],
                'max_bytes' => $storage_details['max_size_bytes'],
                'passes' => $passes,
            ]);

            if (! $passes) {
                Log::warning('Storage limit exceeded', [
                    'file_size' => $file_size,
                    'remaining_bytes' => $remaining_bytes,
                    'max_bytes' => $storage_details['max_size_bytes'],
                ]);
            }

            return $passes;
        } catch (Exception $exception) {
            Log::error('StorageLimit rule error', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'request_data' => request()->all(),
            ]);

            // On error, allow upload (fail open for better UX)
            return true;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        try {
            $storage_details = CustomMedia::getUsedStorageSize();
            $used_readable = $storage_details['used_size_readable'] ?? '0 B';
            $max_readable = $storage_details['max_size_readable'] ?? 'Unknown';
            $remaining_readable = $storage_details['remaining_readable'] ?? '0 B';

            return sprintf('Storage limit exceeded. Used: %s of %s (Remaining: %s). Please contact your administrator to extend storage limit.', $used_readable, $max_readable, $remaining_readable);
        } catch (Exception $exception) {
            Log::error('StorageLimit message error', ['error' => $exception->getMessage()]);

            return 'Storage limit exceeded. Please contact your administrator to extend storage limit.';
        }
    }

    /**
     * Get the error type for this validation rule
     */
    public function getErrorType(): MediaUploadErrorType
    {
        return $this->errorType;
    }
}
