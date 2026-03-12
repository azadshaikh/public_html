<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_monitor_snapshots', function (Blueprint $table): void {
            $table->id();
            // Start of the aggregation window (rounded down to the hour)
            $table->timestampTz('period_start')->index();
            $table->string('queue');
            $table->unsignedInteger('succeeded')->default(0);
            $table->unsignedInteger('failed')->default(0);
            // Average duration in seconds for succeeded jobs in this window
            $table->float('avg_duration')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['period_start', 'queue']);
            $table->index(['queue', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_monitor_snapshots');
    }
};
