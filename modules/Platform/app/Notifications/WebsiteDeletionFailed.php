<?php

namespace Modules\Platform\Notifications;

class WebsiteDeletionFailed extends PlatformNotification
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

        $urlBackend = route('platform.websites.index', 'all');

        return $this->payload('Website Deletion Failed!', $text, 'deletion_failed', 'website', 'high', 'ri-error-warning-line', $urlBackend, null, 'View websites')
            ->extra([
                'domain' => $this->domain,
                'error_message' => $this->error_message,
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
