<?php

namespace Modules\CMS\Services;

use Illuminate\Support\Facades\File;
use Modules\CMS\Events\ThemeValidated;
use Modules\CMS\Models\Theme;

class ThemeValidationService
{
    /**
     * Validate a theme manifest file
     */
    public function validateManifest(array $manifest): array
    {
        $errors = [];
        $requiredFields = ['name', 'version', 'description'];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (! isset($manifest[$field]) || empty($manifest[$field])) {
                $errors[] = 'Missing required field: '.$field;
            }
        }

        // Validate version format
        if (isset($manifest['version']) && ! preg_match('/^\d+\.\d+\.\d+$/', $manifest['version'])) {
            $errors[] = 'Invalid version format. Use semantic versioning (e.g., 1.0.0)';
        }

        // Validate author structure
        if (isset($manifest['author'])) {
            if (! is_array($manifest['author'])) {
                $errors[] = "Author field must be an object with 'name' and 'uri' properties";
            } elseif (! isset($manifest['author']['name']) || empty($manifest['author']['name'])) {
                $errors[] = 'Author name is required';
            }
        }

        // Validate license structure
        if (isset($manifest['license']) && ! is_array($manifest['license'])) {
            $errors[] = "License field must be an object with 'name' and 'uri' properties";
        }

        // Validate requirements
        if (isset($manifest['requirements'])) {
            if (! is_array($manifest['requirements'])) {
                $errors[] = 'Requirements field must be an object';
            } elseif (isset($manifest['requirements']['php'])) {
                if (! version_compare(PHP_VERSION, $manifest['requirements']['php'], '>=')) {
                    $errors[] = sprintf('PHP version %s or higher is required', $manifest['requirements']['php']);
                }
            }
        }

        // Validate supports structure
        if (isset($manifest['supports']) && ! is_array($manifest['supports'])) {
            $errors[] = 'Supports field must be an object';
        }

        // Validate assets structure
        if (isset($manifest['assets']) && ! is_array($manifest['assets'])) {
            $errors[] = 'Assets field must be an object';
        }

        // Validate template files list
        if (isset($manifest['template_files'])) {
            if (! is_array($manifest['template_files'])) {
                $errors[] = 'Template files field must be an array';
            } else {
                foreach ($manifest['template_files'] as $template) {
                    if (! is_string($template)) {
                        $errors[] = 'Template file names must be strings';
                        break;
                    }

                    if (! str_ends_with($template, '.tpl') && $template !== 'config.json') {
                        $errors[] = 'Template files must end with .tpl or be config.json';
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate config.json file for security
     */
    public function validateThemeConfig(string $configContent): array
    {
        $errors = [];

        // Check file size (max 50KB)
        if (strlen($configContent) > 51200) {
            $errors[] = 'Config.json file is too large (max 50KB allowed)';
        }

        // Parse JSON
        $config = json_decode($configContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Invalid JSON format in config.json: '.json_last_error_msg();

            return $errors;
        }

        // Use ThemeConfigService for validation
        $configService = resolve(ThemeConfigService::class);
        $validationErrors = $configService->validateConfig($config);

        return array_merge($errors, $validationErrors);
    }

    /**
     * Validate theme templates
     */
    public function validateTemplates(string $themePath): array
    {
        $errors = [];
        $templatesPath = $themePath.'/templates';

        if (! File::isDirectory($templatesPath)) {
            $errors[] = 'Templates directory is missing';

            return $errors;
        }

        $templateFiles = collect(File::allFiles($templatesPath))
            ->filter(fn ($file): bool => strtolower((string) $file->getExtension()) === 'tpl')
            ->values();

        if ($templateFiles->isEmpty()) {
            $errors[] = 'No .tpl templates found in templates directory';

            return $errors;
        }

        foreach ($templateFiles as $templateFile) {
            $templateName = $templateFile->getFilename();
            $templateErrors = $this->validateTemplateFile($templateFile->getPathname());

            foreach ($templateErrors as $error) {
                $errors[] = sprintf('Template %s: %s', $templateName, $error);
            }
        }

        return $errors;
    }

    /**
     * Validate complete theme directory
     */
    public function validateTheme(string $themeDirectory): array
    {
        $themePath = Theme::getThemesPath().'/'.$themeDirectory;
        $allErrors = [];

        // Check if theme directory exists
        if (! File::isDirectory($themePath)) {
            return ['Theme directory does not exist'];
        }

        // Validate manifest.json
        $manifestPath = $themePath.'/manifest.json';
        if (! File::exists($manifestPath)) {
            $allErrors[] = 'manifest.json file is missing';
        } else {
            $manifestContent = File::get($manifestPath);
            $manifest = json_decode($manifestContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $allErrors[] = 'manifest.json contains invalid JSON';
            } else {
                $manifestErrors = $this->validateManifest($manifest);
                $allErrors = array_merge($allErrors, $manifestErrors);
            }
        }

        // Validate config.json if it exists
        $configPath = $themePath.'/config/config.json';
        if (File::exists($configPath)) {
            $configContent = File::get($configPath);
            $configErrors = $this->validateThemeConfig($configContent);
            $allErrors = array_merge($allErrors, $configErrors);
        }

        // Validate templates
        $templateErrors = $this->validateTemplates($themePath);
        $allErrors = array_merge($allErrors, $templateErrors);

        // Fire validation event
        $themeInfo = Theme::getThemeInfo($themeDirectory);
        if ($themeInfo) {
            event(new ThemeValidated($themeInfo, $allErrors === [], $allErrors));
        }

        return $allErrors;
    }

    /**
     * Get security recommendations for a theme
     */
    public function getSecurityRecommendations(string $themeDirectory): array
    {
        $recommendations = [];
        $themePath = Theme::getThemesPath().'/'.$themeDirectory;

        // Check for config.json
        if (! File::exists($themePath.'/config/config.json')) {
            $recommendations[] = 'Consider adding a config.json file for theme customization options';
        }

        // Check for screenshot
        $hasScreenshot = false;
        $screenshotExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        foreach ($screenshotExtensions as $ext) {
            if (File::exists($themePath.'/screenshot.'.$ext)) {
                $hasScreenshot = true;
                break;
            }
        }

        if (! $hasScreenshot) {
            $recommendations[] = 'Add a screenshot.png file to showcase your theme';
        }

        // Check for README
        if (! File::exists($themePath.'/README.md') && ! File::exists($themePath.'/readme.txt')) {
            $recommendations[] = 'Add a README file with theme documentation';
        }

        // Check for CSS files in assets folder
        if (! File::exists($themePath.'/assets/css/style.css')) {
            $recommendations[] = 'Add a style.css file in assets/css/ folder for theme styles';
        }

        return $recommendations;
    }

    /**
     * Validate a single template file
     */
    private function validateTemplateFile(string $templatePath): array
    {
        $errors = [];
        $content = File::get($templatePath);

        // Check file size (max 1MB)
        if (strlen($content) > 1048576) {
            $errors[] = 'Template file is too large (max 1MB allowed)';
        }

        // Check for dangerous PHP in templates
        $dangerousPatterns = [
            '/\<\?php\s+(exec|shell_exec|system|eval)\s*\(/i',
            '/\{\{\s*(\$_GET|\$_POST|\$_REQUEST)/i',
            '/\@php\s+(exec|shell_exec|system|eval)\s*\(/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $errors[] = 'Dangerous PHP code detected';
                break;
            }
        }

        return $errors;
    }
}
