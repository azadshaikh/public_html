<?php

namespace Modules\CMS\Http\Controllers\Concerns;

use App\Support\CacheInvalidation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\CMS\Models\Theme;

trait InteractsWithThemeEditorFileTree
{
    /**
     * Build file tree structure from theme directory
     */
    private function getFileTree(string $directory): array
    {
        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return [];
        }

        $themePath = $theme['path'];
        $tree = [];

        $this->scanDirectory($themePath, $themePath, $tree, $directory);
        $localPaths = $this->extractAllPaths($tree);

        if (Theme::isChildTheme($directory)) {
            $hierarchy = Theme::getThemeHierarchy($directory);
            array_shift($hierarchy);

            foreach ($hierarchy as $parentDirectory) {
                $parentTheme = Theme::getThemeInfo($parentDirectory);
                if ($parentTheme) {
                    $tree = $this->mergeParentFiles(
                        $tree,
                        $parentTheme['path'],
                        $localPaths,
                        $parentDirectory,
                    );
                }
            }
        }

        return $tree;
    }

    /**
     * Merge parent theme files into child theme file tree
     * Files from parent that don't exist in child will be marked as inherited
     */
    private function mergeParentFiles(
        array $childTree,
        string $parentPath,
        array $localPaths,
        string $parentDirectory,
    ): array {
        $parentTree = [];
        $this->scanDirectory(
            $parentPath,
            $parentPath,
            $parentTree,
            $parentDirectory,
            true,
        );

        $childPaths = $this->extractAllPaths($childTree);

        $this->addInheritedFiles(
            $childTree,
            $parentTree,
            $childPaths,
            $localPaths,
            $parentDirectory,
        );

        $this->sortTree($childTree);

        return $childTree;
    }

    /**
     * Extract all file/directory paths from a tree (recursively)
     */
    private function extractAllPaths(array $tree): array
    {
        $paths = [];
        foreach ($tree as $item) {
            $paths[] = $item['path'];
            if ($item['type'] === 'directory' && ! empty($item['children'])) {
                $paths = array_merge(
                    $paths,
                    $this->extractAllPaths($item['children']),
                );
            }
        }

        return $paths;
    }

    /**
     * Add inherited files from parent to child tree
     */
    private function addInheritedFiles(
        array &$childTree,
        array $parentTree,
        array $childPaths,
        array $localPaths,
        string $parentDirectory,
    ): void {
        foreach ($parentTree as $parentItem) {
            if ($parentItem['type'] === 'file') {
                if (! in_array($parentItem['path'], $childPaths)) {
                    $parentItem['inherited'] = true;
                    $parentItem['inheritedFrom'] = $parentDirectory;
                    $this->addToTree($childTree, $parentItem);
                } elseif (in_array($parentItem['path'], $localPaths)) {
                    $this->markAsOverride(
                        $childTree,
                        $parentItem['path'],
                        $parentDirectory,
                    );
                }
            } elseif ($parentItem['type'] === 'directory') {
                $existingDir = $this->findInTree($childTree, $parentItem['path']);
                if ($existingDir === null) {
                    $parentItem['inherited'] = true;
                    $parentItem['inheritedFrom'] = $parentDirectory;
                    $this->markAllAsInherited(
                        $parentItem['children'],
                        $parentDirectory,
                    );
                    $childTree[] = $parentItem;
                } else {
                    foreach ($childTree as &$item) {
                        if (
                            $item['path'] === $parentItem['path']
                            && $item['type'] === 'directory'
                        ) {
                            $this->addInheritedFiles(
                                $item['children'],
                                $parentItem['children'],
                                $this->extractAllPaths($item['children']),
                                $localPaths,
                                $parentDirectory,
                            );
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Add item to appropriate location in tree based on path
     */
    private function addToTree(array &$tree, array $item): void
    {
        $pathParts = explode('/', (string) $item['path']);

        if (count($pathParts) === 1) {
            $tree[] = $item;

            return;
        }

        $parentPath = implode('/', array_slice($pathParts, 0, -1));
        foreach ($tree as &$treeItem) {
            if (
                $treeItem['type'] === 'directory'
                && $treeItem['path'] === $parentPath
            ) {
                $treeItem['children'][] = $item;

                return;
            }

            if (
                $treeItem['type'] === 'directory'
                && str_starts_with($parentPath, $treeItem['path'].'/')
            ) {
                $this->addToTree($treeItem['children'], $item);

                return;
            }
        }

        $tree[] = $item;
    }

    /**
     * Find item in tree by path
     */
    private function findInTree(array $tree, string $path): ?array
    {
        foreach ($tree as $item) {
            if ($item['path'] === $path) {
                return $item;
            }

            if ($item['type'] === 'directory' && ! empty($item['children'])) {
                $found = $this->findInTree($item['children'], $path);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Mark a file in tree as override (exists in both child and parent)
     */
    private function markAsOverride(
        array &$tree,
        string $path,
        string $parentDirectory,
    ): void {
        foreach ($tree as &$item) {
            if ($item['path'] === $path && $item['type'] === 'file') {
                if (empty($item['override'])) {
                    $item['override'] = true;
                    $item['overrides'] = $parentDirectory;
                }

                return;
            }

            if ($item['type'] === 'directory' && ! empty($item['children'])) {
                $this->markAsOverride($item['children'], $path, $parentDirectory);
            }
        }
    }

    /**
     * Mark all files in a tree branch as inherited
     */
    private function markAllAsInherited(
        array &$tree,
        string $parentDirectory,
    ): void {
        foreach ($tree as &$item) {
            $item['inherited'] = true;
            $item['inheritedFrom'] = $parentDirectory;
            if ($item['type'] === 'directory' && ! empty($item['children'])) {
                $this->markAllAsInherited($item['children'], $parentDirectory);
            }
        }
    }

    /**
     * Sort tree recursively
     */
    private function sortTree(array &$tree): void
    {
        usort($tree, function (array $left, array $right): int {
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'directory' ? -1 : 1;
            }

            return strcasecmp((string) $left['name'], (string) $right['name']);
        });

        foreach ($tree as &$item) {
            if ($item['type'] === 'directory' && ! empty($item['children'])) {
                $this->sortTree($item['children']);
            }
        }
    }

    /**
     * Recursively scan directory and build tree structure
     */
    private function scanDirectory(
        string $basePath,
        string $currentPath,
        array &$tree,
        string $themeDirectory,
        bool $isParent = false,
    ): void {
        $items = File::files($currentPath);
        $directories = File::directories($currentPath);

        foreach ($items as $file) {
            $relativePath = str_replace(
                $basePath.'/',
                '',
                $file->getPathname(),
            );
            $extension = strtolower($file->getExtension());

            $fileItem = [
                'name' => $file->getFilename(),
                'path' => $relativePath,
                'type' => 'file',
                'extension' => $extension,
                'size' => $file->getSize(),
                'editable' => in_array($extension, self::ALLOWED_EXTENSIONS),
                'protected' => in_array($relativePath, self::PROTECTED_FILES),
            ];

            if ($isParent) {
                $fileItem['inherited'] = true;
                $fileItem['inheritedFrom'] = $themeDirectory;
            }

            $tree[] = $fileItem;
        }

        foreach ($directories as $directory) {
            $relativePath = str_replace($basePath.'/', '', $directory);
            $directoryName = basename((string) $directory);

            if (Str::startsWith($directoryName, '.')) {
                continue;
            }

            if (in_array($directoryName, ['node_modules', 'vendor'])) {
                continue;
            }

            $children = [];
            $this->scanDirectory(
                $basePath,
                $directory,
                $children,
                $themeDirectory,
                $isParent,
            );

            $directoryItem = [
                'name' => $directoryName,
                'path' => $relativePath,
                'type' => 'directory',
                'children' => $children,
            ];

            if ($isParent) {
                $directoryItem['inherited'] = true;
                $directoryItem['inheritedFrom'] = $themeDirectory;
            }

            $tree[] = $directoryItem;
        }

        usort($tree, function (array $left, array $right): int {
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'directory' ? -1 : 1;
            }

            return strcasecmp((string) $left['name'], (string) $right['name']);
        });
    }

    /**
     * Validate file path for security
     */
    private function validateFilePath(string $path): bool
    {
        if (str_contains($path, '..') || str_contains($path, '\\')) {
            return false;
        }

        if (Str::startsWith($path, '/')) {
            return false;
        }

        if (Str::contains($path, '/.')) {
            return false;
        }

        return ! Str::startsWith($path, '.');
    }

    /**
     * Check if file extension is allowed for editing
     */
    private function isAllowedExtension(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS);
    }

    /**
     * Get Monaco editor language from file extension
     */
    private function getEditorLanguage(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'twig' => 'twig',
            'js' => 'javascript',
            'ts' => 'typescript',
            'css' => 'css',
            'scss', 'sass' => 'scss',
            'json' => 'json',
            'html', 'htm' => 'html',
            'xml', 'svg' => 'xml',
            'md' => 'markdown',
            'php' => 'php',
            'txt' => 'plaintext',
            default => 'plaintext',
        };
    }

    /**
     * Get default content for new files
     */
    private function getDefaultContent(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $filename = pathinfo($path, PATHINFO_FILENAME);

        return match ($extension) {
            'tpl' => "{* {$filename} template *}\n\n",
            'css' => "/* {$filename} styles */\n\n",
            'scss' => "// {$filename} styles\n\n",
            'js' => "// {$filename}\n\n",
            'json' => "{\n    \n}\n",
            'html' => "<!DOCTYPE html>\n<html>\n<head>\n    <title>{$filename}</title>\n</head>\n<body>\n    \n</body>\n</html>\n",
            'md' => "# {$filename}\n\n",
            default => '',
        };
    }

    /**
     * Sanitize and validate uploaded file names
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = trim($filename);

        if (
            $filename === ''
            || str_contains($filename, '..')
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
        ) {
            return '';
        }

        if (Str::startsWith($filename, '.')) {
            return '';
        }

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            return '';
        }

        return $filename;
    }

    private function invalidateFrontendCache(): void
    {
        CacheInvalidation::touch('Theme editor change', dispatchRecache: true);
    }

    /**
     * Log theme-related activity
     */
    private function logThemeActivity(
        string $description,
        string $directory,
        array $properties = [],
    ): void {
        $activity = activity();

        $user = auth()->guard()->user();
        if ($user) {
            $activity->causedBy($user);
        }

        $activity
            ->withProperties(
                array_merge(['theme_directory' => $directory], $properties),
            )
            ->log($description);
    }

    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, mixed>
     */
    private function mapThemeSummary(array $theme): array
    {
        $directory = (string) ($theme['directory'] ?? '');

        return [
            'directory' => $directory,
            'name' => (string) ($theme['name'] ?? Str::headline($directory)),
            'description' => (string) ($theme['description'] ?? ''),
            'author' => (string) ($theme['author'] ?? ''),
            'version' => (string) ($theme['version'] ?? '1.0.0'),
            'screenshot' => $theme['screenshot'] ?? null,
            'is_active' => (bool) ($theme['is_active'] ?? false),
            'parent' => isset($theme['parent']) && is_string($theme['parent'])
                ? $theme['parent']
                : null,
        ];
    }
}
