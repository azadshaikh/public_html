<?php

declare(strict_types=1);

namespace App\Notifications;

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

        return [
            'title' => 'Queue Failures: '.$this->queue,
            'module' => 'QueueMonitor',
            'type' => 'queue_failure_alert',
            'category' => 'system',
            'priority' => 'high',
            'icon' => 'ri-error-warning-line',
            'text' => sprintf('<strong>%d</strong> job(s) failed in the <strong>%s</strong> queue within the last %d minutes.', $this->failureCount, $this->queue, $this->windowMinutes),
            'url_backend' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [];
    }
}
