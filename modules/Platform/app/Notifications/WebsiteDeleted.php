<?php

namespace Modules\Platform\Notifications;

class WebsiteDeleted extends PlatformNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public $domain) {}

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
        $text = 'Website deleted | <strong>'.$this->domain.'</strong>';

        $urlBackend = route('platform.websites.index', 'all');

        return $this->payload('Website Deleted!', $text, 'deleted', 'website', 'medium', 'ri-delete-bin-line', $urlBackend, null, 'View websites')
            ->extra([
                'domain' => $this->domain,
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
