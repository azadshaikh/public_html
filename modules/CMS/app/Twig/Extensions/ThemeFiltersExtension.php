<?php

namespace Modules\CMS\Twig\Extensions;

use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Custom Twig filters for theme templates
 */
class ThemeFiltersExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // String modifiers
            new TwigFilter('str_replace', $this->strReplace(...)),
            new TwigFilter('limit', $this->limit(...)),
            new TwigFilter('truncate', $this->limit(...)), // Alias
            new TwigFilter('excerpt', $this->excerpt(...)),
            new TwigFilter('slug', $this->slug(...)),
            new TwigFilter('snake', $this->snake(...)),
            new TwigFilter('camel', $this->camel(...)),
            new TwigFilter('kebab', $this->kebab(...)),
            new TwigFilter('studly', $this->studly(...)),
            new TwigFilter('starts_with', $this->startsWith(...)),
            new TwigFilter('ends_with', $this->endsWith(...)),
            new TwigFilter('contains', $this->contains(...)),

            // File path modifiers
            new TwigFilter('extension', $this->extension(...)),
            new TwigFilter('basename', $this->basename(...)),
            new TwigFilter('dirname', $this->dirname(...)),

            // Number/formatting modifiers
            new TwigFilter('file_size', $this->fileSize(...)),
            new TwigFilter('money', $this->money(...)),
            new TwigFilter('currency', $this->currency(...)),
            new TwigFilter('pluralize', $this->pluralize(...)),

            // Encoding modifiers
            new TwigFilter('json_decode', $this->jsonDecode(...)),
            new TwigFilter('md5', $this->md5(...)),
            new TwigFilter('base64_encode', $this->base64Encode(...)),

            // Date/Time modifiers
            new TwigFilter('time_ago', $this->timeAgo(...)),

            // Utility modifiers
            new TwigFilter('count_words', $this->countWords(...)),
        ];
    }

    /**
     * String replace filter
     * Usage: {{ var|str_replace('_', '-') }}
     */
    public function strReplace(?string $value, string $search, string $replace): string
    {
        if ($value === null) {
            return '';
        }

        return str_replace($search, $replace, $value);
    }

    /**
     * Limit string length
     * Usage: {{ text|limit(100, '...') }}
     */
    public function limit(?string $value, int $limit = 100, string $end = '...'): string
    {
        if ($value === null) {
            return '';
        }

        return Str::limit($value, $limit, $end);
    }

    /**
     * Excerpt from HTML
     * Usage: {{ content|excerpt(200) }}
     */
    public function excerpt(?string $value, int $length = 200): string
    {
        if ($value === null) {
            return '';
        }

        return Str::limit(strip_tags($value), $length);
    }

    /**
     * Slugify string
     * Usage: {{ title|slug }}
     */
    public function slug(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return Str::slug($value);
    }

    /**
     * Convert to snake case
     * Usage: {{ name|snake }}
     */
    public function snake(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return Str::snake($value);
    }

    /**
     * Convert to camel case
     * Usage: {{ name|camel }}
     */
    public function camel(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return Str::camel($value);
    }

    /**
     * Convert to kebab case
     * Usage: {{ name|kebab }}
     */
    public function kebab(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return Str::kebab($value);
    }

    /**
     * Convert to studly case
     * Usage: {{ name|studly }}
     */
    public function studly(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return Str::studly($value);
    }

    /**
     * Check if string starts with
     * Usage: {% if url|starts_with('http') %}
     */
    public function startsWith(?string $haystack, string $needle): bool
    {
        if ($haystack === null) {
            return false;
        }

        return Str::startsWith($haystack, $needle);
    }

    /**
     * Check if string ends with
     * Usage: {% if file|ends_with('.pdf') %}
     */
    public function endsWith(?string $haystack, string $needle): bool
    {
        if ($haystack === null) {
            return false;
        }

        return Str::endsWith($haystack, $needle);
    }

    /**
     * Check if string contains
     * Usage: {% if text|contains('keyword') %}
     */
    public function contains(?string $haystack, string $needle): bool
    {
        if ($haystack === null) {
            return false;
        }

        return Str::contains($haystack, $needle);
    }

    /**
     * Get file extension
     * Usage: {{ filename|extension }}
     */
    public function extension(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        return pathinfo($path, PATHINFO_EXTENSION);
    }

    /**
     * Get basename
     * Usage: {{ path|basename }}
     */
    public function basename(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        return basename($path);
    }

    /**
     * Get dirname
     * Usage: {{ path|dirname }}
     */
    public function dirname(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        return dirname($path);
    }

    /**
     * Format file size
     * Usage: {{ bytes|file_size }}
     */
    public function fileSize(?int $bytes, int $precision = 2): string
    {
        if ($bytes === null || $bytes < 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * Format money/currency
     * Usage: {{ price|money('USD', 2) }}
     */
    public function money(?float $amount, string $currency = 'USD', int $decimals = 2): string
    {
        if ($amount === null) {
            return $currency.' 0.00';
        }

        return $currency.' '.number_format($amount, $decimals);
    }

    /**
     * Format as currency with symbol from settings
     * Usage: {{ amount|currency }}
     */
    public function currency(?float $amount): string
    {
        $currencySymbol = setting('currency_symbol', '$');

        if ($amount === null) {
            return $currencySymbol.'0.00';
        }

        return $currencySymbol.number_format($amount, 2);
    }

    /**
     * Pluralize word
     * Usage: {{ count|pluralize('item', 'items') }}
     */
    public function pluralize(?int $count, string $singular, string $plural): string
    {
        if ($count === null) {
            $count = 0;
        }

        return $count === 1 ? $singular : $plural;
    }

    /**
     * Decode JSON
     * Usage: {{ json|json_decode }}
     */
    public function jsonDecode(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return json_decode($value, true);
    }

    /**
     * MD5 hash
     * Usage: {{ email|md5 }}
     */
    public function md5(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return md5($value);
    }

    /**
     * Base64 encode
     * Usage: {{ data|base64_encode }}
     */
    public function base64Encode(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return base64_encode($value);
    }

    /**
     * Time ago (human-readable difference)
     * Usage: {{ date|time_ago }}
     */
    public function timeAgo(mixed $date): string
    {
        if ($date === null) {
            return '';
        }

        if (is_string($date)) {
            $date = new DateTime($date);
        }

        if (! $date instanceof DateTimeInterface) {
            return '';
        }

        $carbon = Date::instance($date);

        return $carbon->diffForHumans();
    }

    /**
     * Count words in string
     * Usage: {{ text|count_words }}
     */
    public function countWords(?string $value): int
    {
        if ($value === null) {
            return 0;
        }

        return str_word_count($value);
    }
}
