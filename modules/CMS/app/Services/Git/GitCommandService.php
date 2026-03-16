<?php

namespace Modules\CMS\Services\Git;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Modules\CMS\Models\Theme;
use Symfony\Component\Process\Process;
use Throwable;

class GitCommandService
{
    public function repositoryExists(string $themeDirectory): bool
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return false;
        }

        return File::isDirectory($themePath.'/.git');
    }

    public function executeInit(string $themeDirectory): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        if (! File::isDirectory($themePath)) {
            return GitResult::failure('theme_directory_not_found', data: ['path' => $themePath]);
        }

        $result = $this->run($themePath, ['init']);
        if (! $result->success) {
            return $result;
        }

        $this->audit('init', $themeDirectory, ['path' => $themePath]);

        return $result;
    }

    public function executeAdd(string $themeDirectory, array $files = []): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $args = ['add'];

        if ($files === []) {
            $args[] = '-A';
        } else {
            foreach ($files as $file) {
                $validated = $this->validateRepoRelativePath($file);
                if ($validated === null) {
                    return GitResult::failure('invalid_file_path', data: ['file' => $file]);
                }

                $args[] = $validated;
            }
        }

        $result = $this->run($themePath, $args);
        if ($result->success) {
            $this->audit('add', $themeDirectory, ['files' => $files]);
        }

        return $result;
    }

    public function executeCommit(string $themeDirectory, string $message, array $options = []): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $sanitizedMessage = $this->sanitizeCommitMessage($message);
        if ($sanitizedMessage === null) {
            return GitResult::failure('invalid_commit_message');
        }

        $args = ['commit', '-m', $sanitizedMessage];

        if (($options['amend'] ?? false) === true) {
            $args[] = '--amend';
        }

        if (($options['allow_empty'] ?? false) === true) {
            $args[] = '--allow-empty';
        }

        if (($options['no_verify'] ?? false) === true) {
            $args[] = '--no-verify';
        }

        $result = $this->run($themePath, $args);
        if ($result->success) {
            $this->audit('commit', $themeDirectory, ['message' => $sanitizedMessage]);
        }

        return $result;
    }

    public function getCurrentBranch(string $themeDirectory): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        return $this->run($themePath, ['rev-parse', '--abbrev-ref', 'HEAD']);
    }

    public function getHeadHash(string $themeDirectory): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        return $this->run($themePath, ['rev-parse', 'HEAD']);
    }

    public function executeLog(string $themeDirectory, array $options = []): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $limit = (int) ($options['limit'] ?? 50);
        $limit = max(1, min(500, $limit));

        $skip = (int) ($options['skip'] ?? 0);
        $skip = max(0, min(50000, $skip));

        $args = [
            'log',
            '--max-count='.$limit,
            '--skip='.$skip,
            '--date=iso-strict',
            "--pretty=format:%H\t%an\t%ae\t%ad\t%s",
        ];

        if (! empty($options['file'])) {
            $file = $this->validateRepoRelativePath((string) $options['file']);
            if ($file === null) {
                return GitResult::failure('invalid_file_path', data: ['file' => $options['file']]);
            }

            $args[] = '--';
            $args[] = $file;
        }

        $result = $this->run($themePath, $args);
        if (! $result->success) {
            return $result;
        }

        $commits = $this->parseLogOutput($result->output);

        return GitResult::success($result->output, ['commits' => $commits]);
    }

    public function executeStatusPorcelain(string $themeDirectory): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        return $this->run($themePath, ['status', '--porcelain']);
    }

    public function executeDiff(string $themeDirectory, string $fromCommit, string $toCommit): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $from = $this->validateCommitish($fromCommit);
        $to = $this->validateCommitish($toCommit);

        if ($from === null || $to === null) {
            return GitResult::failure('invalid_commit_reference');
        }

        return $this->run($themePath, ['diff', $from, $to]);
    }

    public function executeShowFile(string $themeDirectory, string $commit, string $filePath): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $ref = $this->validateCommitish($commit);
        $file = $this->validateRepoRelativePath($filePath);

        if ($ref === null || $file === null) {
            return GitResult::failure('invalid_reference');
        }

        return $this->run($themePath, ['show', $ref.':'.$file]);
    }

    public function executeShowIndexFile(string $themeDirectory, string $filePath): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $file = $this->validateRepoRelativePath($filePath);
        if ($file === null) {
            return GitResult::failure('invalid_reference');
        }

        return $this->run($themePath, ['show', ':'.$file]);
    }

    public function executeCommitChangedFiles(string $themeDirectory, string $commit): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $ref = $this->validateCommitish($commit);
        if ($ref === null) {
            return GitResult::failure('invalid_commit_reference');
        }

        $result = $this->run($themePath, ['show', '--name-status', '--pretty=format:', $ref]);
        if (! $result->success) {
            return $result;
        }

        $files = [];
        $lines = preg_split('/\r?\n/', trim($result->output)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line);
            $status = $parts[0];
            if ($status === '') {
                continue;
            }

            $kind = strtoupper(substr($status, 0, 1));

            if (($kind === 'R' || $kind === 'C') && count($parts) >= 3) {
                $oldPath = $this->validateRepoRelativePath($parts[1]);
                $newPath = $this->validateRepoRelativePath($parts[2]);
                if ($oldPath === null) {
                    continue;
                }

                if ($newPath === null) {
                    continue;
                }

                $files[] = [
                    'status' => $status,
                    'path' => $newPath,
                    'old_path' => $oldPath,
                ];

                continue;
            }

            if (count($parts) < 2) {
                continue;
            }

            $path = $this->validateRepoRelativePath($parts[1]);
            if ($path === null) {
                continue;
            }

            $files[] = [
                'status' => $status,
                'path' => $path,
            ];
        }

        return GitResult::success($result->output, ['files' => $files]);
    }

    public function executeCommitParentHash(string $themeDirectory, string $commit): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $ref = $this->validateCommitish($commit);
        if ($ref === null) {
            return GitResult::failure('invalid_commit_reference');
        }

        $result = $this->run($themePath, ['show', '-s', '--pretty=%P', $ref]);
        if (! $result->success) {
            return $result;
        }

        $parent = trim($result->output);
        $parentHash = $parent === '' ? null : explode(' ', $parent)[0];

        return GitResult::success($result->output, ['parent' => $parentHash]);
    }

    public function executeAddAllForPaths(string $themeDirectory, array $files): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $args = ['add', '-A', '--'];

        $validatedFiles = [];
        foreach ($files as $file) {
            $validated = $this->validateRepoRelativePath((string) $file);
            if ($validated === null) {
                return GitResult::failure('invalid_file_path', data: ['file' => $file]);
            }

            $validatedFiles[] = $validated;
            $args[] = $validated;
        }

        $result = $this->run($themePath, $args);
        if ($result->success) {
            $this->audit('add_paths', $themeDirectory, ['files' => $validatedFiles]);
        }

        return $result;
    }

    public function executeCheckoutFileFromCommit(string $themeDirectory, string $commit, string $filePath): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $ref = $this->validateCommitish($commit);
        $file = $this->validateRepoRelativePath($filePath);

        if ($ref === null || $file === null) {
            return GitResult::failure('invalid_reference');
        }

        return $this->run($themePath, ['checkout', $ref, '--', $file]);
    }

    public function executeCheckoutFileHead(string $themeDirectory, string $filePath): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $file = $this->validateRepoRelativePath($filePath);
        if ($file === null) {
            return GitResult::failure('invalid_file_path');
        }

        return $this->run($themePath, ['checkout', '--', $file]);
    }

    public function executeResetFile(string $themeDirectory, string $filePath): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $file = $this->validateRepoRelativePath($filePath);
        if ($file === null) {
            return GitResult::failure('invalid_file_path');
        }

        return $this->run($themePath, ['reset', '--', $file]);
    }

    public function executeBranch(string $themeDirectory, string $action, array $params = []): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        return match ($action) {
            'list' => $this->run($themePath, ['branch', '--list', '--format=%(refname:short)\t%(objectname)\t%(committerdate:iso-strict)\t%(contents:subject)']),
            'create' => $this->createBranch($themePath, $themeDirectory, $params),
            'switch' => $this->switchBranch($themePath, $themeDirectory, $params),
            'delete' => $this->deleteBranch($themePath, $themeDirectory, $params),
            default => GitResult::failure('invalid_branch_action', data: ['action' => $action]),
        };
    }

    public function configureUserIdentity(string $themeDirectory, string $name, string $email): GitResult
    {
        $themePath = $this->getThemePath($themeDirectory);
        if ($themePath === null) {
            return GitResult::failure('theme_not_found', data: ['theme' => $themeDirectory]);
        }

        $name = trim($name);
        $email = trim($email);

        if ($name === '' || strlen($name) > 255) {
            return GitResult::failure('invalid_git_user_name');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            return GitResult::failure('invalid_git_user_email');
        }

        $r1 = $this->run($themePath, ['config', 'user.name', $name]);
        if (! $r1->success) {
            return $r1;
        }

        $r2 = $this->run($themePath, ['config', 'user.email', $email]);
        if (! $r2->success) {
            return $r2;
        }

        $this->audit('config_identity', $themeDirectory, ['name' => $name, 'email' => $email]);

        return GitResult::success('Configured Git user identity');
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    private function getThemePath(string $themeDirectory): ?string
    {
        $themeDirectory = trim($themeDirectory);
        if ($themeDirectory === '' || str_contains($themeDirectory, '..') || str_contains($themeDirectory, '\\')) {
            return null;
        }

        $theme = Theme::getThemeInfo($themeDirectory);

        return $theme['path'] ?? null;
    }

    private function run(string $workingDirectory, array $args, int $timeoutSeconds = 60): GitResult
    {
        if (! File::isDirectory($workingDirectory)) {
            return GitResult::failure('working_directory_missing', data: ['cwd' => $workingDirectory]);
        }

        $process = new Process(array_merge(['git'], $args), $workingDirectory, [
            'GIT_TERMINAL_PROMPT' => '0',
        ]);

        $process->setTimeout($timeoutSeconds);

        try {
            $process->run();

            $output = rtrim($process->getOutput());
            $errorOutput = rtrim($process->getErrorOutput());

            if (! $process->isSuccessful()) {
                Log::warning('Git command failed', [
                    'cwd' => $workingDirectory,
                    'args' => $args,
                    'exit_code' => $process->getExitCode(),
                    'error' => $errorOutput,
                ]);

                return GitResult::failure($errorOutput !== '' ? $errorOutput : 'git_command_failed', $output, [
                    'exit_code' => $process->getExitCode(),
                ]);
            }

            return GitResult::success($output);
        } catch (Throwable $throwable) {
            Log::error('Git command exception', [
                'cwd' => $workingDirectory,
                'args' => $args,
                'error' => $throwable->getMessage(),
            ]);

            return GitResult::failure('git_exception: '.$throwable->getMessage());
        }
    }

    private function validateRepoRelativePath(string $path): ?string
    {
        $path = trim($path);
        $path = str_replace("\0", '', $path);
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            return null;
        }

        if (str_contains($path, ':')) {
            return null;
        }

        if (str_contains($path, '..')) {
            return null;
        }

        return $path;
    }

    private function sanitizeCommitMessage(string $message): ?string
    {
        $message = Str::squish($message);
        $message = str_replace(["\0", "\r", "\n"], ' ', $message);
        $message = trim($message);

        if ($message === '' || strlen($message) > 255) {
            return null;
        }

        return $message;
    }

    private function validateBranchName(string $branch): ?string
    {
        $branch = trim($branch);

        if ($branch === '' || strlen($branch) > 100) {
            return null;
        }

        if (str_contains($branch, '..') || str_contains($branch, '@{') || str_contains($branch, '\\')) {
            return null;
        }

        if (preg_match('/[\s~^:?*\[\]]/', $branch) === 1) {
            return null;
        }

        if (str_starts_with($branch, '-') || str_ends_with($branch, '/') || str_ends_with($branch, '.lock')) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*[A-Za-z0-9]$/', $branch) !== 1) {
            return null;
        }

        return $branch;
    }

    private function validateCommitish(string $ref): ?string
    {
        $ref = trim($ref);

        if ($ref === '' || strlen($ref) > 200) {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $ref) !== 1) {
            return null;
        }

        return $ref;
    }

    private function parseLogOutput(string $output): array
    {
        if (trim($output) === '') {
            return [];
        }

        $lines = preg_split('/\r?\n/', $output) ?: [];
        $commits = [];

        foreach ($lines as $line) {
            $parts = explode("\t", $line, 5);
            if (count($parts) < 5) {
                continue;
            }

            [$hash, $authorName, $authorEmail, $date, $subject] = $parts;
            $commits[] = [
                'hash' => $hash,
                'author_name' => $authorName,
                'author_email' => $authorEmail,
                'date' => $date,
                'subject' => $subject,
            ];
        }

        return $commits;
    }

    private function createBranch(string $themePath, string $themeDirectory, array $params): GitResult
    {
        $branch = $this->validateBranchName((string) ($params['name'] ?? ''));
        if ($branch === null) {
            return GitResult::failure('invalid_branch_name');
        }

        $from = $params['from'] ?? null;
        if ($from !== null) {
            $from = $this->validateCommitish((string) $from);
            if ($from === null) {
                return GitResult::failure('invalid_commit_reference');
            }
        }

        $args = ['branch', $branch];
        if ($from !== null) {
            $args[] = $from;
        }

        $result = $this->run($themePath, $args);
        if ($result->success) {
            $this->audit('branch_create', $themeDirectory, ['branch' => $branch, 'from' => $from]);
        }

        return $result;
    }

    private function switchBranch(string $themePath, string $themeDirectory, array $params): GitResult
    {
        $branch = $this->validateBranchName((string) ($params['name'] ?? ''));
        if ($branch === null) {
            return GitResult::failure('invalid_branch_name');
        }

        $result = $this->run($themePath, ['checkout', $branch]);
        if ($result->success) {
            $this->audit('branch_switch', $themeDirectory, ['branch' => $branch]);
        }

        return $result;
    }

    private function deleteBranch(string $themePath, string $themeDirectory, array $params): GitResult
    {
        $branch = $this->validateBranchName((string) ($params['name'] ?? ''));
        if ($branch === null) {
            return GitResult::failure('invalid_branch_name');
        }

        $force = (bool) ($params['force'] ?? false);

        $result = $this->run($themePath, ['branch', $force ? '-D' : '-d', $branch]);
        if ($result->success) {
            $this->audit('branch_delete', $themeDirectory, ['branch' => $branch, 'force' => $force]);
        }

        return $result;
    }

    private function audit(string $operation, string $themeDirectory, array $properties = []): void
    {
        try {
            $ip = null;
            try {
                $ip = Request::ip();
            } catch (Throwable) {
                // ignore
            }

            $activity = activity('Theme Git')
                ->event($operation)
                ->withProperties(array_merge($properties, [
                    'theme_directory' => $themeDirectory,
                    'ip' => $ip,
                ]));

            if (auth()->guard()->check()) {
                $activity->causedBy(auth()->guard()->user());
            }

            $activity->log('Git operation');
        } catch (Throwable $throwable) {
            Log::debug('Failed to write git activity log', [
                'operation' => $operation,
                'theme_directory' => $themeDirectory,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
