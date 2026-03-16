<?php

namespace Modules\CMS\Console;

use Illuminate\Console\Command;
use Modules\CMS\Services\Git\GitCommandService;

class GitCommitCommand extends Command
{
    protected $signature = 'themes:git-commit
                            {theme : Theme directory}
                            {message : Commit message}
                            {--files=* : One or more repo-relative paths to commit (defaults to all)}
                            {--allow-empty : Allow an empty commit}';

    protected $description = 'Create a Git commit for theme changes (no shell_exec)';

    public function handle(GitCommandService $git): int
    {
        $theme = (string) $this->argument('theme');
        $message = (string) $this->argument('message');
        $files = (array) $this->option('files');

        $add = $git->executeAdd($theme, $files);
        if (! $add->success) {
            $this->error($add->error ?? 'Git add failed');

            return Command::FAILURE;
        }

        $commit = $git->executeCommit($theme, $message, [
            'allow_empty' => (bool) $this->option('allow-empty'),
        ]);

        if (! $commit->success) {
            $this->error($commit->error ?? 'Git commit failed');

            return Command::FAILURE;
        }

        $this->info('Commit created.');
        if ($commit->output !== '') {
            $this->line($commit->output);
        }

        return Command::SUCCESS;
    }
}
