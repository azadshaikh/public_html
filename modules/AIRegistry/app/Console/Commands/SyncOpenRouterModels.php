<?php

declare(strict_types=1);

namespace Modules\AIRegistry\Console\Commands;

use Illuminate\Console\Command;
use Modules\AIRegistry\Models\AiProvider;
use Modules\AIRegistry\Services\OpenRouterModelSyncService;

class SyncOpenRouterModels extends Command
{
    protected $signature = 'airegistry:sync-openrouter-models';

    protected $description = 'Sync selected OpenRouter-backed models into the AI registry';

    public function handle(OpenRouterModelSyncService $openRouterModelSyncService): int
    {
        if (! AiProvider::query()->where('slug', 'openrouter')->exists()) {
            $this->error('OpenRouter provider is missing from AI Registry. Seed providers first.');

            return self::FAILURE;
        }

        if (! $openRouterModelSyncService->hasApiKey()) {
            $this->error('OpenRouter API key is missing. Configure it before syncing models.');

            return self::FAILURE;
        }

        $this->info('Syncing OpenRouter models for: '.implode(', ', $openRouterModelSyncService->importedProviderPrefixes()));

        $syncedCount = $openRouterModelSyncService->sync();

        $this->info(sprintf('Synced %d OpenRouter model(s) into AI Registry.', $syncedCount));

        return self::SUCCESS;
    }
}
