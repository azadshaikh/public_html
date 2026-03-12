<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

class CleanInvalidActivityLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'activity-log:clean-invalid
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up activity logs with invalid morph types (deleted model classes)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Analyzing activity logs for invalid morph types...');

        // Get all distinct morph types
        $causerTypes = ActivityLog::query()
            ->distinct('causer_type')
            ->whereNotNull('causer_type')
            ->pluck('causer_type');

        $subjectTypes = ActivityLog::query()
            ->distinct('subject_type')
            ->whereNotNull('subject_type')
            ->pluck('subject_type');

        // Find invalid types
        $invalidCauserTypes = $causerTypes->reject(fn ($type): bool => class_exists($type));
        $invalidSubjectTypes = $subjectTypes->reject(fn ($type): bool => class_exists($type));

        if ($invalidCauserTypes->isEmpty() && $invalidSubjectTypes->isEmpty()) {
            $this->info('✓ No invalid morph types found. All activity logs are valid.');

            return self::SUCCESS;
        }

        // Display summary
        $this->newLine();
        $this->warn('Found invalid morph types:');

        if ($invalidCauserTypes->isNotEmpty()) {
            $this->line("\nInvalid Causer Types:");
            foreach ($invalidCauserTypes as $type) {
                $count = ActivityLog::query()->where('causer_type', $type)->count();
                $this->line(sprintf('  • %s (%s records)', $type, $count));
            }
        }

        if ($invalidSubjectTypes->isNotEmpty()) {
            $this->line("\nInvalid Subject Types:");
            foreach ($invalidSubjectTypes as $type) {
                $count = ActivityLog::query()->where('subject_type', $type)->count();
                $this->line(sprintf('  • %s (%s records)', $type, $count));
            }
        }

        // Count total records to be affected
        $totalToUpdate = ActivityLog::query()
            ->where(function ($q) use ($invalidCauserTypes, $invalidSubjectTypes): void {
                if ($invalidCauserTypes->isNotEmpty()) {
                    $q->whereIn('causer_type', $invalidCauserTypes->toArray());
                }

                if ($invalidSubjectTypes->isNotEmpty()) {
                    $q->orWhereIn('subject_type', $invalidSubjectTypes->toArray());
                }
            })
            ->count();

        $this->newLine();
        $this->warn('Total records to be cleaned: '.$totalToUpdate);

        if ($this->option('dry-run')) {
            $this->info("\n[DRY RUN] No changes were made.");

            return self::SUCCESS;
        }

        // Confirm before proceeding
        if (! $this->option('force') && ! $this->confirm('Do you want to set these invalid references to NULL?', true)) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        // Clean up invalid causers
        if ($invalidCauserTypes->isNotEmpty()) {
            $causerUpdated = ActivityLog::query()
                ->whereIn('causer_type', $invalidCauserTypes->toArray())
                ->update([
                    'causer_id' => null,
                    'causer_type' => null,
                ]);

            $this->info(sprintf('✓ Cleaned %d records with invalid causer types', $causerUpdated));
        }

        // Clean up invalid subjects
        if ($invalidSubjectTypes->isNotEmpty()) {
            $subjectUpdated = ActivityLog::query()
                ->whereIn('subject_type', $invalidSubjectTypes->toArray())
                ->update([
                    'subject_id' => null,
                    'subject_type' => null,
                ]);

            $this->info(sprintf('✓ Cleaned %d records with invalid subject types', $subjectUpdated));
        }

        $this->newLine();
        $this->info('✓ Activity logs cleaned successfully!');

        return self::SUCCESS;
    }
}
