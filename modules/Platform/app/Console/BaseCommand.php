<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Modules\Platform\Exceptions\WaitingException;
use Modules\Platform\Models\Website;
use Throwable;

/**
 * An abstract base command for website provisioning tasks.
 *
 * This class provides shared functionality for all commands that interact with a website
 * during provisioning operations (Hestia, Bunny, etc.). It includes a standardized way to
 * retrieve the website model and a consistent structure for command execution and error handling.
 *
 * Subclasses must implement the `handleCommand` method, which contains the core logic
 * for that specific command.
 */
abstract class BaseCommand extends Command
{
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Base command for website provisioning operations.';

    /**
     * The step key for this command (e.g., 'create_user', 'create_website', 'setup_bunny_cdn').
     * Subclasses should override this property to enable automatic error status updates.
     */
    protected ?string $stepKey = null;

    /**
     * Exit code indicating the step is waiting for an external condition (e.g., DNS propagation).
     * The orchestrator interprets this as "pause pipeline, will resume later."
     */
    public const EXIT_WAITING = 2;

    /**
     * Execute the console command.
     *
     * This method acts as a template for command execution. It retrieves the website,
     * logs the start of the operation, and wraps the core logic in a try-catch block
     * to ensure that failures are handled gracefully.
     *
     * @return int The command exit code.
     */
    public function handle(): int
    {
        $website = null;

        try {
            $websiteId = $this->argument('website_id');
            // Use withTrashed() to allow operations on soft-deleted websites (e.g., trashed status)
            /** @var Website $website */
            $website = Website::withTrashed()->findOrFail($websiteId);

            $this->info(sprintf('Starting: %s for %s (#%d)', $this->description, $website->name, $website->id));

            // Delegate the core logic to the subclass.
            $this->handleCommand($website);

            $this->info(sprintf('Completed: %s for %s (#%d)', $this->description, $website->name, $website->id));

            return self::SUCCESS;
        } catch (WaitingException $waitingException) {
            // Step is waiting for an external condition — not a failure
            $this->info(sprintf('Waiting: %s', $waitingException->getMessage()));

            return self::EXIT_WAITING;
        } catch (Throwable $throwable) {
            $this->error(sprintf('Failed: %s. Error: %s', $this->description, $throwable->getMessage()));

            // Update the provisioning step status to 'failed' with the error message
            if ($website && $this->stepKey) {
                $website->updateProvisioningStep(
                    $this->stepKey,
                    'Error: '.$throwable->getMessage(),
                    'failed'
                );
            }

            // Re-throw the exception to ensure the queue worker marks the job as failed.
            throw $throwable;
        }
    }

    /**
     * The core logic of the command.
     *
     * Subclasses must implement this method to perform the specific provisioning task.
     *
     * @param  Website  $website  The website instance to operate on.
     */
    abstract protected function handleCommand(Website $website): void;
}
