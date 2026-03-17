<?php

namespace Modules\Platform\Notifications;

use Illuminate\Notifications\Notification;

class WebsiteUnexpirationFailed extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public $websiteobj, public $error_message = null) {}

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

        $text = 'Website unexpiration failed | <strong>'.$websiteobj->domain.'</strong>';
        if ($this->error_message) {
            $text .= ' | Error: '.$this->error_message;
        }

        $url_backend = route('platform.websites.show', $websiteobj->id);
        $url_frontend = 'https://'.$websiteobj->domain;

        return [
            'title' => 'Website Unexpiration Failed!',
            'module' => 'Platform',
            'type' => 'unexpiration_failed',
            'category' => 'website',
            'priority' => 'high',
            'icon' => 'ri-error-warning-fill',
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
