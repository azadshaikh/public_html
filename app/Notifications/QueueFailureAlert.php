<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Support\NotificationPayload;
use Illuminate\Notifications\Notification;

class QueueFailureAlert extends Notification
{
    public function __construct(
        public readonly string $queue,
        public readonly int $failureCount,
        public readonly int $windowMinutes,
    ) {}

    /**
     * @return string[]
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        $url = route('app.masters.queue-monitor.index', [
            'status' => 'failed',
            'queue' => $this->queue,
        ]);

        return NotificationPayload::make(
            'Queue Failures: '.$this->queue,
            sprintf(
                '<strong>%d</strong> job(s) failed in the <strong>%s</strong> queue within the last %d minutes.',
                $this->failureCount,
                $this->queue,
                $this->windowMinutes,
            ),
        )
            ->module('QueueMonitor')
            ->type('queue_failure_alert')
            ->category('system')
            ->priority('high')
            ->icon('ri-error-warning-line')
            ->backendLink($url, 'Open queue monitor')
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
