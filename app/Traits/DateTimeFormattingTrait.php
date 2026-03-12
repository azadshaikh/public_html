<?php

namespace App\Traits;

/**
 * DateTimeFormattingTrait
 *
 * Helper trait for formatting datetime fields in API Resources.
 * Uses app localization settings for timezone and format.
 *
 * @example
 * class UserResource extends JsonResource
 * {
 *     use DateTimeFormattingTrait;
 *
 *     public function toArray(Request $request): array
 *     {
 *         $data = [
 *             'created_at' => $this->created_at,
 *             'published_date' => $this->published_date,
 *         ];
 *
 *         return $this->formatDateTimeFields($data,
 *             dateFields: ['published_date'],
 *             datetimeFields: ['created_at']
 *         );
 *     }
 * }
 */
trait DateTimeFormattingTrait
{
    /**
     * Format datetime fields based on app settings
     *
     * Converts UTC datetime values to app timezone with configured formats.
     * Uses app_date_time_format() helper which respects localization_timezone,
     * localization_date_format, and localization_time_format settings.
     *
     * @param  array  $data  Data array containing datetime values
     * @param  array  $dateFields  Fields to format as date only (e.g., 'published_date')
     * @param  array  $timeFields  Fields to format as time only (e.g., 'reminder_time')
     * @param  array  $datetimeFields  Fields to format as date + time (e.g., 'created_at')
     * @return array Data with formatted datetime fields
     */
    protected function formatDateTimeFields(
        array $data,
        array $dateFields = [],
        array $timeFields = [],
        array $datetimeFields = []
    ): array {
        // Format date-only fields
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && $data[$field]) {
                $data[$field] = app_date_time_format($data[$field], 'date');
            }
        }

        // Format time-only fields
        foreach ($timeFields as $field) {
            if (isset($data[$field]) && $data[$field]) {
                $data[$field] = app_date_time_format($data[$field], 'time');
            }
        }

        // Format datetime fields
        foreach ($datetimeFields as $field) {
            if (isset($data[$field]) && $data[$field]) {
                $data[$field] = app_date_time_format($data[$field], 'datetime');
            }
        }

        return $data;
    }

    /**
     * Format a single datetime value
     *
     * @param  mixed  $value  DateTime value (Carbon, DateTime, or string)
     * @param  string  $format  Format type: 'date', 'time', 'datetime', 'time_with_seconds'
     * @return string Formatted datetime string
     */
    protected function formatDateTime($value, string $format = 'datetime'): string
    {
        if (empty($value)) {
            return '';
        }

        return app_date_time_format($value, $format);
    }
}
