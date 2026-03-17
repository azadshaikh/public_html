<?php

namespace Modules\Platform\Console;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Modules\Platform\Libs\HestiaClient;
use Modules\Platform\Models\Website;

/**
 * Changes the nginx template for a website on the Hestia server.
 *
 * This command uses the 'v-change-web-domain-tpl' API call to change
 * the nginx configuration template for a website.
 */
class HestiaChangeWebTemplateCommand extends BaseCommand
{
    use ActivityTrait;

    /**
     * Available templates for status changes.
     */
    public const STATUS_TEMPLATES = [
        'active' => 'astero-active',
        'suspended' => 'astero-suspended',
        'expired' => 'astero-expired',
        'trashed' => 'astero-trashed',
        'inactive' => 'astero-inactive',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:change-web-template
                            {website_id : The ID of the website}
                            {template : The template name to apply (e.g., astero-active, astero-suspended, astero-expired, astero-trashed, astero-inactive)}
                            {--restart : Whether to restart the web server after template change}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change the nginx template for a website on the Hestia server.';

    /**
     * Get the appropriate template for a given status.
     *
     * @param  string  $status  The website status.
     * @return string The template name.
     */
    public static function getTemplateForStatus(string $status): string
    {
        return self::STATUS_TEMPLATES[$status] ?? 'astero-active';
    }

    /**
     * The core logic of the command.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the template change fails.
     */
    protected function handleCommand(Website $website): void
    {
        $template = $this->argument('template');

        $this->info(sprintf("Attempting to change template to '%s' for website: %s", $template, $website->domain));

        $this->changeWebTemplate($website, $template);

        if ($this->option('restart')) {
            $this->info('Restarting nginx service...');
            $this->restartNginx($website);
        }

        $this->info(sprintf("Template changed successfully to '%s'.", $template));
    }

    /**
     * Changes the web domain template on the Hestia server.
     *
     * @param  Website  $website  The website instance.
     * @param  string  $template  The template name to apply.
     *
     * @throws Exception If the API call fails.
     */
    private function changeWebTemplate(Website $website, string $template): void
    {
        $startTime = microtime(true);

        $arguments = [
            'arg1' => $website->website_username,
            'arg2' => $website->domain,
            'arg3' => $template,
        ];

        $response = HestiaClient::execute(
            'v-change-web-domain-tpl',
            $website->server,
            $arguments
        );

        $processTime = round(microtime(true) - $startTime, 2);

        $this->logActivity(
            $website,
            ActivityAction::UPDATE,
            $response['message'] ?? sprintf("Web template change to '%s' (completed in %ss)", $template, $processTime),
            [
                'success' => $response['success'],
                'code' => $response['code'] ?? null,
                'website_id' => $website->site_id,
                'domain' => $website->domain,
                'template' => $template,
                'process_time' => $processTime.' seconds',
            ]
        );

        if (! $response['success']) {
            throw new Exception($response['message'] ?? 'Unknown error occurred while changing web template.');
        }
    }

    /**
     * Restarts nginx service on the Hestia server.
     *
     * @param  Website  $website  The website instance.
     *
     * @throws Exception If the API call fails.
     */
    private function restartNginx(Website $website): void
    {
        $response = HestiaClient::execute(
            'v-restart-web',
            $website->server,
            []
        );

        if (! $response['success']) {
            $this->warn('Warning: Failed to restart nginx: '.$response['message']);
        }
    }
}
