<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Traits\IsMonitored;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * BroadcastNotification
 *
 * Admin broadcast notification sent to multiple users.
 */
class BroadcastNotification extends Notification implements ShouldQueue
{
    use IsMonitored;
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  array<string, mixed>  $data  Notification data (title, text, priority, icon)
     */
    public function __construct(
        private readonly array $data
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->data['title'] ?? 'Announcement',
            'text' => $this->data['text'] ?? $this->data['message'] ?? '',
            'icon' => $this->data['icon'] ?? 'ri-broadcast-line',
            'url' => $this->data['url'] ?? $this->data['url_backend'] ?? null,
            'url_backend' => $this->data['url_backend'] ?? $this->data['url'] ?? null,
            'category' => NotificationCategory::Broadcast->value,
            'priority' => $this->data['priority'] ?? NotificationPriority::Medium->value,
            'module' => 'System',
            'type' => 'broadcast',
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
