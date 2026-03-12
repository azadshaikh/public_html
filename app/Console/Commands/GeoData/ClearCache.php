<?php

namespace App\Console\Commands\GeoData;

use App\Services\GeoDataService;
use Exception;
use Illuminate\Console\Command;

class ClearCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geodata:cache-clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all geodata cache entries';

    /**
     * Execute the console command.
     */
    public function handle(GeoDataService $geoDataService): int
    {
        $this->info('Clearing geodata cache...');

        try {
            $geoDataService->clearCache();

            $this->info('✓ Geodata cache cleared successfully!');

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error('Failed to clear geodata cache: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
