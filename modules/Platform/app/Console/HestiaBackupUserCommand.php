<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Creates a backup of a user's data on the Hestia server.
 *
 * This command initiates the v-backup-user process in HestiaCP for a specific website's user.
 * It logs the attempt, execution time, and result of the backup operation.
 */
class HestiaBackupUserCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:backup-user {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup website user data on HestiaCP server.';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the backup process fails.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info('Starting backup for user: '.$website->website_username);
        $this->backupUser($website);
    }

    /**
     * Executes the user backup process on the Hestia server.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the API call to backup the user fails.
     */
    private function backupUser(Website $website): void
    {
        $startTime = microtime(true);

        $arguments = ['arg1' => $website->website_username];

        $response = HestiaClient::execute(
            'v-backup-user',
            $website->server,
            $arguments,
            120 // timeout
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::BACKUP,
            $response['message'] ?? sprintf('User backup attempt logged (completed in %ss)', $processTime),
            [
                'success' => $response['success'],
                'code' => $response['code'] ?? null,
                'website_id' => $website->uid,
                'username' => $website->website_username,
                'arguments' => $arguments,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            $errorMessage = $response['message'] ?? sprintf("User backup failed for '%s'.", $website->website_username);
            $this->updateBackupHistory($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        $successMessage = 'User backup created successfully for username: '.$website->website_username;
        $this->updateBackupHistory($website, $successMessage, 'done');
        $this->info(sprintf('User backup process completed successfully in %s seconds.', $processTime));
    }

    /**
     * Creates a record in the website's backup history.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the history entry.
     * @param  string  $status  The status of the backup (e.g., 'done', 'failed').
     */
    private function updateBackupHistory(Website $website, string $message, string $status): void
    {
        $metadata = $website->metadata ?? [];
        $history = $metadata['backup_history'] ?? [];

        $history[] = [
            'meta_key' => 'backup_user',
            'message' => $message,
            'status' => $status,
            'created_at' => now()->toIso8601String(),
            'updated_by' => $website->updated_by,
        ];

        $website->metadata = array_merge($metadata, ['backup_history' => $history]);
        $website->save();
    }
}
