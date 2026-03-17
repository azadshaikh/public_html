<?php

namespace Modules\Platform\Notifications;

use Illuminate\Notifications\Notification;

class WebsiteUpdateFailed extends Notification
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

        $text = 'Website updation failed | <strong>'.$websiteobj->domain.'</strong>';

        $url_backend = route('platform.websites.show', $websiteobj->id);
        $url_frontend = 'https://'.$websiteobj->domain;

        return [
            'title' => 'Website Update Failed!',
            'module' => 'Platform',
            'type' => 'update_failed',
            'category' => 'website',
            'priority' => 'high',
            'icon' => 'ri-error-warning-line',
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
