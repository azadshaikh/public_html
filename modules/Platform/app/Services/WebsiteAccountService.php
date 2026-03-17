<?php

namespace Modules\Platform\Services;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Illuminate\Support\Str;
use Modules\Platform\Models\Website;

/**
 * Website Account Service - Account Management
 * ============================================================================
 *
 * RESPONSIBILITIES:
 * ├── Creates Super User accounts
 * ├── Creates Website Owner (Admin) accounts
 * ├── Creates Server-level accounts
 * └── Manages secrets generation for these accounts
 *
 * CALLED BY:
 * └── WebsiteService::create() - After website record creation
 */
class WebsiteAccountService
{
    use ActivityTrait;

    /**
     * The main public method to create all accounts for a website.
     *
     * It orchestrates the creation of each required account type and returns an array
     * containing the details of the created accounts, which is suitable for API responses.
     *
     * @param  Website  $website  The website instance for which to create accounts.
     * @return array A collection of created account data, including plain text passwords.
     */
    public function createAccountsForWebsite(Website $website): array
    {
        $createdAccounts = [];

        // Add Super User account
        $createdAccounts[] = $this->createSuperUserAccount($website);

        // Add Website Admin account (uses customer email when available)
        // Note: If customer has no email, no admin account is created. This is intentional -
        // agencies may create websites without customer association (demo, internal, agency-managed).
        // The website will still have Super User access via the agency.
        if ($this->resolveOwnerEmail($website) !== null) {
            $createdAccounts[] = $this->createOwnerAccount($website);
        }

        // Add Server account
        if ($website->server) {
            $createdAccounts[] = $this->createServerAccount($website);
        }

        return array_filter($createdAccounts);
    }

    /**
     * Creates the Super User account.
     */
    private function createSuperUserAccount(Website $website): array
    {
        return $this->createAccount(
            $website,
            'super_user_password',
            'su@astero.in',
            'Super User',
            'Super User Account Added.'
        );
    }

    /**
     * Creates the primary Website Admin account using the customer's email.
     */
    private function createOwnerAccount(Website $website): ?array
    {
        $customerEmail = $this->resolveOwnerEmail($website);

        if (! $customerEmail) {
            return null;
        }

        return $this->createAccount(
            $website,
            'website_admin_password',
            $customerEmail,
            'Website Admin',
            'Website Admin Account Added.'
        );
    }

    private function resolveOwnerEmail(Website $website): ?string
    {
        $customerEmail = data_get($website, 'customer_data.email');

        if (is_string($customerEmail) && $customerEmail !== '') {
            return $customerEmail;
        }

        $ownerEmail = data_get($website, 'owner.email');

        return is_string($ownerEmail) && $ownerEmail !== ''
            ? $ownerEmail
            : null;
    }

    /**
     * Creates the server-level user account for the website.
     */
    private function createServerAccount(Website $website): array
    {
        $serverUsername = $website->uid ?? ($website->server->username ?? 'server_user');

        return $this->createAccount(
            $website,
            'server_password',
            $serverUsername,
            'Server',
            'Server Account Added.'
        );
    }

    /**
     * The core private method for creating a single account.
     *
     * This method generates a password, creates the secret with encrypted username,
     * logs the activity, and returns the account details.
     *
     * @param  Website  $website  The parent website.
     * @param  string  $secretKey  The key for the secret (e.g., 'super_user_password').
     * @param  string  $username  The username/email for the account.
     * @param  string  $groupName  Human-readable name for the account type.
     * @param  string  $logMessage  The message to store in the activity log.
     * @return array Account data with username and plain text password.
     */
    private function createAccount(Website $website, string $secretKey, string $username, string $groupName, string $logMessage): array
    {
        $existingSecret = $website->getSecret($secretKey);
        if ($existingSecret && ! empty($existingSecret['value'])) {
            return [
                'group_name' => $groupName,
                'secret_key' => $secretKey,
                'username' => $existingSecret['username'] ?? $username,
                'password' => $existingSecret['value'],
            ];
        }

        $password = Str::random(8);

        // Create secret using HasSecrets trait with username
        $website->setSecret($secretKey, $password, 'password', $username);

        $this->logActivity($website, ActivityAction::CREATE, $logMessage, ['caused_by' => auth()->id() ?? 1]);

        return [
            'group_name' => $groupName,
            'secret_key' => $secretKey,
            'username' => $username,
            'password' => $password, // Return plain password
        ];
    }
}
