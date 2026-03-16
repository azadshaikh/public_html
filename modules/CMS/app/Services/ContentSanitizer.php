<?php

namespace Modules\CMS\Services;

class ContentSanitizer
{
    /**
     * Sanitize HTML content to prevent XSS attacks while preserving safe formatting
     */
    public static function sanitizeHTML(string $html, ?array $allowedTags = null): string
    {
        if ($allowedTags === null) {
            // Default allowed tags for content
            $allowedTags = [
                'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'ul', 'ol', 'li', 'a', 'img', 'blockquote', 'div', 'span', 'table', 'thead',
                'tbody', 'tr', 'td', 'th', 'code', 'pre',
            ];
        }

        // Remove dangerous attributes
        $dangerousAttributes = [
            'onload', 'onerror', 'onclick', 'onmouseover', 'onmouseout', 'onfocus', 'onblur',
            'onchange', 'onsubmit', 'onreset', 'onselect', 'onabort', 'onkeydown', 'onkeypress',
            'onkeyup', 'style', 'javascript', 'vbscript', 'data',
        ];

        // Remove script tags and their content
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<\/?\s*script[^>]*>/i', '', (string) $html);

        // Remove dangerous attributes
        foreach ($dangerousAttributes as $attr) {
            $html = preg_replace('/\s'.preg_quote($attr, '/').'\s*=\s*["\'][^"\']*["\']/i', '', (string) $html);
        }

        // Remove javascript: and data: URLs
        $html = preg_replace('/href\s*=\s*["\']?\s*javascript\s*:/i', 'href="#"', (string) $html);
        $html = preg_replace('/src\s*=\s*["\']?\s*javascript\s*:/i', 'src=""', (string) $html);
        $html = preg_replace('/src\s*=\s*["\']?\s*data\s*:/i', 'src=""', (string) $html);

        // Strip all tags except allowed ones
        $allowedTagsString = '<'.implode('><', $allowedTags).'>';
        $html = strip_tags((string) $html, $allowedTagsString);

        return trim($html);
    }

    /**
     * Sanitize CSS content to prevent XSS attacks
     */
    public static function sanitizeCSS(string $css): string
    {
        // Remove potentially dangerous CSS functions and properties
        $dangerousPatterns = [
            '/javascript\s*:/i',           // JavaScript URLs
            '/data\s*:\s*[^;]*base64/i',   // Base64 data URLs
            '/expression\s*\(/i',          // IE expression
            '/behavior\s*:/i',             // IE behaviors
            '/@import/i',                  // CSS imports
            '/url\s*\(\s*[\'"]?javascript/i', // JavaScript in URL
            '/url\s*\(\s*[\'"]?data:/i',   // Data URLs in CSS
            '/-moz-binding/i',             // Mozilla binding
            '/vbscript\s*:/i',             // VBScript URLs
            '/content\s*:\s*[\'"][^\'"]*(javascript|data:)/i', // Content with JS/data
        ];

        foreach ($dangerousPatterns as $pattern) {
            $css = preg_replace($pattern, '', (string) $css);
        }

        // Remove any remaining script tags that might have slipped through
        $css = preg_replace('/<script[^>]*>.*?<\/script>/is', '', (string) $css);
        $css = preg_replace('/<\/?\s*script[^>]*>/i', '', (string) $css);

        // Remove HTML tags except for comments
        $css = strip_tags((string) $css);

        // Additional security: limit file size
        if (strlen($css) > 500000) { // 500KB limit
            $css = substr($css, 0, 500000).'/* CSS truncated for security */';
        }

        return trim($css);
    }

    /**
     * Sanitize JavaScript content to prevent dangerous code execution
     */
    public static function sanitizeJS($js): string
    {
        if (is_array($js)) {
            return implode("\n", array_map(self::sanitizeJSString(...), $js));
        }

        return self::sanitizeJSString($js);
    }

    /**
     * Sanitize theme options to prevent injection
     */
    public static function sanitizeThemeOption(string $key, $value): bool|string|float|int|array
    {
        // Sanitize key
        $key = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);

        if (is_string($value)) {
            // Handle boolean-like strings for checkbox options
            if ($value === 'true') {
                return true;
            }

            // Handle boolean-like strings for checkbox options
            if ($value === 'false') {
                return false;
            }

            // Check if it's a color value
            if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $value)) {
                return $value; // Valid color
            }

            // Check if it's a URL
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                // Only allow http/https URLs
                if (preg_match('/^https?:\/\//', $value)) {
                    return $value;
                }

                return '';
            }

            // Check if it's a number
            if (is_numeric($value)) {
                return $value;
            }

            // For text values, sanitize HTML
            return self::sanitizeHTML($value, ['strong', 'em', 'b', 'i']);
        }

        if (is_bool($value)) {
            // Keep boolean as boolean
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn ($item): array|bool|float|int|string => self::sanitizeThemeOption($key, $item), $value);
        }

        return '';
    }

    /**
     * Check if content is safe for output
     */
    public static function isSafeContent(string $content): bool
    {
        $dangerousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',  // Event handlers like onclick, onload, etc.
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/data:\s*text\/html/i',
            '/data:\s*image\/svg\+xml/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize file upload content
     */
    public static function sanitizeUploadedFile(string $filename, string $content): array
    {
        $errors = [];

        // Get max file size from config (default to 10MB if not set)
        $maxFileSize = config('media-library.max_file_size', 10 * 1024 * 1024);
        $maxFileSizeMB = round($maxFileSize / (1024 * 1024), 2);

        // Check file size
        if (strlen($content) > $maxFileSize) {
            $errors[] = sprintf('File size exceeds maximum allowed (%sMB)', $maxFileSizeMB);
        }

        // Check for ZIP bombs (high compression ratio)
        if (str_ends_with($filename, '.zip')) {
            $compressionRatio = $content !== '' ? strlen(gzcompress($content)) / strlen($content) : 0;
            if ($compressionRatio < 0.01) { // Less than 1% compression suggests ZIP bomb
                $errors[] = 'Potential ZIP bomb detected';
            }
        }

        // Check for executable files in disguise
        $executableSignatures = [
            "\x4D\x5A", // PE executable
            "\x7FELF",   // ELF executable
            '#!/bin/',   // Shell script
            '<?php',     // PHP script (if not expected)
        ];

        foreach ($executableSignatures as $signature) {
            if (str_starts_with($content, $signature)) {
                $errors[] = 'Executable file detected';
                break;
            }
        }

        return $errors;
    }

    /**
     * Sanitize a single JavaScript string
     */
    private static function sanitizeJSString(string $js): string
    {
        // List of dangerous JavaScript patterns to remove
        $dangerousPatterns = [
            '/eval\s*\(/i',                    // eval function
            '/Function\s*\(/i',                // Function constructor
            '/new\s+Function/i',               // Function constructor
            '/document\.write/i',              // Document write
            '/document\.writeln/i',            // Document writeln
            '/innerHTML\s*=/i',                // innerHTML manipulation
            '/outerHTML\s*=/i',                // outerHTML manipulation
            '/insertAdjacentHTML/i',           // insertAdjacentHTML
            '/setTimeout\s*\(\s*[\'"][^\'"]*[\'"]/i', // setTimeout with string
            '/setInterval\s*\(\s*[\'"][^\'"]*[\'"]/i', // setInterval with string
            '/location\s*=/i',                 // Location redirection
            '/window\.location/i',             // Window location
            '/document\.location/i',           // Document location
            '/XMLHttpRequest/i',               // AJAX requests
            '/fetch\s*\(/i',                   // Fetch API
            '/import\s*\(/i',                  // Dynamic imports
            '/require\s*\(/i',                 // CommonJS require
            '/exec\s*\(/i',                    // RegExp exec
            '/constructor/i',                  // Constructor access
            '/prototype\s*\[/i',               // Prototype manipulation
            '/alert\s*\(/i',                   // Alert (remove for security)
            '/confirm\s*\(/i',                 // Confirm dialogs
            '/prompt\s*\(/i',                  // Prompt dialogs
            '/__proto__/i',                    // Prototype pollution
            '/globalThis/i',                   // Global access
        ];

        foreach ($dangerousPatterns as $pattern) {
            $js = preg_replace($pattern, '', (string) $js);
        }

        // Remove script tags
        $js = preg_replace('/<script[^>]*>.*?<\/script>/is', '', (string) $js);
        $js = preg_replace('/<\/?\s*script[^>]*>/i', '', (string) $js);

        // Remove HTML tags
        $js = strip_tags((string) $js);

        // Additional security: limit file size
        if (strlen($js) > 100000) { // 100KB limit
            return '// JavaScript content was truncated for security reasons';
        }

        // If it contains complex dangerous patterns, sanitize heavily
        // Very restrictive: only allow simple variable assignments
        if (preg_match('/[{}();].*[{}();]/s', $js) && preg_match('/[^a-zA-Z0-9\s=\'".,;:\-_+*\/]/', $js)) {
            return '// JavaScript content was sanitized for security';
        }

        return trim($js);
    }
}
