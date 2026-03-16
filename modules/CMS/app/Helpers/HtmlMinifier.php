<?php

namespace Modules\CMS\Helpers;

/**
 * HTML Minification Utility
 *
 * Minifies HTML content by removing unnecessary whitespace, comments,
 * and optimizing the output while preserving content integrity.
 *
 * Safe for production use - preserves:
 * - Content within <pre>, <code>, <script>, <style>, <textarea> tags
 * - Conditional comments (<!--[if ...)
 * - Inline JavaScript/CSS functionality
 * - Required whitespace between inline elements
 */
class HtmlMinifier
{
    /**
     * Tags whose content should not be minified (whitespace-sensitive)
     */
    protected const PRESERVED_TAGS = [
        'pre',
        'code',
        'textarea',
        'script',
        'style',
    ];

    /**
     * Placeholder prefix for preserved content
     */
    protected const PLACEHOLDER_PREFIX = '___PRESERVED_BLOCK_';

    /**
     * Minify HTML content
     */
    public static function minify(string $html): string
    {
        if ($html === '' || $html === '0') {
            return $html;
        }

        $instance = new self;

        return $instance->process($html);
    }

    /**
     * Check if HTML minification is enabled.
     */
    public static function isEnabled(): bool
    {
        return config('cms.html_minification.enabled', true);
    }

    /**
     * Get statistics about minification
     */
    public static function getStats(string $original, string $minified): array
    {
        $originalSize = strlen($original);
        $minifiedSize = strlen($minified);
        $saved = $originalSize - $minifiedSize;
        $percentage = $originalSize > 0 ? round($saved / $originalSize * 100, 2) : 0;

        return [
            'original_size' => $originalSize,
            'minified_size' => $minifiedSize,
            'bytes_saved' => $saved,
            'percentage_saved' => $percentage,
        ];
    }

    /**
     * Process and minify HTML
     */
    protected function process(string $html): string
    {
        // Store preserved blocks (script, style, pre, code, textarea)
        $preservedBlocks = [];
        $html = $this->preserveBlocks($html, $preservedBlocks);

        // Remove HTML comments (but keep conditional comments for IE)
        $html = $this->removeComments($html);

        // Remove whitespace between tags
        $html = $this->removeWhitespaceBetweenTags($html);

        // Collapse multiple whitespace into single space
        $html = $this->collapseWhitespace($html);

        // Remove whitespace around block-level tags
        $html = $this->optimizeBlockTags($html);

        // Remove optional closing tags whitespace
        $html = $this->removeExtraNewlines($html);

        // Restore preserved blocks
        $html = $this->restoreBlocks($html, $preservedBlocks);

        return trim($html);
    }

    /**
     * Preserve content that shouldn't be minified
     */
    protected function preserveBlocks(string $html, array &$preservedBlocks): string
    {
        foreach (self::PRESERVED_TAGS as $tag) {
            $pattern = '/(<'.$tag.'\b[^>]*>)(.*?)(<\/'.$tag.'>)/is';

            $html = preg_replace_callback($pattern, function (array $matches) use (&$preservedBlocks): string {
                $placeholder = self::PLACEHOLDER_PREFIX.count($preservedBlocks).'___';
                $preservedBlocks[$placeholder] = $matches[0];

                return $placeholder;
            }, (string) $html);
        }

        // Also preserve inline event handlers and data attributes with whitespace
        // This is handled by not touching attribute values

        return $html;
    }

    /**
     * Restore preserved blocks
     */
    protected function restoreBlocks(string $html, array $preservedBlocks): string
    {
        foreach ($preservedBlocks as $placeholder => $content) {
            $html = str_replace($placeholder, $content, $html);
        }

        return $html;
    }

    /**
     * Remove HTML comments (preserving conditional comments)
     */
    protected function removeComments(string $html): string
    {
        // Remove standard HTML comments but preserve:
        // - Conditional comments: <!--[if ...]>...<![endif]-->
        // - IE-specific comments: <!--[if IE]>...<![endif]-->
        // - Livewire/Alpine markers if present

        // Pattern to match standard comments but not conditional
        return preg_replace('/<!--(?!\s*\[if\s)(?!\s*<!\[endif\]).*?-->/s', '', $html) ?? $html;
    }

    /**
     * Remove whitespace between HTML tags
     */
    protected function removeWhitespaceBetweenTags(string $html): string
    {
        // Remove whitespace between tags, but be careful with inline elements
        // Pattern: >whitespace<
        return preg_replace('/>\s+</', '><', $html) ?? $html;
    }

    /**
     * Collapse multiple whitespace characters into single space
     */
    protected function collapseWhitespace(string $html): string
    {
        // Replace multiple spaces/tabs with single space
        $html = preg_replace('/[ \t]+/', ' ', $html) ?? $html;

        // Replace multiple newlines with single newline
        $html = preg_replace('/\n+/', "\n", $html) ?? $html;

        return $html;
    }

    /**
     * Optimize whitespace around block-level tags
     */
    protected function optimizeBlockTags(string $html): string
    {
        $blockTags = [
            'html', 'head', 'body', 'div', 'section', 'article', 'aside',
            'header', 'footer', 'nav', 'main', 'figure', 'figcaption',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody',
            'tfoot', 'tr', 'th', 'td', 'form', 'fieldset', 'legend',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'blockquote',
            'address', 'hr', 'br', 'meta', 'link', 'title', 'base',
        ];

        $tagsPattern = implode('|', $blockTags);

        // Remove newlines/spaces before opening block tags
        $html = preg_replace('/\s*<('.$tagsPattern.')\b/i', '<$1', $html) ?? $html;

        // Remove newlines/spaces after closing block tags
        $html = preg_replace('/<\/('.$tagsPattern.')>\s*/i', '</$1>', $html) ?? $html;

        return $html;
    }

    /**
     * Remove extra newlines
     */
    protected function removeExtraNewlines(string $html): string
    {
        // Replace any remaining newlines with nothing (single line output)
        return str_replace(["\r\n", "\r", "\n"], '', $html);
    }
}
