<?php

use Illuminate\Support\Facades\Date;

if (! function_exists('app_localization_timezone')) {
    /**
     * Resolve the currently configured localization timezone.
     */
    function app_localization_timezone(): string
    {
        $defaults = config('appsettings', []);

        return setting('localization_timezone', $defaults['time_zone'] ?? config('app.timezone', 'UTC'));
    }
}

if (! function_exists('app_date_time_format')) {
    /**
     * Format date and time based on admin settings
     *
     * @param  DateTimeInterface|string  $value
     * @param  string  $format
     * @return string
     */
    function app_date_time_format($value, $format = 'date')
    {
        if (empty($value)) {
            return '';
        }

        static $settingsCache = null;

        // Allow cache reset for testing (pass '__CLEAR_CACHE__' as value)
        if ($value === '__CLEAR_CACHE__') {
            $settingsCache = null;

            return '';
        }

        if ($settingsCache === null) {
            $defaults = config('appsettings', []);

            $settingsCache = [
                'date_format' => setting('localization_date_format', $defaults['date_format'] ?? 'Y-m-d'),
                'time_format' => setting('localization_time_format', $defaults['time_format'] ?? 'H:i'),
                'timezone' => app_localization_timezone(),
            ];
        }

        $dateFormat = $settingsCache['date_format'];
        $timeFormat = $settingsCache['time_format'];
        $timezone = $settingsCache['timezone'];

        $dateFormats = config('constants.date_formats', []);
        $timeFormats = config('constants.time_formats', []);

        $date = $value instanceof DateTimeInterface ? Date::instance($value) : Date::parse($value, 'UTC');

        if ($format === 'js_date') {
            return $dateFormats[$dateFormat]['jsformat'] ?? 'YYYY-MM-DD';
        }

        if ($format === 'js_time') {
            return $timeFormats[$timeFormat]['jsformat'] ?? 'HH:mm';
        }

        if ($format === 'js_datetime') {
            $dateJs = $dateFormats[$dateFormat]['jsformat'] ?? 'YYYY-MM-DD';
            $timeJs = $timeFormats[$timeFormat]['jsformat'] ?? 'HH:mm';

            return trim($dateJs.' '.$timeJs);
        }

        if ($format === 'time') {
            return $date->timezone($timezone)->format($timeFormat);
        }

        if ($format === 'time_with_seconds') {
            // Insert seconds before am/pm marker (a or A) if present, otherwise append
            $timeFormatWithSeconds = preg_replace('/(\s*[aA])$/', ':s$1', (string) $timeFormat);
            if ($timeFormatWithSeconds === $timeFormat) {
                // No am/pm marker found, just append seconds
                $timeFormatWithSeconds = $timeFormat.':s';
            }

            return $date->timezone($timezone)->format($timeFormatWithSeconds);
        }

        if ($format === 'datetime') {
            return $date->timezone($timezone)->format($dateFormat.' '.$timeFormat);
        }

        return $date->timezone($timezone)->format($dateFormat);
    }
}

if (! function_exists('get_date_time_format')) {
    /**
     * Backwards compatible helper to return JS-friendly format strings.
     */
    function get_date_time_format($type = 'date')
    {
        $dateFormat = app_date_time_format(now(), 'js_date');
        $timeFormat = app_date_time_format(now(), 'js_time');

        return match ($type) {
            'time' => $timeFormat,
            'datetime' => $dateFormat.' '.$timeFormat,
            default => $dateFormat,
        };
    }
}

if (! function_exists('format_date')) {
    /**
     * Format a date string to a specific format
     *
     * @param  string  $date
     * @param  string  $format
     */
    function format_date($date, $format = 'Y-m-d'): string
    {
        return date($format, strtotime($date));
    }
}

if (! function_exists('get_timeago_from_date')) {
    /**
     * Get time ago from past date to current time
     *
     * @param  string  $date
     */
    function get_timeago_from_date($date): string
    {
        $date = strtotime($date);
        $now = time();
        $ago = $now - $date;
        if ($ago < 60) {
            return $ago.' seconds ago';
        }

        if ($ago < 3600) {
            return floor($ago / 60).' minutes ago';
        }

        if ($ago < 86400) {
            return floor($ago / 3600).' hours ago';
        }

        if ($ago < 2592000) {
            return floor($ago / 86400).' days ago';
        }

        if ($ago < 31104000) {
            return floor($ago / 2592000).' months ago';
        }

        return floor($ago / 31104000).' years ago';
    }
}

if (! function_exists('schema_date_time_format')) {
    function schema_date_time_format($value): string
    {
        if (empty($value)) {
            return '';
        }

        static $settingsCache = null;

        if ($settingsCache === null) {
            $defaults = config('appsettings', []);

            $settingsCache = [
                'date_format' => setting('localization_date_format', $defaults['date_format'] ?? 'Y-m-d'),
                'timezone' => app_localization_timezone(),
            ];
        }

        $date = $value instanceof DateTimeInterface ? Date::instance($value) : Date::parse($value, 'UTC');

        return $date
            ->timezone($settingsCache['timezone'])
            ->format(($settingsCache['date_format'] ?? 'Y-m-d').'\TH:i:s.v\Z');
    }
}

if (! function_exists('sitemap_date_time_format')) {
    function sitemap_date_time_format($value): string
    {
        if (empty($value)) {
            return '';
        }

        static $timezone = null;

        if ($timezone === null) {
            $timezone = app_localization_timezone();
        }

        $date = $value instanceof DateTimeInterface ? Date::instance($value) : Date::parse($value, 'UTC');

        return $date->timezone($timezone)->format('Y-m-d\TH:i:s.v\Z');
    }
}
