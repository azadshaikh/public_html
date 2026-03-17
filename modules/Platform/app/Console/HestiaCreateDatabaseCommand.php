<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Str;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Creates a new database and user on the Hestia server for a given website.
 *
 * The database engine (MySQL/MariaDB vs PostgreSQL) is determined from the server's
 * provision install options stored in metadata.
 */
class HestiaCreateDatabaseCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:create-database {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Hestia database and user for a website.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'create_database';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the database creation or credential storage fails.
     */
    protected function handleCommand(Website $website): void
    {
        $credentials = $this->generateDatabaseCredentials();
        $dbType = $this->determineHestiaDatabaseType($website);
        $identifiers = $this->buildDatabaseIdentifiers($website, $credentials, $dbType);

        $dbName = $identifiers['name'];
        $dbUser = $identifiers['user'];

        $this->createDatabaseOnServer($website, $credentials, $dbType);
        $this->storeDatabaseCredentials($website, $dbName, $dbUser, $credentials['password'], $dbType);

        $this->updateWebsiteStep($website, sprintf("Database '%s' created successfully.", $dbName), 'done');
    }

    /**
     * Calls the Hestia API to create the database and user.
     *
     * @param  Website  $website  The website instance.
     * @param  array  $credentials  The generated credentials.
     *
     * @throws Exception If the API call fails.
     */
    protected function createDatabaseOnServer(Website $website, array $credentials, string $dbType): void
    {
        $this->info(sprintf("Creating database '%s' for user '%s'.", $credentials['name'], $website->website_username));

        $response = HestiaClient::execute(
            'v-add-database',
            $website->server,
            [
                'arg1' => $website->website_username,
                'arg2' => $credentials['name'],
                'arg3' => $credentials['username'],
                'arg4' => $credentials['password'],
                'arg5' => $dbType,
            ]
        );

        if (! $response['success']) {
            throw new Exception('Failed to create database: '.$response['message']);
        }

        $this->logActivity($website, ActivityAction::CREATE, 'Database and user created on Hestia server.');
    }

    /**
     * Determine the Hestia DB type argument for v-add-database.
     *
     * Hestia expects 'mysql' for MySQL/MariaDB and 'pgsql' for PostgreSQL.
     */
    protected function determineHestiaDatabaseType(Website $website): string
    {
        $server = $website->server;
        if (! $server) {
            return 'pgsql';
        }

        $installOptions = (array) ($server->getMetadata('install_options') ?? []);

        $mysqlEnabled = (bool) ($installOptions['mysql'] ?? false) || (bool) ($installOptions['mysql8'] ?? false);

        return $mysqlEnabled ? 'mysql' : 'pgsql';
    }

    /**
     * Build final database identifiers that match engine-specific normalization rules.
     *
     * @param  array{name:string,username:string,password:string}  $credentials
     * @return array{name:string,user:string}
     */
    protected function buildDatabaseIdentifiers(Website $website, array $credentials, string $dbType): array
    {
        $ownerPrefix = (string) $website->website_username;

        if ($dbType === 'pgsql') {
            $ownerPrefix = strtolower($ownerPrefix);
        }

        return [
            'name' => $ownerPrefix.'_'.$credentials['name'],
            'user' => $ownerPrefix.'_'.$credentials['username'],
        ];
    }

    /**
     * Generates secure, random credentials for the database.
     *
     * @return array An array containing the database name, username, and password.
     */
    private function generateDatabaseCredentials(): array
    {
        return [
            'name' => 'db',
            'username' => 'db_user',
            'password' => Str::random(16), // Using Laravel's Str helper for a secure random string
        ];
    }

    /**
     * Stores the new database credentials in the password manager.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $dbName  The full name of the database.
     * @param  string  $dbUser  The full username for the database.
     * @param  string  $dbPassword  The database password.
     */
    private function storeDatabaseCredentials(Website $website, string $dbName, string $dbUser, string $dbPassword, string $dbType): void
    {
        $this->info(sprintf("Storing credentials for database '%s'.", $dbName));

        // Store database name and determined DB type in metadata
        $website->db_name = $dbName;
        $website->setMetadata('db_type', $dbType);
        $website->save();

        // Store database credentials as a secret with username and metadata
        $website->setSecret('database_password', $dbPassword, 'password', $dbUser, [
            'database_name' => $dbName,
        ]);

        $this->logActivity($website, ActivityAction::UPDATE, 'Database credentials stored securely.');
    }

    /**
     * Updates the website's provisioning step for 'create_database'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('create_database', $message, $status);
    }
}
