<?php

namespace Modules\CMS\Http\Controllers;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Jobs\RecacheApplication;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Modules\CMS\Models\Theme;
use Modules\CMS\Repositories\ThemeRepository;
use Modules\CMS\Services\ThemeAssetResolver;
use Modules\CMS\Services\ThemeConfigService;
use Modules\CMS\Services\ThemeValidationService;
use stdClass;
use ZipArchive;

class ThemeController extends Controller implements HasMiddleware
{
    use ActivityTrait;

    private const string MODULE_PATH = 'cms::themes';

    protected string $activityLogModule = 'Themes';

    protected string $activityEntityAttribute = 'name';

    public function __construct(
        protected ThemeRepository $themeRepository,
        protected ThemeValidationService $validationService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_themes', only: ['index', 'export']),
            new Middleware('permission:add_themes', only: ['import', 'createChild']),
            new Middleware('permission:edit_themes', only: ['activate', 'detach']),
            new Middleware('permission:delete_themes', only: ['destroy']),
        ];
    }

    /**
     * Display all file-based themes
     */
    public function index(Request $request): Factory|View
    {
        $search = $request->query('search');
        $filter = $request->query('filter');

        // Build filter array for repository
        $filters = [];

        if ($search) {
            $filters['search'] = $search;
        }

        if ($filter === 'supports') {
            $supports = $request->query('supports', []);
            if (! empty($supports)) {
                $filters['supports'] = $supports;
            }
        }

        // Get filtered themes using repository
        $themes = $this->themeRepository->getThemesFiltered($filters)->toArray();

        // Apply additional filters
        if ($filter === 'active') {
            $themes = array_filter($themes, fn (array $theme) => $theme['is_active']);
        } elseif ($filter === 'inactive') {
            $themes = array_filter($themes, fn (array $theme): bool => ! $theme['is_active']);
        }

        /** @var view-string $view */
        $view = self::MODULE_PATH.'.index';

        return view($view, ['themes' => $themes, 'search' => $search, 'filter' => $filter]);
    }

    /**
     * Activate a theme
     */
    public function activate(string $directory)
    {
        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return back()->with('error', 'Theme not found.');
        }

        if (Theme::activateTheme($directory)) {
            // Invalidate asset cache when theme changes
            ThemeAssetResolver::invalidate();

            // Dispatch job to rebuild all caches asynchronously (non-blocking)
            dispatch(new RecacheApplication('Theme activation: '.$directory));

            $this->logThemeActivity(
                ActivityAction::UPDATE,
                sprintf("Theme '%s' activated.", $theme['name']),
                $directory,
                $theme
            );

            return back()->with('success', sprintf("'%s' theme has been activated successfully!", $theme['name']));
        }

        return back()->with('error', 'Failed to activate theme. Please try again.');
    }

    /**
     * Export theme as ZIP
     */
    public function export(string $directory)
    {
        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return back()->with('error', 'Theme not found.');
        }

        $zipFileName = $theme['name'].'-'.($theme['version'] ?? '1.0.0').'.zip';
        $zipPath = storage_path('app/temp/'.$zipFileName);

        // Ensure temp directory exists
        if (! File::exists(dirname($zipPath))) {
            File::makeDirectory(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $this->addDirectoryToZip($zip, $theme['path'], $theme['directory']);
            $zip->close();

            $this->logThemeActivity(
                ActivityAction::EXPORT,
                sprintf("Theme '%s' exported.", $theme['name']),
                $directory,
                $theme
            );

            return Response::download($zipPath, $zipFileName)->deleteFileAfterSend();
        }

        return back()->with('error', 'Failed to create theme export.');
    }

    /**
     * Import theme from ZIP
     */
    public function import(Request $request)
    {
        $request->validate([
            'theme_zip' => ['required', 'file', 'mimes:zip', 'max:10240'], // 10MB max
        ]);

        $zipFile = $request->file('theme_zip');
        $extractPath = storage_path('app/temp/theme-import-'.time());

        $zip = new ZipArchive;
        if ($zip->open($zipFile->getPathname()) === true) {
            // Validate ZIP contents before extraction
            if (! $this->validateZipContents($zip)) {
                $zip->close();

                return back()->with('error', 'Invalid theme ZIP: Contains unsafe files or directory traversal attempts.');
            }

            $zip->extractTo($extractPath);
            $zip->close();

            // Find the main theme directory
            $themeDir = $this->findThemeDirectory($extractPath);

            if ($themeDir) {
                // Validate extracted theme files
                if (! $this->validateThemeFiles($themeDir)) {
                    File::deleteDirectory($extractPath);

                    return back()->with('error', 'Invalid theme: Contains unsafe files or content.');
                }

                $themeName = basename($themeDir);

                // Sanitize theme name
                $themeName = $this->sanitizeThemeName($themeName);
                $targetPath = Theme::getThemesPath().'/'.$themeName;

                // Check if theme already exists
                if (File::exists($targetPath)) {
                    File::deleteDirectory($extractPath);

                    return back()->with('error', 'Theme already exists: '.$themeName);
                }

                // Move theme to themes directory
                File::moveDirectory($themeDir, $targetPath);
                File::deleteDirectory($extractPath);

                $importedTheme = Theme::getThemeInfo($themeName);
                $this->logThemeActivity(
                    ActivityAction::IMPORT,
                    sprintf("Theme '%s' imported successfully.", $themeName),
                    $themeName,
                    $importedTheme
                );

                return to_route('cms.appearance.themes.index')
                    ->with('success', 'Theme imported successfully!');
            }

            File::deleteDirectory($extractPath);

            return back()->with('error', 'Invalid theme structure. No manifest.json found.');
        }

        return back()->with('error', 'Failed to extract theme ZIP file.');
    }

    /**
     * Delete theme
     */
    public function destroy(string $directory)
    {
        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return back()->with('error', 'Theme not found.');
        }

        // Protect default theme from deletion
        if (Theme::isProtectedTheme($directory)) {
            return back()->with('error', 'Cannot delete the default theme. It is a system theme required for the application to function properly.');
        }

        if ($theme['is_active']) {
            return back()->with('error', 'Cannot delete active theme. Please activate another theme first.');
        }

        // Check if theme has child themes
        if (Theme::hasChildThemes($directory)) {
            $children = Theme::getChildThemes($directory);
            $childNames = array_column($children, 'name');

            return back()->with('error', 'Cannot delete this theme. It has '.count($children).' child theme(s): '.implode(', ', $childNames).'. Please delete or detach the child themes first.');
        }

        File::deleteDirectory($theme['path']);

        // Invalidate asset cache when theme is deleted
        ThemeAssetResolver::invalidate();

        $this->logThemeActivity(
            ActivityAction::DELETE,
            sprintf("Theme '%s' deleted successfully.", $theme['name']),
            $directory,
            $theme
        );

        return to_route('cms.appearance.themes.index')
            ->with('success', 'Theme deleted successfully!');
    }

    /**
     * Create a child theme from a parent theme (auto-generates name and slug)
     */
    public function createChild(Request $request)
    {
        $validated = $request->validate([
            'parent_theme' => ['required', 'string', 'max:255'],
        ]);

        // Check if parent theme exists
        $parentTheme = Theme::getThemeInfo($validated['parent_theme']);
        if (! $parentTheme) {
            return back()->withErrors(['parent_theme' => 'Parent theme not found.'])->withInput();
        }

        // Auto-generate child name and slug
        $baseName = $parentTheme['name'].' Child';
        $baseSlug = Str::slug($parentTheme['name']).'-child';

        // Find unique name/slug by appending number if needed
        $childName = $baseName;
        $childSlug = $baseSlug;
        $counter = 1;

        while (Theme::getThemeInfo($childSlug)) {
            $counter++;
            $childName = $baseName.' '.$counter;
            $childSlug = $baseSlug.'-'.$counter;
        }

        // Validate hierarchy (prevent circular dependencies)
        if (! Theme::validateThemeHierarchy($childSlug, $validated['parent_theme'])) {
            return back()->withErrors(['parent_theme' => 'Invalid theme hierarchy. This would create a circular dependency.'])->withInput();
        }

        // Create child theme directory
        $childPath = Theme::getThemesPath().'/'.$childSlug;

        try {
            File::makeDirectory($childPath, 0755, true);

            // Create minimal manifest.json
            $manifest = [
                'name' => $childName,
                'parent' => $validated['parent_theme'],
                'description' => 'Child theme of '.$parentTheme['name'],
                'version' => '1.0.0',
                'author' => [
                    'name' => auth()->user()->name,
                ],
                'created_at' => now()->toIso8601String(),
            ];

            File::put($childPath.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Create empty config directory
            File::makeDirectory($childPath.'/config', 0755, true);

            // Create options.json with empty settings (will inherit from parent)
            File::put($childPath.'/config/options.json', json_encode(new stdClass, JSON_PRETTY_PRINT));

            $childTheme = Theme::getThemeInfo($childSlug);
            $this->logThemeActivity(
                ActivityAction::CREATE,
                sprintf("Child theme '%s' created from parent '%s'.", $childName, $parentTheme['name']),
                $childSlug,
                $childTheme
            );

            // Invalidate asset cache when child theme is created
            ThemeAssetResolver::invalidate();

            return to_route('cms.appearance.themes.index')
                ->with('success', sprintf("Child theme '%s' created successfully!", $childName));
        } catch (Exception $exception) {
            // Clean up on failure
            if (File::exists($childPath)) {
                File::deleteDirectory($childPath);
            }

            return back()->with('error', 'Failed to create child theme: '.$exception->getMessage())->withInput();
        }
    }

    /**
     * Detach a child theme from its parent (make it standalone)
     */
    public function detach(string $directory)
    {
        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return back()->with('error', 'Theme not found.');
        }

        if (! Theme::isChildTheme($directory)) {
            return back()->with('error', 'This theme is not a child theme.');
        }

        $parentDirectory = Theme::getParentTheme($directory);
        $parentTheme = Theme::getThemeInfo($parentDirectory);

        if (! $parentTheme) {
            return back()->with('error', 'Parent theme not found. Cannot detach.');
        }

        try {
            // Copy all parent files to child theme (excluding config)
            $this->copyParentFilesToChild($parentTheme['path'], $theme['path']);

            // Copy parent config files (excluding options.json)
            $this->copyParentConfigToChild($parentTheme['path'], $theme['path']);

            // Persist merged config so detached theme keeps full customizer/CSS settings
            $configService = new ThemeConfigService;
            $mergedConfig = $configService->loadThemeConfig($directory);
            $configDir = $theme['path'].'/config';
            if (! File::exists($configDir)) {
                File::makeDirectory($configDir, 0755, true);
            }

            File::put(
                $configDir.'/config.json',
                json_encode($mergedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );

            // Update manifest.json to remove parent reference
            $manifestPath = $theme['path'].'/manifest.json';
            $manifest = json_decode(File::get($manifestPath), true);
            unset($manifest['parent']);
            $manifest['detached_from'] = $parentDirectory;
            $manifest['detached_at'] = now()->toIso8601String();

            File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->logThemeActivity(
                ActivityAction::UPDATE,
                sprintf("Theme '%s' detached from parent '%s' and is now standalone.", $theme['name'], $parentTheme['name']),
                $directory,
                $theme
            );

            return to_route('cms.appearance.themes.index')
                ->with('success', sprintf("Theme '%s' has been detached and is now a standalone theme.", $theme['name']));
        } catch (Exception $exception) {
            return back()->with('error', 'Failed to detach theme: '.$exception->getMessage());
        }
    }

    /**
     * Copy parent theme files to child theme (for detaching)
     */
    private function copyParentFilesToChild(string $parentPath, string $childPath): void
    {
        $parentFiles = File::allFiles($parentPath);

        foreach ($parentFiles as $file) {
            $relativePath = $file->getRelativePathname();

            // Skip config directory (child has its own options)
            if (Str::startsWith($relativePath, 'config/')) {
                continue;
            }

            $targetPath = $childPath.'/'.$relativePath;

            // Only copy if file doesn't exist in child
            if (! File::exists($targetPath)) {
                $targetDir = dirname($targetPath);
                if (! File::exists($targetDir)) {
                    File::makeDirectory($targetDir, 0755, true);
                }

                File::copy($file->getPathname(), $targetPath);
            }
        }
    }

    /**
     * Copy parent config files to child theme (excluding options.json)
     */
    private function copyParentConfigToChild(string $parentPath, string $childPath): void
    {
        $parentConfigPath = $parentPath.'/config';

        if (! File::isDirectory($parentConfigPath)) {
            return;
        }

        $configFiles = File::allFiles($parentConfigPath);

        foreach ($configFiles as $file) {
            $relativePath = $file->getRelativePathname();

            if ($relativePath === 'options.json') {
                continue;
            }

            $targetPath = $childPath.'/config/'.$relativePath;
            if (! File::exists($targetPath)) {
                $targetDir = dirname($targetPath);
                if (! File::exists($targetDir)) {
                    File::makeDirectory($targetDir, 0755, true);
                }

                File::copy($file->getPathname(), $targetPath);
            }
        }
    }

    /**
     * Add directory to ZIP recursively
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPath = ''): void
    {
        $files = File::allFiles($dir);

        foreach ($files as $file) {
            $relativePath = $zipPath.'/'.$file->getRelativePathname();
            $zip->addFile($file->getPathname(), $relativePath);
        }
    }

    /**
     * Find theme directory in extracted ZIP
     */
    private function findThemeDirectory(string $extractPath): ?string
    {
        // Look for manifest.json in root first
        if (File::exists($extractPath.'/manifest.json')) {
            return $extractPath;
        }

        // Look for manifest.json in subdirectories
        $directories = File::directories($extractPath);
        foreach ($directories as $dir) {
            if (File::exists($dir.'/manifest.json')) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Validate ZIP contents for security
     */
    private function validateZipContents(ZipArchive $zip): bool
    {
        $allowedExtensions = [
            'twig', 'css', 'scss', 'sass', 'less', 'js', 'mjs', 'ts',
            'json', 'txt', 'md', 'html', 'xml',
            'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp',
            'woff', 'woff2', 'ttf', 'otf', 'eot',
            'map',
        ];
        $maxFileSize = 5 * 1024 * 1024; // 5MB per file
        $maxTotalSize = 50 * 1024 * 1024; // 50MB total
        $totalSize = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileInfo = $zip->statIndex($i);
            $filename = $fileInfo['name'];
            $fileSize = $fileInfo['size'];

            // Check for directory traversal or absolute paths
            if (
                str_contains($filename, '..') ||
                str_contains($filename, '/..') ||
                str_starts_with($filename, '/') ||
                preg_match('/^[A-Za-z]:[\/\\\\]/', $filename)
            ) {
                return false;
            }

            // Check file size
            if ($fileSize > $maxFileSize) {
                return false;
            }

            $totalSize += $fileSize;
            if ($totalSize > $maxTotalSize) {
                return false;
            }

            // Skip directories
            if (Str::endsWith($filename, '/')) {
                continue;
            }

            // Check file extension
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (! in_array($extension, $allowedExtensions) && ! empty($extension)) {
                return false;
            }

            // Check for executable files
            if (in_array($extension, ['exe', 'sh', 'bat', 'cmd', 'com', 'scr', 'pif'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate extracted theme files
     */
    private function validateThemeFiles(string $themeDir): bool
    {
        // Check manifest.json exists and is valid
        $manifestPath = $themeDir.'/manifest.json';
        if (! File::exists($manifestPath)) {
            return false;
        }

        $manifestContent = File::get($manifestPath);
        json_decode($manifestContent, true);

        // No PHP file validation needed - themes use Twig templates (.twig) only
        // Twig's sandbox policy prevents PHP execution
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Sanitize theme name
     */
    private function sanitizeThemeName(string $name): string
    {
        // Remove any path traversal attempts
        $name = str_replace(['..', '/', '\\'], '', $name);

        // Use Laravel's Str::slug for consistent naming
        return Str::slug($name);
    }

    /**
     * Log theme activity without using a model subject
     * Themes are file-based and don't use database, so we log activity
     * with theme information in properties instead of as a subject model
     */
    private function logThemeActivity(ActivityAction $action, string $message, string $directory, ?array $themeInfo = null): void
    {
        $themeInfo ??= Theme::getThemeInfo($directory) ?? [];

        activity('Themes')
            ->causedBy(auth()->user())
            ->withProperties([
                'module' => $this->activityLogModule,
                'theme_directory' => $directory,
                'theme_name' => $themeInfo['name'] ?? Str::headline($directory),
                'theme_version' => $themeInfo['version'] ?? null,
                'is_active' => $themeInfo['is_active'] ?? false,
            ])
            ->event($action->value)
            ->log($message);
    }
}
