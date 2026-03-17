<?php

namespace Modules\Platform\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Platform\Models\Website;

/**
 * Fired when DNS propagation has not completed within the allowed timeout window.
 *
 * Platform admin listeners should use this event to alert the operations team
 * so manual intervention can be offered to the affected customer.
 */
class DnsVerificationTimeoutEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Website $website;

    public string $rootDomain;

    public int $checkCount;

    public function __construct(Website $website, string $rootDomain, int $checkCount)
    {
        $this->website = $website;
        $this->rootDomain = $rootDomain;
        $this->checkCount = $checkCount;
    }
}
