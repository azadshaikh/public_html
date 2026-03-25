<?php

namespace Modules\Platform\Notifications;

class ServerScriptsUpdated extends PlatformNotification
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

        $urlBackend = route('platform.servers.show', $server->id);

        return $this->payload('Server Scripts Updated!', $text, 'update', 'server', 'medium', 'ri-tools-line', $urlBackend, null, 'View server')
            ->extra([
                'server_id' => $server->id,
                'server_name' => $server->name,
                'uploaded_count' => $this->uploadedCount,
                'failed_count' => $this->failedCount,
            ])
            ->toArray();
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
