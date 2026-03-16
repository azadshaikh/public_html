<?php

namespace Modules\CMS\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\Git\GitCommandService;
use RuntimeException;
use Throwable;

class ThemeGitService
{
    private const int BATCH_WINDOW_SECONDS = 300;

    public function __construct(
        protected GitCommandService $git,
    ) {}

    /**
     * Called after a theme file is saved via ThemeEditor.
     */
    public function onFileSaved(string $themeDirectory, string $filePath): void
    {
        $userId = (int) (auth()->guard()->id() ?? 0);

        $lockKey = $this->lockKey($themeDirectory, $userId);
        $lock = Cache::lock($lockKey, 15);

        try {
            $lock->block(5);

            $init = $this->ensureInitialized($themeDirectory);
            if (! $init) {
                return;
            }

            $this->configureIdentityFromAuth($themeDirectory);

            $batchKey = $this->batchKey($themeDirectory, $userId);
            $batch = Cache::get($batchKey);

            $now = now();

            if (! is_array($batch)) {
                $batch = [
                    'started_at' => $now->toIso8601String(),
                    'files' => [],
                    'has_committed' => false,
                ];
            }

            $startedAt = isset($batch['started_at']) ? now()->parse($batch['started_at']) : $now;
            $withinWindow = $now->diffInSeconds($startedAt) <= self::BATCH_WINDOW_SECONDS;

            if (! $withinWindow) {
                $batch = [
                    'started_at' => $now->toIso8601String(),
                    'files' => [],
                    'has_committed' => false,
                ];
            }

            $batch['files'] = array_values(array_unique(array_merge((array) ($batch['files'] ?? []), [$filePath])));

            $add = $this->git->executeAdd($themeDirectory, [$filePath]);
            if (! $add->success) {
                Log::warning('Theme git add failed', ['theme' => $themeDirectory, 'file' => $filePath, 'error' => $add->error]);

                return;
            }

            $message = $this->buildAutoCommitMessage($batch['files']);
            $shouldAmend = $withinWindow && ((bool) ($batch['has_committed'] ?? false));

            $commit = $this->git->executeCommit($themeDirectory, $message, [
                'amend' => $shouldAmend,
            ]);

            if (! $commit->success) {
                Log::warning('Theme git commit failed', ['theme' => $themeDirectory, 'error' => $commit->error]);

                return;
            }

            $batch['has_committed'] = true;
            Cache::put($batchKey, $batch, now()->addSeconds(self::BATCH_WINDOW_SECONDS + 60));
        } catch (Throwable $throwable) {
            Log::warning('Theme git batching failed', ['theme' => $themeDirectory, 'error' => $throwable->getMessage()]);
        } finally {
            $lock->release();
        }
    }

    public function ensureInitialized(string $themeDirectory): bool
    {
        $themeInfo = Theme::getThemeInfo($themeDirectory);
        $themePath = $themeInfo['path'] ?? null;

        if (! $themePath || ! File::isDirectory($themePath)) {
            return false;
        }

        if ($this->git->repositoryExists($themeDirectory)) {
            return true;
        }

        $init = $this->git->executeInit($themeDirectory);
        if (! $init->success) {
            Log::warning('Theme git init failed', ['theme' => $themeDirectory, 'error' => $init->error]);

            return false;
        }

        if (! File::exists($themePath.'/.gitignore')) {
            File::put($themePath.'/.gitignore', $this->defaultGitignore());
        }

        $this->configureIdentityFromAuth($themeDirectory);

        $add = $this->git->executeAdd($themeDirectory);
        if (! $add->success) {
            Log::warning('Theme git add failed during init', ['theme' => $themeDirectory, 'error' => $add->error]);

            return false;
        }

        $commit = $this->git->executeCommit($themeDirectory, 'Initial theme setup', ['allow_empty' => true]);
        if (! $commit->success) {
            Log::warning('Theme git initial commit failed', ['theme' => $themeDirectory, 'error' => $commit->error]);

            return false;
        }

        return true;
    }

    public function getCommitHistoryForFile(string $themeDirectory, string $filePath, int $limit = 50, int $skip = 0): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return [];
        }

        $log = $this->git->executeLog($themeDirectory, [
            'limit' => $limit,
            'skip' => $skip,
            'file' => $filePath,
        ]);

        if (! $log->success) {
            return [];
        }

        return (array) ($log->data['commits'] ?? []);
    }

    public function getCommitHistory(string $themeDirectory, int $limit = 50, int $skip = 0): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return [];
        }

        $log = $this->git->executeLog($themeDirectory, [
            'limit' => $limit,
            'skip' => $skip,
        ]);

        if (! $log->success) {
            return [];
        }

        return (array) ($log->data['commits'] ?? []);
    }

    public function getCommitParentHash(string $themeDirectory, string $commitHash): ?string
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return null;
        }

        $parent = $this->git->executeCommitParentHash($themeDirectory, $commitHash);
        if (! $parent->success) {
            return null;
        }

        $hash = $parent->data['parent'] ?? null;
        if (! is_string($hash) || $hash === '') {
            return null;
        }

        return $hash;
    }

    public function diffFileAtCommit(string $themeDirectory, string $commitHash, string $filePath): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized'];
        }

        $parentHash = $this->getCommitParentHash($themeDirectory, $commitHash);

        $newContent = $this->getFileContentAtCommit($themeDirectory, $commitHash, $filePath);
        if ($newContent === null) {
            return ['success' => false, 'message' => 'Commit content not found'];
        }

        $oldContent = '';
        if ($parentHash) {
            $oldContent = $this->getFileContentAtCommit($themeDirectory, $parentHash, $filePath) ?? '';
        }

        return [
            'success' => true,
            'old_content' => $oldContent,
            'new_content' => $newContent,
        ];
    }

    public function getFileContentAtCommit(string $themeDirectory, string $commitHash, string $filePath): ?string
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return null;
        }

        $show = $this->git->executeShowFile($themeDirectory, $commitHash, $filePath);
        if (! $show->success) {
            return null;
        }

        return $show->output;
    }

    public function diffPreviewAgainstCommit(string $themeDirectory, string $commitHash, string $filePath, ?string $currentContent = null): array
    {
        $old = $this->getFileContentAtCommit($themeDirectory, $commitHash, $filePath);
        if ($old === null) {
            return ['success' => false, 'message' => 'Commit content not found'];
        }

        if ($currentContent === null) {
            $themeInfo = Theme::getThemeInfo($themeDirectory);
            $themePath = $themeInfo['path'] ?? null;
            $diskPath = $themePath ? $themePath.'/'.$filePath : null;
            $currentContent = $diskPath && File::exists($diskPath) ? File::get($diskPath) : '';
        }

        return [
            'success' => true,
            'old_content' => $old,
            'new_content' => $currentContent,
        ];
    }

    public function workingTreeDiff(string $themeDirectory, string $filePath, string $mode): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized'];
        }

        $themeInfo = Theme::getThemeInfo($themeDirectory);
        $themePath = $themeInfo['path'] ?? null;
        if (! $themePath) {
            return ['success' => false, 'message' => 'Theme not found'];
        }

        $diskPath = $themePath.'/'.$filePath;
        $workingContent = File::exists($diskPath) ? File::get($diskPath) : '';

        $headContent = $this->getFileContentAtCommit($themeDirectory, 'HEAD', $filePath) ?? '';

        $indexContent = '';
        $indexResult = $this->git->executeShowIndexFile($themeDirectory, $filePath);
        if ($indexResult->success) {
            $indexContent = $indexResult->output;
        }

        if ($mode === 'staged') {
            return [
                'success' => true,
                'old_content' => $headContent,
                'new_content' => $indexContent,
            ];
        }

        return [
            'success' => true,
            'old_content' => $indexContent !== '' ? $indexContent : $headContent,
            'new_content' => $workingContent,
        ];
    }

    public function restoreFileFromCommit(string $themeDirectory, string $commitHash, string $filePath): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized'];
        }

        $this->configureIdentityFromAuth($themeDirectory);

        $themeInfo = Theme::getThemeInfo($themeDirectory);
        $themePath = $themeInfo['path'] ?? null;
        if (! $themePath) {
            return ['success' => false, 'message' => 'Theme not found'];
        }

        $diskPath = $themePath.'/'.$filePath;
        $previousContent = File::exists($diskPath) ? File::get($diskPath) : null;

        // Read content first to avoid changing working directory on invalid commit/file.
        $targetContent = $this->getFileContentAtCommit($themeDirectory, $commitHash, $filePath);
        if ($targetContent === null) {
            return ['success' => false, 'message' => 'Invalid commit or file not found in commit'];
        }

        try {
            // Ensure directory exists
            $dir = dirname($diskPath);
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            File::put($diskPath, $targetContent);

            $add = $this->git->executeAdd($themeDirectory, [$filePath]);
            if (! $add->success) {
                throw new RuntimeException($add->error ?? 'Failed to stage restored file');
            }

            $message = 'Restore '.basename($filePath).' from commit '.$commitHash;
            $commit = $this->git->executeCommit($themeDirectory, $message);
            if (! $commit->success) {
                throw new RuntimeException($commit->error ?? 'Failed to commit restored file');
            }

            return [
                'success' => true,
                'message' => 'File restored successfully',
                'content' => $targetContent,
            ];
        } catch (Throwable $throwable) {
            // Roll back working tree best-effort.
            try {
                $this->git->executeResetFile($themeDirectory, $filePath);

                if ($previousContent === null) {
                    if (File::exists($diskPath)) {
                        File::delete($diskPath);
                    }
                } else {
                    File::put($diskPath, $previousContent);
                }
            } catch (Throwable) {
                // ignore
            }

            return ['success' => false, 'message' => 'Failed to restore file: '.$throwable->getMessage()];
        }
    }

    public function getFilesChangedInCommit(string $themeDirectory, string $commitHash): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return [];
        }

        $files = $this->git->executeCommitChangedFiles($themeDirectory, $commitHash);
        if (! $files->success) {
            return [];
        }

        return (array) ($files->data['files'] ?? []);
    }

    public function getWorkingTreeStatus(string $themeDirectory): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized', 'changes' => []];
        }

        $status = $this->git->executeStatusPorcelain($themeDirectory);
        if (! $status->success) {
            return ['success' => false, 'message' => $status->error ?? 'Failed to load status', 'changes' => []];
        }

        $changes = $this->parseStatusPorcelain($status->output);

        return [
            'success' => true,
            'changes' => $changes,
            'has_changes' => $changes !== [],
        ];
    }

    public function commitWorkingTree(string $themeDirectory, string $message, ?array $paths = null): array
    {
        return $this->commitWorkingTreeWithMode($themeDirectory, $message, $paths, false);
    }

    public function commitStagedChanges(string $themeDirectory, string $message): array
    {
        return $this->commitWorkingTreeWithMode($themeDirectory, $message, null, true);
    }

    public function stagePaths(string $themeDirectory, array $paths): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized'];
        }

        $files = array_values(array_unique(array_filter(array_map(strval(...), $paths))));
        if ($files === []) {
            return ['success' => false, 'message' => 'No files selected'];
        }

        $add = $this->git->executeAddAllForPaths($themeDirectory, $files);
        if (! $add->success) {
            return ['success' => false, 'message' => $add->error ?? 'Failed to stage changes'];
        }

        return ['success' => true, 'message' => 'Changes staged'];
    }

    public function unstagePaths(string $themeDirectory, array $paths): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized'];
        }

        $files = array_values(array_unique(array_filter(array_map(strval(...), $paths))));
        if ($files === []) {
            return ['success' => false, 'message' => 'No files selected'];
        }

        foreach ($files as $file) {
            $reset = $this->git->executeResetFile($themeDirectory, $file);
            if (! $reset->success) {
                return ['success' => false, 'message' => $reset->error ?? 'Failed to unstage changes'];
            }
        }

        return ['success' => true, 'message' => 'Changes unstaged'];
    }

    public function discardPaths(string $themeDirectory, array $paths): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized'];
        }

        $files = array_values(array_unique(array_filter(array_map(strval(...), $paths))));
        if ($files === []) {
            return ['success' => false, 'message' => 'No files selected'];
        }

        $themeInfo = Theme::getThemeInfo($themeDirectory);
        $themePath = $themeInfo['path'] ?? null;
        if (! $themePath) {
            return ['success' => false, 'message' => 'Theme not found'];
        }

        foreach ($files as $file) {
            $status = $this->git->executeStatusPorcelain($themeDirectory);
            if ($status->success && str_contains($status->output, '?? '.$file)) {
                $diskPath = $themePath.'/'.$file;
                if (File::exists($diskPath)) {
                    File::delete($diskPath);
                }

                continue;
            }

            $checkout = $this->git->executeCheckoutFileHead($themeDirectory, $file);
            if (! $checkout->success) {
                return ['success' => false, 'message' => $checkout->error ?? 'Failed to discard changes'];
            }
        }

        return ['success' => true, 'message' => 'Changes discarded'];
    }

    public function restoreFilesFromCommit(string $themeDirectory, string $commitHash, ?array $paths = null): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized'];
        }

        $this->configureIdentityFromAuth($themeDirectory);

        $themeInfo = Theme::getThemeInfo($themeDirectory);
        $themePath = $themeInfo['path'] ?? null;
        if (! $themePath) {
            return ['success' => false, 'message' => 'Theme not found'];
        }

        $status = $this->git->executeStatusPorcelain($themeDirectory);
        if ($status->success && trim($status->output) !== '') {
            return ['success' => false, 'message' => 'Working tree has uncommitted changes. Please save/commit changes before restoring.'];
        }

        $changedFiles = $this->getFilesChangedInCommit($themeDirectory, $commitHash);
        if ($changedFiles === []) {
            return ['success' => false, 'message' => 'No files found for commit (or invalid commit)'];
        }

        $selected = null;
        if (is_array($paths) && $paths !== []) {
            $selected = array_values(array_unique(array_filter(array_map(strval(...), $paths))));
        }

        $selectedLookup = $selected ? array_fill_keys($selected, true) : null;

        $touchedPaths = [];
        $restored = [];

        try {
            foreach ($changedFiles as $entry) {
                $path = (string) ($entry['path'] ?? '');
                if ($path === '') {
                    continue;
                }

                if ($selectedLookup !== null && ! isset($selectedLookup[$path])) {
                    continue;
                }

                $statusLabel = (string) ($entry['status'] ?? '');
                $kind = strtoupper(substr($statusLabel, 0, 1));

                if ($kind === 'D') {
                    $diskPath = $themePath.'/'.$path;
                    if (File::exists($diskPath)) {
                        File::delete($diskPath);
                    }

                    $touchedPaths[] = $path;
                    $restored[] = ['path' => $path, 'action' => 'deleted'];

                    continue;
                }

                $checkout = $this->git->executeCheckoutFileFromCommit($themeDirectory, $commitHash, $path);
                if (! $checkout->success) {
                    throw new RuntimeException($checkout->error ?? 'Failed to checkout file from commit');
                }

                $touchedPaths[] = $path;
                $restored[] = ['path' => $path, 'action' => 'restored'];

                if ($kind === 'R') {
                    $oldPath = (string) ($entry['old_path'] ?? '');
                    if ($oldPath !== '') {
                        $oldDiskPath = $themePath.'/'.$oldPath;
                        if (File::exists($oldDiskPath)) {
                            File::delete($oldDiskPath);
                        }

                        $touchedPaths[] = $oldPath;
                    }
                }
            }

            $touchedPaths = array_values(array_unique($touchedPaths));

            if ($touchedPaths === []) {
                return ['success' => false, 'message' => 'No files selected for restoration'];
            }

            $add = $this->git->executeAddAllForPaths($themeDirectory, $touchedPaths);
            if (! $add->success) {
                throw new RuntimeException($add->error ?? 'Failed to stage restored files');
            }

            $message = 'Restore '.count($touchedPaths).' file(s) from commit '.$commitHash;
            $commit = $this->git->executeCommit($themeDirectory, $message);
            if (! $commit->success) {
                throw new RuntimeException($commit->error ?? 'Failed to commit restored files');
            }

            return [
                'success' => true,
                'message' => 'Files restored successfully',
                'restored' => $restored,
            ];
        } catch (Throwable $throwable) {
            // Roll back working tree best-effort.
            foreach (array_values(array_unique($touchedPaths)) as $path) {
                try {
                    $this->git->executeResetFile($themeDirectory, $path);
                    $this->git->executeCheckoutFileHead($themeDirectory, $path);
                } catch (Throwable) {
                    // ignore
                }
            }

            return ['success' => false, 'message' => 'Failed to restore files: '.$throwable->getMessage()];
        }
    }

    private function configureIdentityFromAuth(string $themeDirectory): void
    {
        if (! auth()->guard()->check()) {
            return;
        }

        $user = auth()->guard()->user();
        if (! $user) {
            return;
        }

        $name = trim((string) ($user->name ?? ''));
        $email = trim((string) ($user->email ?? ''));

        if ($name === '' || $email === '') {
            return;
        }

        $this->git->configureUserIdentity($themeDirectory, $name, $email);
    }

    private function buildAutoCommitMessage(array $files): string
    {
        $files = array_values(array_unique(array_filter(array_map(strval(...), $files))));

        if (count($files) === 1) {
            return 'Update '.basename($files[0]);
        }

        $preview = array_slice($files, 0, 3);
        $label = implode(', ', array_map(basename(...), $preview));

        if (count($files) > 3) {
            $label .= '…';
        }

        return 'Update files: '.$label;
    }

    private function parseStatusPorcelain(string $output): array
    {
        $lines = preg_split('/\r?\n/', rtrim($output)) ?: [];
        $changes = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            if (strlen($line) < 3) {
                continue;
            }

            $status = substr($line, 0, 2);
            $indexStatus = $status[0] ?? ' ';
            $worktreeStatus = $status[1] ?? ' ';
            $path = trim(substr($line, 3));
            if ($path === '') {
                continue;
            }

            $oldPath = null;
            if (str_contains($path, ' -> ')) {
                [$oldPath, $path] = array_map(trim(...), explode(' -> ', $path, 2));
            }

            $label = 'Modified';
            if (str_starts_with($status, '??')) {
                $label = 'Untracked';
            } elseif (str_contains($status, 'A')) {
                $label = 'Added';
            } elseif (str_contains($status, 'D')) {
                $label = 'Deleted';
            } elseif (str_contains($status, 'R')) {
                $label = 'Renamed';
            } elseif (str_contains($status, 'M')) {
                $label = 'Modified';
            }

            $changes[] = [
                'status' => $status,
                'index_status' => $indexStatus,
                'worktree_status' => $worktreeStatus,
                'status_label' => $label,
                'path' => $path,
                'old_path' => $oldPath,
                'staged' => $indexStatus !== ' ' && $indexStatus !== '?',
                'unstaged' => $worktreeStatus !== ' ',
            ];
        }

        return $changes;
    }

    private function commitWorkingTreeWithMode(string $themeDirectory, string $message, ?array $paths, bool $stagedOnly): array
    {
        if (! $this->ensureInitialized($themeDirectory)) {
            return ['success' => false, 'message' => 'Git repository not initialized'];
        }

        $this->configureIdentityFromAuth($themeDirectory);

        $status = $this->getWorkingTreeStatus($themeDirectory);
        if (! ($status['success'] ?? false)) {
            return ['success' => false, 'message' => $status['message'] ?? 'Unable to check git status'];
        }

        if (! ($status['has_changes'] ?? false)) {
            return ['success' => false, 'message' => 'No changes to commit'];
        }

        if ($stagedOnly) {
            $hasStaged = collect($status['changes'] ?? [])->contains(fn ($c): bool => (bool) ($c['staged'] ?? false));
            if (! $hasStaged) {
                return ['success' => false, 'message' => 'No staged changes to commit'];
            }

            $commit = $this->git->executeCommit($themeDirectory, $message);
            if (! $commit->success) {
                return ['success' => false, 'message' => $commit->error ?? 'Failed to commit changes'];
            }

            return ['success' => true, 'message' => 'Changes committed'];
        }

        $files = null;
        if (is_array($paths) && $paths !== []) {
            $files = array_values(array_unique(array_filter(array_map(strval(...), $paths))));
        }

        $add = $files ? $this->git->executeAddAllForPaths($themeDirectory, $files) : $this->git->executeAdd($themeDirectory);
        if (! $add->success) {
            return ['success' => false, 'message' => $add->error ?? 'Failed to stage changes'];
        }

        $commit = $this->git->executeCommit($themeDirectory, $message);
        if (! $commit->success) {
            return ['success' => false, 'message' => $commit->error ?? 'Failed to commit changes'];
        }

        return ['success' => true, 'message' => 'Changes committed'];
    }

    private function defaultGitignore(): string
    {
        return implode("\n", [
            '# Astero theme development',
            '.DS_Store',
            'Thumbs.db',
            '',
            '# Node / build artifacts',
            'node_modules/',
            'dist/',
            'build/',
            '.vite/',
            '',
            '# IDE',
            '.idea/',
            '.vscode/',
            '',
            '# Logs',
            '*.log',
            '',
            '# OS / temp',
            '*.tmp',
            '*.swp',
            '',
        ])."\n";
    }

    private function batchKey(string $themeDirectory, int $userId): string
    {
        return 'theme_git_batch:'.Str::slug($themeDirectory).':'.$userId;
    }

    private function lockKey(string $themeDirectory, int $userId): string
    {
        return 'theme_git_lock:'.Str::slug($themeDirectory).':'.$userId;
    }
}
