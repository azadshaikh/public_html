<?php

namespace Modules\CMS\Services\Components;

/**
 * Base class for Theme components
 */
abstract class ThemeComponent
{
    /**
     * Render the component
     *
     * @param  array  $params  Parameters passed to the component
     * @param  mixed  $template  Template context/instance
     * @return string Rendered HTML
     */
    abstract public function render(array $params, $template = null): string;

    /**
     * Get a parameter value with default fallback
     */
    protected function param(array $params, string $key, mixed $default = null): mixed
    {
        return $params[$key] ?? $default;
    }

    /**
     * Check if parameter exists
     */
    protected function hasParam(array $params, string $key): bool
    {
        return isset($params[$key]);
    }

    /**
     * Get all parameters
     */
    protected function params(array $params): array
    {
        return $params;
    }

    /**
     * Escape HTML for safe output
     */
    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Build HTML attributes from array
     */
    protected function buildAttributes(array $attributes): string
    {
        $html = [];

        foreach ($attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $html[] = $this->escape($key);
                }
            } elseif ($value !== null) {
                $html[] = $this->escape($key).'="'.$this->escape((string) $value).'"';
            }
        }

        return implode(' ', $html);
    }
}
