<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\Domain;
use Modules\Platform\Services\DomainService;
use Throwable;

/**
 * Syncs WHOIS data for a domain.
 *
 * This command fetches fresh WHOIS information and updates the domain record.
 * It's designed to be called from the DomainSyncWhois job or manually from
 * the command line.
 *
 * Unlike the refresh-whois feature in the domain CRUD, this command is meant
 * to be used during automated workflows like website provisioning.
 */
class DomainSyncWhoisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:sync-domain-whois {domain_id : The ID of the domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync WHOIS data for a domain';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $domainId = $this->argument('domain_id');
        $domain = Domain::withTrashed()->find($domainId);

        if (! $domain) {
            $this->error(sprintf('Domain with ID %s not found.', $domainId));
            Log::warning('DomainSyncWhoisCommand: Domain not found', ['domain_id' => $domainId]);

            return Command::FAILURE;
        }

        $this->info(sprintf('Syncing WHOIS data for domain: %s (ID: %d)', $domain->domain_name, $domain->id));

        try {
            $domainService = resolve(DomainService::class);
            $result = $domainService->refreshWhois($domain);

            if ($result['success'] ?? false) {
                $this->info('✔ WHOIS data synced successfully');
                $this->line('  '.$result['message']);

                return Command::SUCCESS;
            }

            $message = $result['message'] ?? 'WHOIS sync failed for unknown reason';
            $this->warn('⚠ '.$message);
            Log::warning('DomainSyncWhoisCommand: WHOIS sync unsuccessful', [
                'domain_id' => $domain->id,
                'domain_name' => $domain->domain_name,
                'message' => $message,
            ]);

            return Command::FAILURE;
        } catch (Throwable $throwable) {
            $this->error('❌ Error syncing WHOIS data: '.$throwable->getMessage());
            Log::error('DomainSyncWhoisCommand: Exception occurred', [
                'domain_id' => $domain->id,
                'domain_name' => $domain->domain_name,
                'error' => $throwable->getMessage(),
                'trace' => $throwable->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
