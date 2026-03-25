<?php

namespace Modules\Platform\Notifications;

class WebsiteExpired extends PlatformNotification
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

        $urlBackend = route('platform.websites.show', $websiteobj->id);
        $urlFrontend = 'https://'.$websiteobj->domain;

        return $this->payload('Website Expired!', $text, 'expired', 'website', 'high', 'ri-history-line', $urlBackend, $urlFrontend, 'View website in app', 'Visit website')
            ->extra([
                'website_id' => $websiteobj->id,
                'domain' => $websiteobj->domain,
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
