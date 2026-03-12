<?php

if (! function_exists('module_enabled')) {
    /**
     * Check if a module is enabled (active).
     *
     * @param  string  $moduleSlug  Module lower-name/slug, e.g. "cms", "todos"
     */
    function module_enabled(string $moduleSlug): bool
    {
        $moduleSlug = strtolower(trim($moduleSlug));

        if ($moduleSlug === '') {
            return false;
        }

        if (function_exists('active_modules')) {
            return (bool) active_modules($moduleSlug);
        }

        return false;
    }
}
