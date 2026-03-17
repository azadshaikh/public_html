<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\DomainService;

/**
 * Installs an SSL certificate on a website using certificates from the domain's secrets.
 *
 * This command selects the best available SSL certificate from the domain record's
 * certificate collection, with the following priority:
 * 1. Wildcard certificates are preferred over non-wildcard
 * 2. Among same type, prefer certificates with longer validity (higher expiry date)
 * 3. Certificate must not be expired
 *
 * If no suitable certificate is found, it falls back to generating a self-signed certificate.
 */
class HestiaInstallSslCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:install-ssl {website_id : The ID of the website}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the best SSL certificate from domain secrets onto the website.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'install_ssl';

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the SSL installation fails.
     */
    protected function handleCommand(Website $website): void
    {
        $domainService = resolve(DomainService::class);

        // Check if website has an associated domain record
        $domainRecord = $website->domainRecord;

        if (! $domainRecord) {
            $this->warn(sprintf("Website '%s' has no associated domain record. Falling back to self-signed certificate.", $website->domain));
            $this->installSelfSignedCertificate($website);

            return;
        }

        // Get the best SSL certificate from the domain's secrets
        $certificate = $domainService->getBestSslCertificate($domainRecord);

        if (! $certificate) {
            $this->warn(sprintf("No valid SSL certificate found for domain '%s'. Falling back to self-signed certificate.", $domainRecord->domain_name));
            $this->installSelfSignedCertificate($website);

            return;
        }

        // Verify the certificate covers this website's domain
        if (! $domainService->certificateCoversDomain($certificate, $website->domain)) {
            $this->warn(sprintf("Certificate '%s' does not cover domain '%s'. Falling back to self-signed certificate.", $certificate->key, $website->domain));
            $this->installSelfSignedCertificate($website);

            return;
        }

        // Install the certificate
        $this->installCertificate($website, $certificate);
    }

    /**
     * Install a real SSL certificate from the domain's secrets.
     *
     * @param  Website  $website  The website instance.
     * @param  Secret  $certificate  The SSL certificate secret.
     *
     * @throws Exception If the installation fails.
     */
    private function installCertificate(Website $website, Secret $certificate): void
    {
        $certName = $certificate->getMetadata('name', $certificate->key);
        $isWildcard = $certificate->getMetadata('is_wildcard', false);
        $expiresAt = $certificate->expires_at?->format('Y-m-d');

        $this->info(sprintf("Installing SSL certificate '%s' ", $certName).($isWildcard ? '(wildcard)' : '').sprintf(" on '%s'.", $website->domain));
        $this->info('Certificate expires: '.$expiresAt);

        // Extract certificate data from the secret
        $certData = $certificate->getMetadata('certificate');
        $privateKey = $certificate->decrypted_value; // Private key stored as encrypted value
        $caBundle = $certificate->getMetadata('ca_bundle', '');

        throw_if(empty($certData) || empty($privateKey), Exception::class, sprintf("Certificate '%s' has incomplete data (missing certificate or private key).", $certName));

        // Base64 encode the certificate content for safe transmission
        $certB64 = base64_encode((string) $certData);
        $keyB64 = base64_encode($privateKey);
        $caB64 = empty($caBundle) ? '' : base64_encode((string) $caBundle);

        // Install SSL certificate using custom Hestia script that accepts base64 content
        $args = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
            'arg3' => $certB64,   // Base64 encoded SSL certificate
            'arg4' => $keyB64,    // Base64 encoded private key
        ];

        // Add CA bundle if present
        if ($caB64 !== '' && $caB64 !== '0') {
            $args['arg5'] = $caB64;
        }

        $response = HestiaClient::execute(
            'a-install-ssl-certificate',
            $website->server,
            $args,
            60
        );

        if (! $response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown error during SSL installation.';
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        // Associate the certificate with the website
        $website->ssl_secret_id = $certificate->id;
        $website->save();

        $successMessage = sprintf("SSL certificate '%s' installed successfully on '%s'", $certName, $website->domain).
            ($isWildcard ? ' (wildcard certificate)' : '').'.';

        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Install a self-signed SSL certificate as a fallback.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the generation fails.
     */
    private function installSelfSignedCertificate(Website $website): void
    {
        $this->info(sprintf("Generating self-signed SSL for '%s'.", $website->domain));

        // Use the custom script for self-signed certificate generation
        $response = HestiaClient::execute(
            'a-generate-ssl-certificate',
            $website->server,
            [
                'arg1' => $website->website_username,
                'arg2' => $website->domain,
                'arg3' => 'support@astero.in', // Email
                'arg4' => 'UK',                         // Country
                'arg5' => 'London',                     // State
                'arg6' => 'Covent Garden',              // City
                'arg7' => 'AsteroDigital',              // Organization
                'arg8' => 'IT',                         // Department
            ],
            60
        );

        if (! $response['success']) {
            $errorMessage = $response['message'] ?? 'Unknown error during self-signed SSL generation.';
            $this->updateWebsiteStep($website, $errorMessage, 'failed');
            throw new Exception($errorMessage);
        }

        // No certificate association for self-signed
        $website->ssl_secret_id = null;
        $website->save();

        $successMessage = sprintf("Self-signed SSL certificate for '%s' generated and installed successfully.", $website->domain);
        $this->logActivity($website, ActivityAction::UPDATE, $successMessage);
        $this->updateWebsiteStep($website, $successMessage, 'done');
    }

    /**
     * Updates the website's provisioning step for 'install_ssl'.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $message  The message to log for the step.
     * @param  string  $status  The status of the step (e.g., 'done', 'failed').
     */
    private function updateWebsiteStep(Website $website, string $message, string $status): void
    {
        $website->updateProvisioningStep('install_ssl', $message, $status);
    }
}
