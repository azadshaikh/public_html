<?php

namespace App\Enums;

enum MediaUploadErrorType: string
{
    case STORAGE_LIMIT = 'storage_limit';
    case FILE_SIZE = 'file_size';
    case FILE_TYPE = 'file_type';
    case VALIDATION = 'validation';
    case NETWORK = 'network';
    case UNKNOWN = 'unknown';

    /**
     * Get human-readable label for the error type
     */
    public function label(): string
    {
        return match ($this) {
            self::STORAGE_LIMIT => 'Storage Limit Exceeded',
            self::FILE_SIZE => 'File Too Large',
            self::FILE_TYPE => 'Invalid File Type',
            self::VALIDATION => 'Validation Error',
            self::NETWORK => 'Network Error',
            self::UNKNOWN => 'Unknown Error',
        };
    }

    /**
     * Get icon class for the error type
     */
    public function icon(): string
    {
        return match ($this) {
            // migrated from Remix Icon -> Bootstrap Icons (bi ...)
            self::STORAGE_LIMIT => 'ri-error-warning-fill',
            self::FILE_SIZE => 'ri-file-damage-line',
            self::FILE_TYPE => 'ri-file-forbid-line',
            self::VALIDATION => 'ri-alert-line',
            self::NETWORK => 'ri-wifi-off-line',
            self::UNKNOWN => 'ri-question-mark-circle',
        };
    }

    /**
     * Check if this error type should show persistent notifications
     */
    public function isPersistent(): bool
    {
        return match ($this) {
            self::STORAGE_LIMIT => true,
            default => false,
        };
    }

    /**
     * Get all error types as array for frontend
     */
    public static function toArray(): array
    {
        return array_map(fn (MediaUploadErrorType $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'icon' => $case->icon(),
            'persistent' => $case->isPersistent(),
        ], self::cases());
    }
}
