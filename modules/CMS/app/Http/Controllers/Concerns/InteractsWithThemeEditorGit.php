<?php

namespace Modules\CMS\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CMS\Models\Theme;

trait InteractsWithThemeEditorGit
{
    public function gitHistory(Request $request, string $directory, string $path): JsonResponse
    {
        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(500, $limit));

        $skip = (int) $request->query('skip', 0);
        $skip = max(0, min(50000, $skip));

        $commits = $this->themeGitService->getCommitHistoryForFile(
            $directory,
            $path,
            $limit,
            $skip,
        );
        $hasMore = count($commits) === $limit;

        return response()->json([
            'success' => true,
            'commits' => $commits,
            'skip' => $skip,
            'limit' => $limit,
            'has_more' => $hasMore,
            'next_skip' => $skip + count($commits),
        ]);
    }

    /**
     * Get git commit history for the entire theme
     */
    public function gitHistoryAll(Request $request, string $directory): JsonResponse
    {
        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min(500, $limit));

        $skip = (int) $request->query('skip', 0);
        $skip = max(0, min(50000, $skip));

        $commits = $this->themeGitService->getCommitHistory(
            $directory,
            $limit,
            $skip,
        );
        $hasMore = count($commits) === $limit;

        return response()->json([
            'success' => true,
            'commits' => $commits,
            'skip' => $skip,
            'limit' => $limit,
            'has_more' => $hasMore,
            'next_skip' => $skip + count($commits),
        ]);
    }

    /**
     * Get git working tree status for the theme.
     */
    public function gitStatus(string $directory): JsonResponse
    {
        $result = $this->themeGitService->getWorkingTreeStatus($directory);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'error' => $result['message'] ?? 'Failed to load status',
            ], 422);
        }

        return response()->json([
            'changes' => $result['changes'] ?? [],
            'has_changes' => (bool) ($result['has_changes'] ?? false),
        ]);
    }

    /**
     * Commit current working tree changes with a message.
     */
    public function gitCommit(Request $request, string $directory): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:255'],
            'paths' => ['nullable', 'array'],
            'paths.*' => ['string'],
            'mode' => ['nullable', 'string', 'in:staged,all'],
        ]);

        $mode = $data['mode'] ?? 'all';

        if ($mode === 'staged') {
            $result = $this->themeGitService->commitStagedChanges(
                $directory,
                (string) $data['message'],
            );
        } else {
            $result = $this->themeGitService->commitWorkingTree(
                $directory,
                (string) $data['message'],
                $data['paths'] ?? null,
            );
        }

        if (! ($result['success'] ?? false)) {
            return response()->json(['error' => $result['message'] ?? 'Commit failed'], 422);
        }

        return response()->json(['message' => 'Commit created']);
    }

    public function gitStage(Request $request, string $directory): JsonResponse
    {
        $data = $request->validate([
            'paths' => ['required', 'array'],
            'paths.*' => ['string'],
        ]);

        $result = $this->themeGitService->stagePaths($directory, $data['paths']);
        if (! ($result['success'] ?? false)) {
            return response()->json(['error' => $result['message'] ?? 'Stage failed'], 422);
        }

        return response()->json(['message' => 'Changes staged']);
    }

    public function gitUnstage(Request $request, string $directory): JsonResponse
    {
        $data = $request->validate([
            'paths' => ['required', 'array'],
            'paths.*' => ['string'],
        ]);

        $result = $this->themeGitService->unstagePaths(
            $directory,
            $data['paths'],
        );
        if (! ($result['success'] ?? false)) {
            return response()->json(['error' => $result['message'] ?? 'Unstage failed'], 422);
        }

        return response()->json(['message' => 'Changes unstaged']);
    }

    public function gitDiscard(Request $request, string $directory): JsonResponse
    {
        $data = $request->validate([
            'paths' => ['required', 'array'],
            'paths.*' => ['string'],
        ]);

        $result = $this->themeGitService->discardPaths($directory, $data['paths']);
        if (! ($result['success'] ?? false)) {
            return response()->json(['error' => $result['message'] ?? 'Discard failed'], 422);
        }

        return response()->json(['message' => 'Changes discarded']);
    }

    /**
     * Get file content for a given commit (used by diff/preview)
     */
    public function gitFileAtCommit(
        string $directory,
        string $commitHash,
        string $path,
    ): JsonResponse {
        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        if (! $this->isAllowedExtension($path)) {
            return response()->json([
                'error' => 'File type not allowed for editing',
            ], 403);
        }

        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $content = $this->themeGitService->getFileContentAtCommit(
            $directory,
            $commitHash,
            $path,
        );
        if ($content === null) {
            return response()->json(['error' => 'Commit or file not found'], 404);
        }

        return response()->json([
            'success' => true,
            'content' => $content,
        ]);
    }

    /**
     * Diff preview between current content and a commit
     */
    public function gitDiff(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'commit_hash' => ['required', 'string'],
            'current_content' => ['nullable', 'string'],
        ]);

        $path = (string) $request->input('path');
        $commitHash = (string) $request->input('commit_hash');

        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        if (! $this->isAllowedExtension($path)) {
            return response()->json([
                'error' => 'File type not allowed for editing',
            ], 403);
        }

        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $result = $this->themeGitService->diffPreviewAgainstCommit(
            $directory,
            $commitHash,
            $path,
            $request->input('current_content'),
        );

        return response()->json($result);
    }

    public function gitWorkingDiff(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'mode' => ['required', 'string', 'in:staged,unstaged'],
        ]);

        $path = (string) $request->input('path');
        $mode = (string) $request->input('mode');

        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        if (! $this->isAllowedExtension($path)) {
            return response()->json([
                'error' => 'File type not allowed for editing',
            ], 403);
        }

        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $result = $this->themeGitService->workingTreeDiff($directory, $path, $mode);

        return response()->json($result);
    }

    /**
     * Restore a file from a commit and create a new commit recording the restoration.
     */
    public function gitRestore(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'path' => ['required', 'string'],
            'commit_hash' => ['required', 'string'],
        ]);

        $path = (string) $request->input('path');
        $commitHash = (string) $request->input('commit_hash');

        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        if (! $this->isAllowedExtension($path)) {
            return response()->json([
                'error' => 'File type not allowed for editing',
            ], 403);
        }

        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $result = $this->themeGitService->restoreFileFromCommit(
            $directory,
            $commitHash,
            $path,
        );

        if (($result['success'] ?? false) === true) {
            $this->logThemeActivity(
                sprintf("File '%s' restored from git commit", $path),
                $directory,
                ['file_path' => $path, 'commit_hash' => $commitHash],
            );

            $this->invalidateFrontendCache();
        }

        return response()->json($result, $result['success'] ?? false ? 200 : 422);
    }

    /**
     * List files changed in a commit (for multi-file restore UI)
     */
    public function gitCommitFiles(string $directory, string $commitHash): JsonResponse
    {
        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $files = $this->themeGitService->getFilesChangedInCommit(
            $directory,
            $commitHash,
        );

        $filtered = [];
        foreach ($files as $entry) {
            $path = (string) ($entry['path'] ?? '');
            if ($path === '') {
                continue;
            }

            if (! $this->validateFilePath($path)) {
                continue;
            }

            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
                continue;
            }

            $filtered[] = $entry;
        }

        return response()->json([
            'success' => true,
            'files' => $filtered,
        ]);
    }

    /**
     * Diff a file between a commit and its parent (used by history diff viewer)
     */
    public function gitCommitFileDiff(
        Request $request,
        string $directory,
        string $commitHash,
    ): JsonResponse {
        $request->validate([
            'path' => ['required', 'string'],
        ]);

        $path = (string) $request->input('path');

        if (! $this->validateFilePath($path)) {
            return response()->json(['error' => 'Invalid file path'], 403);
        }

        if (! $this->isAllowedExtension($path)) {
            return response()->json([
                'error' => 'File type not allowed for editing',
            ], 403);
        }

        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        $result = $this->themeGitService->diffFileAtCommit(
            $directory,
            $commitHash,
            $path,
        );

        return response()->json($result, $result['success'] ?? false ? 200 : 422);
    }

    /**
     * Restore multiple files from a commit and create a new commit recording the restoration.
     */
    public function gitRestoreCommit(Request $request, string $directory): JsonResponse
    {
        $request->validate([
            'commit_hash' => ['required', 'string'],
            'paths' => ['nullable', 'array'],
            'paths.*' => ['string'],
        ]);

        $commitHash = (string) $request->input('commit_hash');
        $paths = $request->input('paths');

        $theme = Theme::getThemeInfo($directory);
        if (! $theme) {
            return response()->json(['error' => 'Theme not found'], 404);
        }

        if (is_array($paths)) {
            $paths = array_slice($paths, 0, 500);

            foreach ($paths as $path) {
                $path = (string) $path;

                if (! $this->validateFilePath($path)) {
                    return response()->json(['error' => 'Invalid file path'], 403);
                }

                if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
                    return response()->json(['error' => 'File type not allowed'], 403);
                }
            }
        }

        $result = $this->themeGitService->restoreFilesFromCommit(
            $directory,
            $commitHash,
            is_array($paths) ? $paths : null,
        );

        if (($result['success'] ?? false) === true) {
            $this->logThemeActivity(
                'Multiple files restored from git commit',
                $directory,
                ['commit_hash' => $commitHash, 'paths' => $paths],
            );

            $this->invalidateFrontendCache();
        }

        return response()->json($result, $result['success'] ?? false ? 200 : 422);
    }
}
