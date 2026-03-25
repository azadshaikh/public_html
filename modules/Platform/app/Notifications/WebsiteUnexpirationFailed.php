<?php

namespace Modules\Platform\Notifications;

class WebsiteUnexpirationFailed extends PlatformNotification
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

        $urlBackend = route('platform.websites.show', $websiteobj->id);
        $urlFrontend = 'https://'.$websiteobj->domain;

        return $this->payload('Website Unexpiration Failed!', $text, 'unexpiration_failed', 'website', 'high', 'ri-error-warning-fill', $urlBackend, $urlFrontend, 'View website in app', 'Visit website')
            ->extra([
                'website_id' => $websiteobj->id,
                'domain' => $websiteobj->domain,
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
