<?php

namespace App\Console\Commands\GeoData;

use App\Services\GeoDataService;
use Exception;
use Illuminate\Console\Command;

class WarmCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geodata:cache-warm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up the geodata cache with commonly used data';

    /**
     * Execute the console command.
     */
    public function handle(GeoDataService $geoDataService): int
    {
        $this->info('Starting geodata cache warming...');

        try {
            $geoDataService->warmCache();

            $this->info('✓ Geodata cache warmed successfully!');

            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error('Failed to warm geodata cache: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
