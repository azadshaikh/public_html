<?php

namespace App\Providers;

use App\Console\Commands\MarkJobsAsStaleCommand;
use App\Console\Commands\PurgeOldMonitorsCommand;
use App\Models\Monitor;
use App\Services\QueueMonitor;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class QueueMonitorProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MarkJobsAsStaleCommand::class,
                PurgeOldMonitorsCommand::class,
            ]);
        }

        // listen to JobQueued event
        if (config('queue-monitor.monitor_queued_jobs', true)) {
            /**
             * If the project uses Horizon, we will listen to the JobPushed event,
             * because Horizon fires JobPushed event when the job is queued or retry the job again from its UI.
             *
             * @see https://laravel.com/docs/horizon
             */
            if (class_exists('Laravel\Horizon\Events\JobPushed')) {
                Event::listen('Laravel\Horizon\Events\JobPushed', function ($event): void {
                    QueueMonitor::handleJobPushed($event);
                });
            } else {
                Event::listen(JobQueued::class, function (JobQueued $event): void {
                    QueueMonitor::handleJobQueued($event);
                });
            }
        }

        // listen to other job events

        /** @var QueueManager $manager */
        $manager = resolve(QueueManager::class);

        $manager->before(static function (JobProcessing $event): void {
            QueueMonitor::handleJobProcessing($event);
        });

        $manager->after(static function (JobProcessed $event): void {
            QueueMonitor::handleJobProcessed($event);
        });

        $manager->failing(static function (JobFailed $event): void {
            QueueMonitor::handleJobFailed($event);
        });

        $manager->exceptionOccurred(static function (JobExceptionOccurred $event): void {
            QueueMonitor::handleJobExceptionOccurred($event);
        });
    }

    public function register(): void
    {
        if (! $this->app->configurationIsCached()) {
            $this->mergeConfigFrom(
                __DIR__.'/../../config/queue-monitor.php',
                'queue-monitor'
            );
        }

        QueueMonitor::$model = config('queue-monitor.model') ?: Monitor::class;
    }
}
