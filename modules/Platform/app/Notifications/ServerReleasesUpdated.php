<?php

namespace Modules\Platform\Notifications;

use Illuminate\Notifications\Notification;

class ServerReleasesUpdated extends Notification
{
    /**
     * @var string
     */
    public $releaseVersion;

    /**
     * @var bool
     */
    public $syncWarning;

    /**
     * Create a new notification instance.
     */
    public function __construct(public $server, string $releaseVersion = '', bool $syncWarning = false)
    {
        $this->releaseVersion = $releaseVersion;
        $this->syncWarning = $syncWarning;
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
        $versionSuffix = $this->releaseVersion !== '' ? ' (v'.$this->releaseVersion.')' : '';
        $warningSuffix = $this->syncWarning ? ' - server info sync warning' : '';

        $text = 'Server releases updated | <strong>'.$server->name.'</strong>'.$versionSuffix.$warningSuffix;

        $url_backend = route('platform.servers.show', $server->id);

        return [
            'title' => 'Server Releases Updated!',
            'module' => 'Platform',
            'type' => 'update',
            'category' => 'server',
            'priority' => 'medium',
            'icon' => 'ri-download-cloud-line',
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
