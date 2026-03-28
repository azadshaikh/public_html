<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Http\Controllers\Concerns\InteractsWithThemeEditorFileTree;
use Modules\CMS\Http\Controllers\Concerns\InteractsWithThemeEditorGit;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\ThemeAssetResolver;
use Modules\CMS\Services\ThemeGitService;
use Modules\CMS\Services\TwigService;

class ThemeEditorController extends Controller implements HasMiddleware
{
    use ActivityTrait;
    use InteractsWithThemeEditorFileTree;
    use InteractsWithThemeEditorGit;

    /**
     * Allowed file extensions for editing
     */
    private const array ALLOWED_EXTENSIONS = ['twig', 'css', 'js', 'json', 'txt', 'md', 'html', 'xml', 'svg', 'scss', 'sass'];

    /**
     * File extensions that can be created
     */
    private const array CREATABLE_EXTENSIONS = ['twig', 'css', 'js', 'json', 'txt', 'md', 'html', 'xml', 'scss'];

    /**
     * Explicitly blocked file extensions for security
     */
    private const array BLOCKED_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar'];

    /**
     * Allowed file extensions for uploads
     */
    private const array UPLOAD_ALLOWED_EXTENSIONS = [
        'twig', 'css', 'scss', 'sass', 'less', 'js', 'mjs', 'ts',
        'json', 'txt', 'md', 'html', 'xml',
        'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf', 'eot',
        'mp4', 'webm', 'ogg', 'mp3', 'wav',
        'map',
    ];

    /**
     * Protected files that cannot be deleted
     */
    private const array PROTECTED_FILES = ['manifest.json', 'layouts/layout.twig'];

    protected string $activityLogModule = 'Theme Editor';

    protected string $activityEntityAttribute = 'name';

    public function __construct(
        protected ThemeGitService $themeGitService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_themes', only: [
                'index',
                'files',
                'search',
                'read',
                'gitHistory',
                'gitHistoryAll',
                'gitStatus',
                'gitFileAtCommit',
                'gitDiff',
                'gitWorkingDiff',
                'gitCommitFiles',
                'gitCommitFileDiff',
            ]),
            new Middleware('permission:edit_themes', only: [
                'save',
                'create',
                'upload',
                'rename',
                'duplicate',
                'createFolder',
                'gitCommit',
                'gitStage',
                'gitUnstage',
                'gitRestore',
                'gitRestoreCommit',
            ]),
            new Middleware('permission:delete_themes', only: [
                'delete',
                'deleteFolder',
                'gitDiscard',
            ]),
        ];
    }

    /**
     * Display the IDE-style theme editor
     */
    public function index(string $directory): Response|RedirectResponse
    {
        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return to_route('cms.appearance.themes.index')
                ->with('error', 'Theme not found.');
        }

        $files = $this->getFileTree($directory);

        // Get parent theme info if this is a child theme
        $parentTheme = null;
        $isChildTheme = Theme::isChildTheme($directory);
        if ($isChildTheme) {
            $parentDirectory = Theme::getParentTheme($directory);
            $parentTheme = $parentDirectory ? Theme::getThemeInfo($parentDirectory) : null;
        }

        return Inertia::render('cms/themes/editor/index', [
            'theme' => $this->mapThemeSummary($theme),
            'themeDirectory' => $directory,
            'files' => $files,
            'isChildTheme' => $isChildTheme,
            'parentTheme' => $parentTheme ? $this->mapThemeSummary($parentTheme) : null,
        ]);
    }

    /**
     * Get file tree structure for a theme
     */
    public function files(string $directory): JsonResponse
    {
        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $files = $this->getFileTree($directory);

        // Get parent theme info if this is a child theme
        $parentTheme = null;
        $isChildTheme = Theme::isChildTheme($directory);
        if ($isChildTheme) {
            $parentDirectory = Theme::getParentTheme($directory);
            $parentTheme = $parentDirectory ? Theme::getThemeInfo($parentDirectory) : null;
        }

        return response()->json([
            'files' => $files,
            'isChildTheme' => $isChildTheme,
            'parentTheme' => $parentTheme,
        ]);
    }

    public function search(Request $request, string $directory): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:200'],
            'case_sensitive' => ['nullable', 'boolean'],
            'use_regex' => ['nullable', 'boolean'],
            'max_results' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $query = (string) $data['query'];
        $caseSensitive = (bool) ($data['case_sensitive'] ?? false);
        $useRegex = (bool) ($data['use_regex'] ?? false);
        $maxResults = (int) ($data['max_results'] ?? 200);
        $maxResults = max(1, min(500, $maxResults));

        $regex = null;
        if ($useRegex) {
            $flags = $caseSensitive ? '' : 'i';
            $regex = '/'.$query.'/'.$flags;
            if (@preg_match($regex, '') === false) {
                return response()->json(['error' => 'Invalid regex'], 422);
            }
        }

        $themePath = $theme['path'];
        $results = [];
        $totalMatches = 0;

        foreach (File::allFiles($themePath) as $file) {
            if ($totalMatches >= $maxResults) {
                break;
            }

            $fullPath = $file->getPathname();
            $relativePath = Str::after($fullPath, $themePath.'/');

            if (! $this->isAllowedExtension($relativePath)) {
                continue;
            }

            if (str_contains($relativePath, '/.git/')) {
                continue;
            }

            if (str_contains($relativePath, '/node_modules/')) {
                continue;
            }

            if ($file->getSize() > 1024 * 1024) {
                continue;
            }

            $content = File::get($fullPath);
            $lines = preg_split('/\r?\n/', $content) ?: [];
            $matches = [];

            foreach ($lines as $index => $line) {
                if ($totalMatches >= $maxResults) {
                    break;
                }

                $lineNumber = $index + 1;
                $column = null;

                if ($useRegex && $regex) {
                    if (preg_match($regex, $line, $match, PREG_OFFSET_CAPTURE) === 1) {
                        $column = $match[0][1] + 1;
                    } else {
                        continue;
                    }
                } else {
                    $haystack = $caseSensitive ? $line : Str::lower($line);
                    $needle = $caseSensitive ? $query : Str::lower($query);
                    $pos = strpos($haystack, $needle);
                    if ($pos === false) {
                        continue;
                    }

                    $column = $pos + 1;
                }

                $matches[] = [
                    'path' => $relativePath,
                    'line' => $lineNumber,
                    'column' => $column,
                    'text' => trim($line),
                ];

                $totalMatches++;
            }

            if ($matches !== []) {
                $results[] = [
                    'path' => $relativePath,
                    'match_count' => count($matches),
                    'matches' => $matches,
                ];
            }
        }

        return response()->json([
            'results' => $results,
            'total_matches' => $totalMatches,
        ]);
    }

    /**
     * Read a file's content
     */
    public function read(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $path = (string) $request->input('path');

        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        if (! $this->isAllowedExtension($path)) {
            return response()->json(['error' => 'File type not allowed for editing'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $fullPath = $theme['path'].'/'.$path;
        $inherited = false;
        $inheritedFrom = null;

        // Check if file exists in child theme, if not check parent themes
        if (! File::exists($fullPath) && Theme::isChildTheme($directory)) {
            // Try to find file in parent theme hierarchy
            $hierarchy = Theme::getThemeHierarchy($directory);
            array_shift($hierarchy); // Remove current theme from hierarchy

            foreach ($hierarchy as $parentDirectory) {
                $parentTheme = Theme::getThemeInfo($parentDirectory);
                if ($parentTheme) {
                    $parentPath = $parentTheme['path'].'/'.$path;
                    if (File::exists($parentPath)) {
                        $fullPath = $parentPath;
                        $inherited = true;
                        $inheritedFrom = $parentDirectory;
                        break;
                    }
                }
            }
        }

        if (! File::exists($fullPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $content = File::get($fullPath);

        return response()->json([
            'content' => $content,
            'path' => $path,
            'size' => File::size($fullPath),
            'modified' => File::lastModified($fullPath),
            'language' => $this->getEditorLanguage($path),
            'inherited' => $inherited,
            'inheritedFrom' => $inheritedFrom,
        ]);
    }

    /**
     * Save a file's content
     */
    public function save(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $path = (string) $request->input('path');

        if (! $this->validateFilePath($path)) {
            Log::warning('SECURITY: Blocked theme editor save with invalid path', [
                'theme' => $directory,
                'file' => $path,
                'user' => auth()->guard()->id(),
            ]);

            return response()->json(['error' => 'Invalid file path'], 403);
        }

        if (! $this->isAllowedExtension($path)) {
            return response()->json(['error' => 'File type not allowed for editing'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $fullPath = $theme['path'].'/'.$path;

        try {
            // Ensure directory exists
            $dir = dirname($fullPath);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            // Save the new content
            File::put($fullPath, $request->content);

            // Invalidate asset cache if this is an asset file
            if (str_starts_with($path, 'assets/')) {
                ThemeAssetResolver::invalidate();
            }

            // Clear Twig cache for .twig files to ensure changes are reflected immediately
            if (str_ends_with($path, '.twig')) {
                try {
                    $twigService = resolve(TwigService::class);
                    $twigService->clearCache();
                } catch (Exception $e) {
                    Log::warning('Failed to clear Twig cache after file save', [
                        'theme' => $directory,
                        'file' => $path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logThemeActivity(
                sprintf("File '%s' saved in theme '%s'", $path, $theme['name']),
                $directory,
                ['file_path' => $path]
            );

            // Invalidate frontend caches when theme files change
            $this->invalidateFrontendCache();

            return response()->json([
                'success' => true,
                'message' => 'File saved successfully',
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to save theme file', [
                'theme' => $directory,
                'file' => $path,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to save file: '.$exception->getMessage()], 500);
        }
    }

    /**
     * Create a new file
     */
    public function create(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
        ]);

        $path = $request->path;

        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // Block PHP extensions explicitly
        if (in_array($extension, self::BLOCKED_EXTENSIONS)) {
            Log::warning('SECURITY: Blocked theme editor PHP file creation', [
                'theme' => $directory,
                'path' => $path,
                'user' => auth()->guard()->id(),
            ]);

            return response()->json([
                'error' => 'Cannot create PHP files for security reasons',
            ], 403);
        }

        if (! in_array($extension, self::CREATABLE_EXTENSIONS)) {
            return response()->json(['error' => 'File type not allowed'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $fullPath = $theme['path'].'/'.$path;

        if (File::exists($fullPath)) {
            return response()->json(['error' => 'File already exists'], 409);
        }

        try {
            // Ensure directory exists
            $dir = dirname($fullPath);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $content = $request->content ?? $this->getDefaultContent($path);
            File::put($fullPath, $content);

            // Invalidate asset cache if this is an asset file
            if (str_starts_with($path, 'assets/')) {
                ThemeAssetResolver::invalidate();
            }

            $this->logThemeActivity(
                sprintf("File '%s' created in theme '%s'", $path, $theme['name']),
                $directory,
                ['file_path' => $path]
            );

            // Invalidate frontend caches when theme files change
            $this->invalidateFrontendCache();

            return response()->json([
                'success' => true,
                'message' => 'File created successfully',
                'path' => $path,
                'content' => $content,
                'language' => $this->getEditorLanguage($path),
            ]);
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to create file: '.$exception->getMessage()], 500);
        }
    }

    /**
     * Upload a file to the theme
     */
    public function upload(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
            'path' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $filename = $this->sanitizeFilename($originalName);

        if ($filename === '' || $filename !== $originalName) {
            return response()->json(['error' => 'Invalid file name'], 422);
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Block PHP extensions
        if (in_array($extension, self::BLOCKED_EXTENSIONS)) {
            Log::warning('SECURITY: Blocked theme editor PHP file upload', [
                'theme' => $directory,
                'filename' => $originalName,
                'user' => auth()->guard()->id(),
            ]);

            return response()->json([
                'error' => 'Cannot upload PHP files for security reasons',
            ], 403);
        }

        if (! in_array($extension, self::UPLOAD_ALLOWED_EXTENSIONS)) {
            return response()->json(['error' => 'File type not allowed'], 403);
        }

        // Validate target path
        $targetPath = $request->path ?? '';
        if ($targetPath && ! $this->validateFilePath($targetPath)) {
            return response()->json(['error' => 'Invalid target path'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $relativePath = $targetPath ? $targetPath.'/'.$filename : $filename;
        if (! $this->validateFilePath($relativePath)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        $fullPath = $theme['path'].'/'.$relativePath;

        // Prevent overwriting without explicit flag
        if (File::exists($fullPath) && ! $request->boolean('overwrite')) {
            return response()->json(['error' => 'File already exists. Enable overwrite to replace.'], 409);
        }

        try {
            // Ensure directory exists
            $dir = dirname($fullPath);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $file->move($dir, $filename);

            // Invalidate asset cache if this is an asset file
            if (str_starts_with($relativePath, 'assets/')) {
                ThemeAssetResolver::invalidate();
            }

            $this->logThemeActivity(
                sprintf("File '%s' uploaded to theme '%s'", $relativePath, $theme['name']),
                $directory,
                ['file_path' => $relativePath]
            );

            // Invalidate frontend caches when theme files change
            $this->invalidateFrontendCache();

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'path' => $relativePath,
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to upload theme file', [
                'theme' => $directory,
                'filename' => $filename,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to upload file: '.$exception->getMessage()], 500);
        }
    }

    /**
     * Delete a file
     */
    public function delete(Request $request, string $directory, string $path): JsonResponse
    {
        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        if (in_array($path, self::PROTECTED_FILES)) {
            return response()->json(['error' => 'This file is protected and cannot be deleted'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $fullPath = $theme['path'].'/'.$path;

        if (! File::exists($fullPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            File::delete($fullPath);

            // Invalidate asset cache if this is an asset file
            if (str_starts_with($path, 'assets/')) {
                ThemeAssetResolver::invalidate();
            }

            $this->logThemeActivity(
                sprintf("File '%s' deleted from theme '%s'", $path, $theme['name']),
                $directory,
                ['file_path' => $path]
            );

            // Invalidate frontend caches when theme files change
            $this->invalidateFrontendCache();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to delete file: '.$exception->getMessage()], 500);
        }
    }

    // Legacy theme file revision endpoints were removed.

    /**
     * Rename/move a file
     */
    public function rename(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'old_path' => ['required', 'string', 'max:500'],
            'new_path' => ['required', 'string', 'max:500'],
        ]);

        $oldPath = $request->old_path;
        $newPath = $request->new_path;

        if (! $this->validateFilePath($oldPath) || ! $this->validateFilePath($newPath)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        // Prevent renaming to blocked extensions
        $newExtension = strtolower(pathinfo($newPath, PATHINFO_EXTENSION));
        if (in_array($newExtension, self::BLOCKED_EXTENSIONS)) {
            Log::warning('SECURITY: Blocked theme editor rename to PHP extension', [
                'theme' => $directory,
                'old_path' => $oldPath,
                'new_path' => $newPath,
                'user' => auth()->guard()->id(),
            ]);

            return response()->json([
                'error' => 'Cannot rename to PHP files for security reasons',
            ], 403);
        }

        if (in_array($oldPath, self::PROTECTED_FILES)) {
            return response()->json(['error' => 'This file is protected and cannot be renamed'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $fullOldPath = $theme['path'].'/'.$oldPath;
        $fullNewPath = $theme['path'].'/'.$newPath;

        if (! File::exists($fullOldPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if (File::exists($fullNewPath)) {
            return response()->json(['error' => 'A file with the new name already exists'], 409);
        }

        try {
            // Ensure target directory exists
            $dir = dirname($fullNewPath);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            File::move($fullOldPath, $fullNewPath);

            // Invalidate asset cache if either path is an asset file
            if (str_starts_with($oldPath, 'assets/') || str_starts_with($newPath, 'assets/')) {
                ThemeAssetResolver::invalidate();
            }

            $this->logThemeActivity(
                sprintf("File renamed from '%s' to '%s' in theme '%s'", $oldPath, $newPath, $theme['name']),
                $directory,
                ['old_path' => $oldPath, 'new_path' => $newPath]
            );

            // Invalidate frontend caches when theme files change
            $this->invalidateFrontendCache();

            return response()->json([
                'success' => true,
                'message' => 'File renamed successfully',
                'new_path' => $newPath,
            ]);
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to rename file: '.$exception->getMessage()], 500);
        }
    }

    /**
     * Duplicate a file with -copy, -copy-2, etc. naming convention
     */
    public function duplicate(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $path = $request->path;

        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $fullPath = $theme['path'].'/'.$path;
        // Check if file exists in child theme, if not check parent themes
        if (! File::exists($fullPath) && Theme::isChildTheme($directory)) {
            // Try to find file in parent theme hierarchy
            $hierarchy = Theme::getThemeHierarchy($directory);
            array_shift($hierarchy); // Remove current theme from hierarchy

            foreach ($hierarchy as $parentDirectory) {
                $parentTheme = Theme::getThemeInfo($parentDirectory);
                if ($parentTheme) {
                    $parentPath = $parentTheme['path'].'/'.$path;
                    if (File::exists($parentPath)) {
                        $fullPath = $parentPath;
                        break;
                    }
                }
            }
        }

        if (! File::exists($fullPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        try {
            // Generate new filename with -copy, -copy-2, etc.
            $pathInfo = pathinfo($path);
            $baseName = $pathInfo['filename'];
            $extension = isset($pathInfo['extension']) ? '.'.$pathInfo['extension'] : '';
            $dirPath = isset($pathInfo['dirname']) && $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'].'/' : '';

            // Check for existing -copy suffix and increment
            if (preg_match('/^(.+)-copy(-(\d+))?$/', $baseName, $matches)) {
                $baseName = $matches[1];
            }

            // Find next available copy number
            $newPath = $dirPath.$baseName.'-copy'.$extension;
            $counter = 1;

            while (File::exists($theme['path'].'/'.$newPath)) {
                $counter++;
                $newPath = $dirPath.$baseName.'-copy-'.$counter.$extension;
            }

            $destinationPath = $theme['path'].'/'.$newPath;

            // Ensure destination directory exists
            $destinationDir = dirname($destinationPath);
            if (! File::isDirectory($destinationDir)) {
                File::makeDirectory($destinationDir, 0755, true);
            }

            // Copy the file
            File::copy($fullPath, $destinationPath);

            // Invalidate asset cache if this is an asset file
            if (str_starts_with($path, 'assets/')) {
                ThemeAssetResolver::invalidate();
            }

            $this->logThemeActivity(
                sprintf("File '%s' duplicated as '%s' in theme '%s'", $path, $newPath, $theme['name']),
                $directory,
                ['source_path' => $path, 'new_path' => $newPath]
            );

            // Invalidate frontend caches when theme files change
            $this->invalidateFrontendCache();

            return response()->json([
                'success' => true,
                'message' => 'File duplicated successfully',
                'new_path' => $newPath,
            ]);
        } catch (Exception $exception) {
            Log::error('Failed to duplicate theme file', [
                'theme' => $directory,
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to duplicate file. Please try again.'], 500);
        }
    }

    /**
     * Create a new folder
     */
    public function createFolder(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $path = $request->path;

        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid folder path'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $fullPath = $theme['path'].'/'.$path;

        if (File::isDirectory($fullPath)) {
            return response()->json(['error' => 'Folder already exists'], 409);
        }

        try {
            File::makeDirectory($fullPath, 0755, true);

            return response()->json([
                'success' => true,
                'message' => 'Folder created successfully',
                'path' => $path,
            ]);
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to create folder: '.$exception->getMessage()], 500);
        }
    }

    /**
     * Delete a folder
     */
    public function deleteFolder(Request $request, string $directory, string $path): JsonResponse
    {
        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid folder path'], 403);
        }

        // Protect root-level important folders
        $protectedFolders = ['layouts', 'templates', 'config'];
        if (in_array($path, $protectedFolders)) {
            return response()->json(['error' => 'This folder is protected and cannot be deleted'], 403);
        }

        $theme = Theme::getThemeInfo($directory);

        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $fullPath = $theme['path'].'/'.$path;

        if (! File::isDirectory($fullPath)) {
            return response()->json(['error' => 'Folder not found'], 404);
        }

        try {
            File::deleteDirectory($fullPath);

            // Invalidate asset cache if this is an assets folder
            if (str_starts_with($path, 'assets/') || $path === 'assets') {
                ThemeAssetResolver::invalidate();
            }

            $this->logThemeActivity(
                sprintf("Folder '%s' deleted from theme '%s'", $path, $theme['name']),
                $directory,
                ['folder_path' => $path]
            );

            // Invalidate frontend caches when theme files change
            $this->invalidateFrontendCache();

            return response()->json([
                'success' => true,
                'message' => 'Folder deleted successfully',
            ]);
        } catch (Exception $exception) {
            return response()->json(['error' => 'Failed to delete folder: '.$exception->getMessage()], 500);
        }
    }

    /**
     * Get git commit history for a specific file
     */
}
