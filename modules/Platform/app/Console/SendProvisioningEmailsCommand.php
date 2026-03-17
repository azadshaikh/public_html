<?php

namespace Modules\Platform\Console;

use App\Services\EmailService;
use Exception;
use Modules\Platform\Models\Website;

/**
 * Sends provisioning completion emails to website admin and super user.
 *
 * This command is the final step in provisioning, sending login credentials
 * to both the website admin and super user. It uses existing email templates
 * from the database and retrieves credentials from the website's secret storage.
 */
class SendProvisioningEmailsCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'platform:send-provisioning-emails {website_id : The ID of the website}';

    /**
     * The console command description.
     */
    protected $description = 'Send welcome emails with credentials to website admin and super user.';

    /**
     * The step key for this command.
     */
    protected ?string $stepKey = 'send_emails';

    /**
     * The core logic of the command.
     */
    protected function handleCommand(Website $website): void
    {
        $this->info(sprintf("Sending provisioning emails for '%s'.", $website->domain));

        $emailService = resolve(EmailService::class);
        $emailsSent = 0;
        $errors = [];

        // Get credentials from secrets (stored by HestiaInstallAsteroCommand)
        $adminSecret = $website->getSecret('website_admin_password');
        $superUserSecret = $website->getSecret('super_user_password');

        // Prepare common variables
        $adminSlug = $website->admin_slug ?? 'app';
        $baseVariables = [
            'domain' => $website->domain,
            'website_url' => 'https://'.$website->domain,
            'backend_url' => 'https://'.$website->domain.'/'.$adminSlug,
        ];
        $ownerName = data_get($website, 'customer_data.name')
            ?? data_get($website, 'owner.name')
            ?? 'Admin';

        // Send to website admin
        if ($website->skip_email) {
            $this->info('  ↷ Owner email is disabled for this website, skipping admin email.');
        } elseif ($adminSecret && ! empty($adminSecret['username'])) {
            try {
                $result = $emailService->sendEmail(
                    $adminSecret['username'],
                    'Website Setup Completion Email (send to user)',
                    array_merge($baseVariables, [
                        'first_name' => explode(' ', $ownerName)[0],
                        'email' => $adminSecret['username'],
                        'password' => $adminSecret['value'] ?? '',
                    ])
                );

                if ($result) {
                    $this->info('  ✓ Welcome email sent to website admin: '.$adminSecret['username']);
                    $emailsSent++;
                } else {
                    $errors[] = 'Failed to send email to website admin: '.$adminSecret['username'];
                    $this->warn('  ✗ Failed to send email to website admin: '.$adminSecret['username']);
                }
            } catch (Exception $e) {
                $errors[] = 'Error sending to admin: '.$e->getMessage();
                $this->warn('  ✗ Error sending to admin: '.$e->getMessage());
            }
        } else {
            $this->warn('  ⚠ Website admin credentials not found in secrets, skipping email.');
        }

        // Send notification to super user (without password for security)
        if ($superUserSecret && ! empty($superUserSecret['username'])) {
            try {
                // Use a simpler notification template that doesn't include password
                $result = $emailService->sendEmail(
                    $superUserSecret['username'],
                    'Website Setup Completion Email (send to admin)',
                    array_merge($baseVariables, [
                        'first_name' => 'Super User',
                        'email' => $superUserSecret['username'],
                        'password' => '(Check Platform → Websites → Password Vault)',
                    ])
                );

                if ($result) {
                    $this->info('  ✓ Notification email sent to super user: '.$superUserSecret['username']);
                    $emailsSent++;
                } else {
                    $errors[] = 'Failed to send email to super user: '.$superUserSecret['username'];
                    $this->warn('  ✗ Failed to send email to super user: '.$superUserSecret['username']);
                }
            } catch (Exception $e) {
                $errors[] = 'Error sending to super user: '.$e->getMessage();
                $this->warn('  ✗ Error sending to super user: '.$e->getMessage());
            }
        } else {
            $this->warn('  ⚠ Super user credentials not found in secrets, skipping email.');
        }

        // Update provisioning step
        $message = $emailsSent.' provisioning email(s) sent.';
        if ($errors !== []) {
            $message .= ' Errors: '.implode('; ', $errors);
        }

        $website->updateProvisioningStep('send_emails', $message, 'done');

        $this->info(sprintf("Provisioning emails completed for '%s': %s email(s) sent.", $website->domain, $emailsSent));
    }
}
