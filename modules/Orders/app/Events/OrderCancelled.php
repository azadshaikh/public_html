<?php

declare(strict_types=1);

namespace Modules\Orders\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Orders\Models\Order;

class OrderCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Order $order) {}
}
