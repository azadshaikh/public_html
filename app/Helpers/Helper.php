<?php

use App\Modules\ModuleManager;
use App\Services\SettingsCacheService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

if (! function_exists('app_url')) {
    function app_url()
    {
        return config('app.url');
    }
}

if (! function_exists('setting')) {
    /**
     * Get a setting value by key.
     *
     * Uses SettingsCacheService for robust caching with automatic invalidation
     * via the SettingsObserver when settings are modified.
     *
     * @param  string|null  $key  The setting key (e.g., 'site_title', 'mail_host')
     * @param  mixed  $default  Default value if setting doesn't exist
     * @return mixed
     */
    function setting($key = null, $default = '')
    {
        try {
            /** @var SettingsCacheService $cacheService */
            $cacheService = resolve(SettingsCacheService::class);

            // If no key provided, return all settings
            if ($key === null) {
                return $cacheService->all();
            }

            return $cacheService->get($key, $default);
        } catch (Exception) {
            // If service is not available (e.g., during early bootstrap), return default
            return $default;
        }
    }
}

if (! function_exists('settings_cache')) {
    /**
     * Get the SettingsCacheService instance for advanced cache operations.
     */
    function settings_cache(): SettingsCacheService
    {
        return resolve(SettingsCacheService::class);
    }
}

if (! function_exists('generate_sortable_unique_id')) {
    /**
     * Generate a sortable, URL-safe unique identifier with timestamp-based ordering
     *
     * Features:
     * - Reverse timestamp encoding for natural sorting (latest items first with DESC order)
     * - Microsecond precision for ultra-fine sorting and collision prevention
     * - No confusing characters (excludes i, l, o to prevent misreading)
     * - Pure lowercase letters only (a-z, URL-safe)
     * - Optional pronounceable pattern for better readability
     * - Configurable length for different use cases
     *
     * @param  int  $length  Total desired length of the generated ID (minimum 8, maximum 20)
     * @param  bool  $pronounceable  Whether to use vowel-consonant pattern for readability
     * @return string Generated unique identifier
     *
     * Examples:
     * - generate_sortable_unique_id(12) → "zpwnmkdhabcx"
     * - generate_sortable_unique_id(10, true) → "dakemalexy"
     * - generate_sortable_unique_id(14) → "zpwnmkdhabcxyz"
     */
    function generate_sortable_unique_id(int $length = 12, bool $pronounceable = false): string
    {
        // Validate length bounds
        $length = max(8, min(20, $length));

        // Get current microsecond timestamp for ultra-fine precision
        $microtime = (int) (microtime(true) * 1000000);

        // Create reverse timestamp for latest-first sorting
        // Newer items will have larger reverse values, so they sort first with DESC
        $maxMicro = 2147483647000000; // Max 32-bit timestamp in microseconds (year 2038)
        $reverseTimestamp = $maxMicro - $microtime;

        if ($pronounceable) {
            return generatePronounceableId($reverseTimestamp, $length);
        }

        return generateStandardId($reverseTimestamp, $length);
    }
}

if (! function_exists('generateStandardId')) {
    /**
     * Generate standard sortable ID using safe character set
     *
     * @param  int  $timestamp  Reverse timestamp for encoding
     * @param  int  $length  Desired total length
     * @return string Generated ID
     */
    function generateStandardId(int $timestamp, int $length): string
    {
        // Safe character set: no confusing chars (i, l, o)
        $baseChars = 'abcdefghjkmnpqrstuvwxyz'; // 24 characters
        $charCount = strlen($baseChars);

        // Encode timestamp portion (use 60% of total length)
        $timestampLength = (int) ($length * 0.6);
        $encoded = '';
        $tempTimestamp = $timestamp;

        for ($i = 0; $i < $timestampLength && $tempTimestamp > 0; $i++) {
            $encoded = $baseChars[$tempTimestamp % $charCount].$encoded;
            $tempTimestamp = (int) ($tempTimestamp / $charCount);
        }

        // Pad timestamp portion if needed
        while (strlen($encoded) < $timestampLength) {
            $encoded = $baseChars[0].$encoded;
        }

        // Generate random suffix for remaining length
        $randomLength = $length - $timestampLength;
        $randomSuffix = '';
        for ($i = 0; $i < $randomLength; $i++) {
            $randomSuffix .= $baseChars[random_int(0, $charCount - 1)];
        }

        return $encoded.$randomSuffix;
    }
}

if (! function_exists('generatePronounceableId')) {
    /**
     * Generate pronounceable sortable ID using vowel-consonant pattern
     *
     * @param  int  $timestamp  Reverse timestamp for encoding
     * @param  int  $length  Desired total length
     * @return string Generated pronounceable ID
     */
    function generatePronounceableId(int $timestamp, int $length): string
    {
        // Vowels and consonants (no confusing characters)
        $vowels = 'aeu'; // 3 vowels (removed i, o)
        $consonants = 'bcdfghjkmnpqrstvwxyz'; // 20 consonants (removed l)

        $timestampLength = (int) ($length * 0.6);
        $result = '';
        $tempTimestamp = $timestamp;

        // Encode timestamp with alternating vowel-consonant pattern
        for ($i = 0; $i < $timestampLength; $i++) {
            if ($i % 2 === 0) {
                // Even positions: consonants
                $index = $tempTimestamp % strlen($consonants);
                $result .= $consonants[$index];
                $tempTimestamp = (int) ($tempTimestamp / strlen($consonants));
            } else {
                // Odd positions: vowels
                $index = $tempTimestamp % strlen($vowels);
                $result .= $vowels[$index];
                $tempTimestamp = (int) ($tempTimestamp / strlen($vowels));
            }
        }

        // Add random pronounceable suffix
        $remainingLength = $length - strlen($result);
        for ($i = 0; $i < $remainingLength; $i++) {
            if ($i % 2 === 0) {
                $result .= $consonants[random_int(0, strlen($consonants) - 1)];
            } else {
                $result .= $vowels[random_int(0, strlen($vowels) - 1)];
            }
        }

        return $result;
    }
}

if (! function_exists('generate_unique_id')) {
    /**
     * Generate a unique random ID (NanoID-style)
     *
     * Features:
     * - Always starts with a letter (safe for usernames, database identifiers, etc.)
     * - Uses URL-safe characters: a-z, 0-9 (excluding confusing chars like 0, o, l, 1)
     * - Configurable length (default 9 chars = 19.6 trillion combinations)
     * - Database uniqueness check with configurable table/column
     *
     * @param  string  $table  Database table to check for uniqueness
     * @param  string  $column  Column name to check for uniqueness
     * @param  int  $length  Desired length of the ID (default 9)
     * @param  int  $maxAttempts  Maximum attempts before throwing exception
     * @return string Generated unique identifier
     *
     * Examples:
     * - generate_unique_id('platform_websites', 'site_id') → "k7xm2npab"
     * - generate_unique_id('platform_orders', 'order_code', 12) → "t9bhqwc3efgh"
     */
    function generate_unique_id(string $table, string $column, int $length = 9, int $maxAttempts = 100): string
    {
        // Safe alphabet - excludes confusing characters (0, o, l, 1)
        $letters = 'abcdefghjkmnpqrstuvwxyz'; // 23 letters (no l, o)
        $alphanumeric = 'abcdefghjkmnpqrstuvwxyz23456789'; // 31 chars (no 0, 1, l, o)

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // First character must be a letter (safe for usernames)
            $id = $letters[random_int(0, strlen($letters) - 1)];

            // Remaining characters can be alphanumeric
            for ($i = 1; $i < $length; $i++) {
                $id .= $alphanumeric[random_int(0, strlen($alphanumeric) - 1)];
            }

            // Check if unique in database
            if (! DB::table($table)->where($column, $id)->exists()) {
                return $id;
            }

            // After many attempts, increase length for more entropy
            if ($attempt > 50) {
                $length++;
            }

            if ($attempt > 75) {
                $length++;
            }
        }

        throw new RuntimeException('Unable to generate unique ID after '.$maxAttempts.' attempts');
    }
}

if (! function_exists('active_modules')) {
    function active_modules($module_slug = '')
    {
        try {
            static $active_modules = null;

            if ($active_modules === null) {
                /** @var ModuleManager $moduleManager */
                $moduleManager = app(ModuleManager::class);

                $active_modules = $moduleManager
                    ->enabled()
                    ->map(fn ($module): array => [
                        'name' => $module->name,
                        'slug' => $module->slug,
                        'folder_name' => basename($module->basePath),
                        'icon' => null,
                        'prefix' => $module->url(),
                    ])
                    ->keyBy('slug')
                    ->all();
            }

            if (! empty($module_slug)) {
                /** @var ModuleManager $moduleManager */
                $moduleManager = app(ModuleManager::class);

                return $moduleManager->isEnabled((string) $module_slug);
            }

            return collect($active_modules);
        } catch (Exception) {
            // If there's any error, return default
            return empty($module_slug) ? [] : false;
        }
    }
}

if (! function_exists('admin_prefix')) {
    function admin_prefix()
    {
        return config('app.admin_slug');
    }
}

if (! function_exists('app_version')) {
    /**
     * Helper to grab the application version.
     *
     * @return mixed
     */
    function app_version()
    {
        $defaultVersion = '1.0.0';

        try {
            if (config('app.debug')) {
                $json_content = file_get_contents(base_path('composer.json'));
                $json_data = json_decode($json_content, true);

                return $json_data['version'] ?? $defaultVersion;
            }

            $cacheKey = 'app_version';
            $cacheDuration = 120; // Cache duration in minutes

            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            $json_content = file_get_contents(base_path('composer.json'));
            $json_data = json_decode($json_content, true);
            $version = $json_data['version'] ?? $defaultVersion;

            Cache::put($cacheKey, $version, $cacheDuration);

            return $version;
        } catch (Exception) {
            // If there's any error reading composer.json or cache, return default version
            return $defaultVersion;
        }
    }
}

/**
 * Generates an SEO-friendly slug and checks whether it already exists in the specified table and field.
 *
 * @param  string  $table  - the table name to check for slug uniqueness
 * @param  string  $field  - the column name to check for slug uniqueness
 * @param  string  $slug  - the string to convert to a unique SEO-friendly slug
 * @param  bool  $is_like_query  [optional] - whether the query should use "LIKE" instead of "=" for matching the slug prefix
 * @param  bool  $skip_unique  [optional] - whether to skip checking for slug uniqueness altogether
 * @return string - the unique SEO-friendly slug
 */
if (! function_exists('generate_slug')) {
    function generate_slug(string $table, string $field, string $slug, bool $is_like_query = false, bool $skip_unique = false, $skip_slug_logic = false): string
    {
        if ($skip_slug_logic === true) {
            // Replace whitespace with hyphens
            $slug = preg_replace('/[\s]+/i', '-', $slug);
        } else {
            // List of common stop words
            $stopWords = [
                'a', 'an', 'and', 'are', 'as', 'at',
                'be', 'but', 'by',
                'for', 'if',
                'in', 'into', 'is', 'it',
                'no', 'not',
                'of', 'on', 'or',
                'such',
                'that', 'the', 'their', 'then', 'there', 'these', 'they', 'this', 'to',
                'was', 'will', 'with', 'would',
                'you', 'your',
            ];

            // Remove unwanted characters including numbers.
            $slug = preg_replace('/[^a-zA-Z\-\s]/', '', $slug);
            $slug = preg_replace('~[^\pL\d]+~u', '-', (string) $slug);

            // Remove stop words
            $slug = implode(' ', array_diff(explode(' ', (string) $slug), $stopWords));
            $slug = implode(' ', array_diff(explode('-', $slug), $stopWords));

            // Remove duplicate words
            $slug = implode(' ', array_unique(explode(' ', $slug)));

            // Lowercase
            $slug = strtolower($slug);

            // Replace whitespace with hyphens
            $slug = preg_replace('/[\s]+/i', '-', $slug);

            // Remove duplicated hyphens
            $slug = preg_replace('~-+~', '-', (string) $slug);

            // Trim
            $slug = trim((string) $slug, '-');

            // Transliterate non-ASCII characters
            $slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
        }

        // check for duplicate_slug and return $slug if no duplicate found
        if ($skip_unique) {
            return $slug;
        }

        // check for unique_slug return $slug.
        return getUniqueSlug($table, $field, $slug, $is_like_query);
    }
}

/*
 * Show Human readable file size
 *
 * @var [type]
 */
if (! function_exists('getUniqueSlug')) {
    function getUniqueSlug(string $table, string $field, string $slug, bool $is_like_query = false, ?string $where_raw = null)
    {
        $queryobj = DB::table($table);

        if (! is_null($where_raw) && ($where_raw !== '' && $where_raw !== '0')) {
            $queryobj->whereRaw($where_raw);
        }

        if ($is_like_query) {
            $queryobj->where($field, 'Like', $slug.'%');
        } else {
            $queryobj->where($field, $slug);
        }

        $check_slug = $queryobj->first();

        // if check_slug is empty then return slug
        if (is_null($check_slug)) {
            return $slug;
        }

        // if slug is duplicate then add count to slug and check again
        $count = 0;
        $check_slug = DB::table($table)->where($field, 'LIKE', $slug.'-%')->select($field)->latest()->first();
        if (! is_null($check_slug)) {
            $count = (int) substr((string) $check_slug->$field, -1);
        }

        $slug .= '-'.++$count;

        return getUniqueSlug($table, $field, $slug, false);
    }
}

if (! function_exists('encrypt')) {
    function encrypt($password)
    {
        return Crypt::encryptString($password);
    }
}

if (! function_exists('decrypt')) {
    function decrypt($password)
    {
        $response_password = '';
        try {
            $response_password = Crypt::decryptString($password);
        } catch (DecryptException) {
            $response_password = '';
        }

        return $response_password;
    }
}

if (! function_exists('getContentDetails')) {
    function getContentDetails($post_content, $word_per_min = 'average'): array
    {
        $words_per_minutes = [
            'slow' => 100,
            'average' => 180,
            'fast' => 250,
        ];

        $post_content = mb_convert_encoding($post_content, 'UTF-8', 'UTF-8');

        $tags = ['</'];
        $post_content = str_replace($tags, '&nbsp;</', $post_content);

        $plain_string = strip_tags($post_content);
        $plain_string = str_replace(PHP_EOL, ' ', $plain_string);
        $plain_string = trim((string) preg_replace('/&#?[a-z0-9]+;/i', ' ', $plain_string));
        $plain_string = preg_replace(['/\r+/', '/\s+/'], ' ', $plain_string);

        $total_words = Str::of($plain_string)->wordCount();

        $per_menuts_words = $words_per_minutes[$word_per_min];

        $time_to_read = $total_words / $per_menuts_words;
        $total_minutes = (int) floor($time_to_read);
        $extra_seconds = (int) round(($time_to_read - $total_minutes) * 60);

        $rounded_time_to_read_in_sec = ($total_minutes * 60) + $extra_seconds;

        if ($rounded_time_to_read_in_sec === 0) {
            $rounded_time_to_read_in_sec = 60;
        }

        $readable_reading_time = getSecondsToReadableTime($rounded_time_to_read_in_sec);

        return [
            'words_count' => $total_words,
            'plain_text' => $plain_string,
            'reading_sec' => $rounded_time_to_read_in_sec,
            'readable_reading_time' => $readable_reading_time,
        ];
    }
}

if (! function_exists('getSecondsToReadableTime')) {
    function getSecondsToReadableTime($seconds, $informat = ''): string
    {
        $readable_reading_time = '';
        if (! empty($seconds)) {
            $org_seconds = $seconds;
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds / 60) % 60);
            $seconds %= 60;
            if ($informat === 'minutes') {
                $minutes = floor($org_seconds / 60);
                $tmp_second = $org_seconds % 60;
                if ($tmp_second > 30) {
                    $minutes++;
                }

                return $minutes.' minutes ';
            }

            if ($informat === 'full') {
                if ($hours > 0) {
                    $readable_reading_time = $hours.' hour ';
                }

                if ($minutes > 0) {
                    $readable_reading_time .= $minutes.' minutes ';
                }

                if ($seconds > 0) {
                    $readable_reading_time .= ($readable_reading_time === '' || $readable_reading_time === '0' ? '' : 'and ').$seconds.' seconds ';
                }
            } else {
                if ($hours > 0) {
                    $readable_reading_time = $hours.' hour ';
                }

                if ($seconds > 30) {
                    $minutes++;
                }

                if ($minutes > 0) {
                    $readable_reading_time .= $minutes.' min ';
                }
            }
        } else {
            $readable_reading_time = '1 min';
        }

        // if($seconds > 30){
        //     $readable_reading_time .= ((!empty($readable_reading_time))?'and ':'').$seconds.' seconds ';
        // }

        return $readable_reading_time;
    }
}

if (! function_exists('generateXmlFile')) {
    function generateXmlFile($sitemap_string, string $file_type, ?string $file_no): bool
    {
        if (! empty($sitemap_string)) {
            $dom = new DOMDocument;
            $dom->preserveWhiteSpace = false;
            $dom->loadXML($sitemap_string);
            // Save XML as a file
            $file_path = public_path().'/sitemaps/'.$file_type.'/';
            if (! File::exists($file_path)) {
                File::makeDirectory($file_path, 0777, true); // creates directory
            }

            $file_name = ($file_no > 0 ? 'sitemap'.$file_no.'.xml' : 'sitemap.xml');
            $dom->save($file_path.$file_name);

            return true;
        }

        return false;
    }
}
