<?php

namespace Modules\CMS\Console;

use Illuminate\Console\Command;
use Modules\CMS\Services\Git\GitCommandService;

class GitBranchCommand extends Command
{
    protected $signature = 'themes:git-branch
                            {theme : Theme directory}
                            {action : list|create|switch|delete}
                            {name? : Branch name (for create/switch/delete)}
                            {--from= : Commit/branch to branch from (create)}
                            {--force : Force delete (delete)}';

    protected $description = 'Manage Git branches for a theme (no shell_exec)';

    public function handle(GitCommandService $git): int
    {
        $theme = (string) $this->argument('theme');
        $action = (string) $this->argument('action');
        $name = (string) ($this->argument('name') ?? '');

        $params = [];

        if (in_array($action, ['create', 'switch', 'delete'], true)) {
            $params['name'] = $name;
        }

        if ($action === 'create' && $this->option('from')) {
            $params['from'] = (string) $this->option('from');
        }

        if ($action === 'delete') {
            $params['force'] = (bool) $this->option('force');
        }

        $result = $git->executeBranch($theme, $action, $params);

        if (! $result->success) {
            $this->error($result->error ?? 'Branch operation failed');

            return Command::FAILURE;
        }

        if ($action === 'list') {
            $rows = [];
            $lines = preg_split('/\r?\n/', trim($result->output)) ?: [];
            foreach ($lines as $line) {
                $parts = explode("\t", $line, 4);
                if (count($parts) < 4) {
                    continue;
                }

                [$branch, $hash, $date, $subject] = $parts;
                $rows[] = [$branch, $hash, $date, $subject];
            }

            $this->table(['Branch', 'Hash', 'Date', 'Subject'], $rows);
        } else {
            $this->info('OK');
            if ($result->output !== '') {
                $this->line($result->output);
            }
        }

        return Command::SUCCESS;
    }
}
