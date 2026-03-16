<?php

namespace Modules\CMS\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\Git\GitCommandService;

class GitInitCommand extends Command
{
    protected $signature = 'themes:git-init
                            {theme : Theme directory}
                            {--force : Re-initialize even if .git exists}
                            {--name= : Git user.name for commits}
                            {--email= : Git user.email for commits}';

    protected $description = 'Initialize a Git repository inside a theme directory (no shell_exec)';

    public function handle(GitCommandService $git): int
    {
        $theme = (string) $this->argument('theme');

        if ($git->repositoryExists($theme) && ! $this->option('force')) {
            $this->info(sprintf("Git already initialized for theme '%s'. Use --force to re-init.", $theme));

            return Command::SUCCESS;
        }

        $result = $git->executeInit($theme);
        if (! $result->success) {
            $this->error($result->error ?? 'Git init failed');

            return Command::FAILURE;
        }

        // Ensure a sensible .gitignore exists.
        $themeInfo = Theme::getThemeInfo($theme);
        $themePath = $themeInfo['path'] ?? null;

        if ($themePath && ! File::exists($themePath.'/.gitignore')) {
            File::put($themePath.'/.gitignore', $this->defaultGitignore());
            $this->line('Created .gitignore');
        }

        // Configure identity (optional)
        $name = (string) ($this->option('name') ?? '');
        $email = (string) ($this->option('email') ?? '');
        if ($name !== '' && $email !== '') {
            $identity = $git->configureUserIdentity($theme, $name, $email);
            if (! $identity->success) {
                $this->warn($identity->error ?? 'Failed to configure identity');
            }
        }

        // Create initial commit.
        $add = $git->executeAdd($theme);
        if (! $add->success) {
            $this->error($add->error ?? 'Git add failed');

            return Command::FAILURE;
        }

        $commit = $git->executeCommit($theme, 'Initial theme setup', ['allow_empty' => true]);
        if (! $commit->success) {
            $this->error($commit->error ?? 'Git commit failed');

            return Command::FAILURE;
        }

        $this->info(sprintf("Initialized Git repo for theme '%s'.", $theme));

        return Command::SUCCESS;
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
}
