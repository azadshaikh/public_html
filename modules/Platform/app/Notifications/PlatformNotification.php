<?php

namespace Modules\Platform\Notifications;

use App\Notifications\Support\NotificationPayload;
use Illuminate\Notifications\Notification;

abstract class PlatformNotification extends Notification
{
    protected function payload(
        string $title,
        string $text,
        string $type,
        string $category,
        string $priority,
        string $icon,
        ?string $backendHref = null,
        ?string $frontendHref = null,
        string $backendLabel = 'Open in app',
        string $frontendLabel = 'Open external page',
    ): NotificationPayload {
        $payload = NotificationPayload::make($title, $text)
            ->module('Platform')
            ->type($type)
            ->category($category)
            ->priority($priority)
            ->icon($icon);

        if ($backendHref !== null && $backendHref !== '') {
            $payload = $payload->backendLink($backendHref, $backendLabel);
        }

        if ($frontendHref !== null && $frontendHref !== '') {
            $payload = $payload->frontendLink($frontendHref, $frontendLabel);
        }

        return $payload;
    }
}
