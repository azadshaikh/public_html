<?php

namespace Modules\Platform\Notifications;

class ServerReleasesUpdated extends PlatformNotification
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

        $urlBackend = route('platform.servers.show', $server->id);

        return $this->payload('Server Releases Updated!', $text, 'update', 'server', 'medium', 'ri-download-cloud-line', $urlBackend, null, 'View server')
            ->extra([
                'server_id' => $server->id,
                'server_name' => $server->name,
                'release_version' => $this->releaseVersion,
                'sync_warning' => $this->syncWarning,
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
