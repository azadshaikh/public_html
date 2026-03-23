<?php

declare(strict_types=1);

namespace Modules\Orders\Console\Commands;

use Illuminate\Console\Command;
use Modules\Orders\Models\Order;

/**
 * Auto-expire orders that have been stuck in "processing" for more than 24 hours.
 *
 * These are checkout sessions where Stripe captured the payment page but the
 * user never completed payment (or the success callback was never triggered).
 * After 24 hours we can safely mark them cancelled — Stripe Checkout Sessions
 * expire after 24 h by default, so no payment will arrive.
 */
class ExpireStaleOrders extends Command
{
    protected $signature = 'orders:expire-stale
                            {--hours=24 : Hours after which a processing order is considered stale}
                            {--dry-run : Show what would be expired without making changes}';

    protected $description = 'Cancel orders stuck in "processing" status beyond the configured threshold';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $staleOrders = Order::query()
            ->where('status', Order::STATUS_PROCESSING)
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($staleOrders->isEmpty()) {
            $this->info('No stale orders found.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '').sprintf('Found %d stale order(s) older than %d hours.', $staleOrders->count(), $hours));

        foreach ($staleOrders as $order) {
            /** @var Order $order */
            $this->line(sprintf('  Order #%s (ID: %d) — created %s', $order->order_number, $order->id, $order->created_at->diffForHumans()));

            if (! $dryRun) {
                $order->update([
                    'status' => Order::STATUS_CANCELLED,
                    'notes' => trim(($order->notes ?? '')."\nAuto-expired: no payment confirmation received within {$hours} hours."),
                ]);
            }
        }

        $verb = $dryRun ? 'Would cancel' : 'Cancelled';
        $this->info(sprintf('%s %d stale order(s).', $verb, $staleOrders->count()));

        return self::SUCCESS;
    }
}
