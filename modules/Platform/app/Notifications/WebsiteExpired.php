<?php

namespace Modules\Platform\Notifications;

use Illuminate\Notifications\Notification;

class WebsiteExpired extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public $websiteobj) {}

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
        $websiteobj = $this->websiteobj;

        $text = 'Website expired | <strong>'.$websiteobj->domain.'</strong>';

        $url_backend = route('platform.websites.show', $websiteobj->id);
        $url_frontend = 'https://'.$websiteobj->domain;

        return [
            'title' => 'Website Expired!',
            'module' => 'Platform',
            'type' => 'expired',
            'category' => 'website',
            'priority' => 'high',
            'icon' => 'ri-history-line',
            'text' => $text,
            'url_backend' => $url_backend,
            'url_frontend' => $url_frontend,
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
