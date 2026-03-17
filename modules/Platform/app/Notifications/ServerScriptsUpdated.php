<?php

namespace Modules\Platform\Notifications;

use Illuminate\Notifications\Notification;

class ServerScriptsUpdated extends Notification
{
    /**
     * @var int
     */
    public $uploadedCount;

    /**
     * @var int
     */
    public $failedCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $server, int $uploadedCount = 0, int $failedCount = 0)
    {
        $this->uploadedCount = $uploadedCount;
        $this->failedCount = $failedCount;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toDatabase($notifiable): array
    {
        $server = $this->server;

        $text = 'Server scripts updated | <strong>'.$server->name.'</strong> ('.$this->uploadedCount.' files)';

        $url_backend = route('platform.servers.show', $server->id);

        return [
            'title' => 'Server Scripts Updated!',
            'module' => 'Platform',
            'type' => 'update',
            'category' => 'server',
            'priority' => 'medium',
            'icon' => 'ri-tools-line',
            'text' => $text,
            'url_backend' => $url_backend,
            'url_frontend' => '',
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [];
    }
}
