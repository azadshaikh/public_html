<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        // PostgreSQL performance optimizations for cache tables
        if (DB::connection()->getDriverName() === 'pgsql') {
            // UNLOGGED tables skip Write-Ahead Log, making writes significantly faster.
            // Trade-off: data is lost on crash, which is fine for cache.
            DB::statement('ALTER TABLE cache SET UNLOGGED');
            DB::statement('ALTER TABLE cache_locks SET UNLOGGED');

            // Tune autovacuum for high-churn tables (frequent inserts/deletes)
            DB::statement('ALTER TABLE cache SET (autovacuum_vacuum_scale_factor = 0.05, autovacuum_analyze_scale_factor = 0.05, autovacuum_vacuum_cost_delay = 10)');
            DB::statement('ALTER TABLE cache_locks SET (autovacuum_vacuum_scale_factor = 0.05, autovacuum_analyze_scale_factor = 0.05, autovacuum_vacuum_cost_delay = 10)');

            // Add partial index on expiration for faster cache cleanup
            DB::statement('CREATE INDEX IF NOT EXISTS idx_cache_expiration ON cache(expiration) WHERE expiration > 0');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
