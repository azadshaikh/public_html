<?php

namespace Modules\Platform\Notifications;

use Illuminate\Notifications\Notification;

class WebsiteDeleted extends Notification
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

        $url_backend = route('platform.websites.index', 'all');

        return [
            'title' => 'Website Deleted!',
            'module' => 'Platform',
            'type' => 'deleted',
            'category' => 'website',
            'priority' => 'medium',
            'icon' => 'ri-delete-bin-line',
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
