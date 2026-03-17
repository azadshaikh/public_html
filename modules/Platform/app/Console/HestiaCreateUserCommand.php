<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Str;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Creates a new system user on the Hestia server for a given website.
 *
 * This command is the first step in the website provisioning process. It ensures a unique
 * system user is created on the target server, which will own the website's files and
 * databases. It includes a retry mechanism to handle username conflicts.
 */
class HestiaCreateUserCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The maximum number of retries for creating a user with a unique username.
     */
    private const int MAX_RETRIES = 3;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:create-user {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Hestia system user for a website.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'create_user';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the user creation fails after multiple retries.
     */
    protected function handleCommand(Website $website): void
    {
        $username = $this->getOrCreateUsername($website);
        $this->createUser($website, $username);
    }

    /**
     * Retrieves the username for the website (uses uid).
     *
     * @param  Website  $website  The website instance.
     * @return string The username for the website.
     */
    private function getOrCreateUsername(Website $website): string
    {
        $username = $website->website_username;
        if (empty($username)) {
            throw new Exception(sprintf('UID is missing for website #%d. Cannot create Hestia user.', $website->id));
        }

        return $username;
    }

    /**
     * Creates a user on the Hestia server, with a retry mechanism for username conflicts.
     *
     * For custom usernames (uid_source = 'custom'), the command will NOT auto-mutate;
     * it fails immediately so the user can choose a different name.
     *
     * For generated usernames it appends a lowercase numeric suffix and retries.
     *
     * @param  Website  $website  The website for which the user is being created.
     * @param  string  $username  The desired username.
     * @param  int  $retryCount  The current retry attempt number.
     *
     * @throws Exception If user creation fails.
     */
    private function createUser(Website $website, string $username, int $retryCount = 0): void
    {
        // Get server password from secrets, or create one if it doesn't exist
        $secretData = $website->getSecret('server_password');
        if (! $secretData) {
            // Generate a new server password and store it
            $password = Str::random(12);
            $website->setSecret('server_password', $password, 'password', $username);
            $this->info(sprintf('Generated new server password for website #%d.', $website->id));
        } else {
            $password = $secretData['value'];
        }

        $response = HestiaClient::execute(
            'v-add-user',
            $website->server,
            [
                'arg1' => $username,
                'arg2' => $password,
                'arg3' => $website->agency->email ?? config('astero.default_hestia_email'),
                'arg4' => 'default',
                'arg5' => explode(' ', $website->agency->name ?? 'Agency', 2)[0],
                'arg6' => explode(' ', $website->agency->name ?? 'Agency', 2)[1] ?? '',
            ]
        );

        if ($response['success']) {
            $this->logActivity($website, ActivityAction::CREATE, sprintf("Hestia user '%s' created successfully.", $username));
            $this->updateWebsiteStep($website, sprintf("User '%s' created successfully.", $username), 'done');

            return;
        }

        // Handle "user already exists" error (Hestia code 4)
        if (isset($response['code']) && $response['code'] === 4) {
            $isCustom = $website->getMetadata('uid_source') === 'custom';

            if ($isCustom) {
                $errorMessage = sprintf("Server username '%s' already exists on this server. Please choose a different custom username.", $username);
                $this->updateWebsiteStep($website, $errorMessage, 'failed');
                throw new Exception($errorMessage);
            }

            if ($retryCount < self::MAX_RETRIES) {
                $newUsername = $this->generateUniqueUsername($username, $retryCount);
                $this->warn(sprintf("Username '%s' exists. Retrying with '%s'.", $username, $newUsername));
                $website->setMetadata('provisioning.server_username', $newUsername);
                $website->save();
                $website->setSecret('server_password', $password, 'password', $newUsername);
                $this->createUser($website, $newUsername, $retryCount + 1);

                return;
            }
        }

        $errorMessage = $response['message'] ?? 'Unknown error occurred during user creation.';
        $this->updateWebsiteStep($website, $errorMessage, 'failed');
        throw new Exception($errorMessage);
    }

    /**
     * Generates a unique username by appending a lowercase numeric suffix.
     *
     * Uses deterministic, incrementing suffixes to avoid mixed-case surprises.
     *
     * @param  string  $baseUsername  The base username to start with.
     * @param  int  $attempt  The current retry attempt (0-based).
     * @return string A new, likely unique username.
     */
    private function generateUniqueUsername(string $baseUsername, int $attempt = 0): string
    {
        $suffix = str_pad((string) (($attempt + 1) * random_int(10, 99)), 2, '0', STR_PAD_LEFT);

        return strtolower(substr($baseUsername, 0, 8)).$suffix;
    }

    /**
     * Updates the website's provisioning step for 'create_user'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('create_user', $message, $status);
    }
}
