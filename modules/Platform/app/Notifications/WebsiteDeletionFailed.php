<?php

namespace Modules\Platform\Notifications;

use Illuminate\Notifications\Notification;

class WebsiteDeletionFailed extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public $domain, public $error_message = null) {}

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
        $text = 'Website deletion failed | <strong>'.$this->domain.'</strong>';
        if ($this->error_message) {
            $text .= ' | Error: '.$this->error_message;
        }

        $url_backend = route('platform.websites.index', 'all');
        $url_frontend = '';

        return [
            'title' => 'Website Deletion Failed!',
            'module' => 'Platform',
            'type' => 'deletion_failed',
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
