<?php

declare(strict_types=1);

namespace Modules\Billing\Providers;

use App\Modules\Support\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Contracts\BillingAggregator;
use Modules\Billing\Listeners\CreateInvoiceForOrder;
use Modules\Billing\Services\BillingService;
use Modules\Billing\Services\CouponScaffoldService;
use Modules\Billing\Services\CouponService;
use Modules\Billing\Services\CreditService;
use Modules\Billing\Services\CurrencyService;
use Modules\Billing\Services\InvoiceService;
use Modules\Billing\Services\PaymentService;
use Modules\Billing\Services\RefundService;
use Modules\Billing\Services\TaxService;
use Modules\Billing\Services\TransactionService;
use Modules\Orders\Events\OrderPaid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected function moduleSlug(): string
    {
        return 'billing';
    }

    public function boot(): void
    {
        parent::boot();

        // Listen for OrderPaid to create an invoice + payment record.
        // Guard with class_exists so this does nothing if the Orders module is disabled.
        if (class_exists(OrderPaid::class)) {
            Event::listen(OrderPaid::class, CreateInvoiceForOrder::class);
        }
    }

    public function register(): void
    {
        parent::register();

        $this->registerAllConfigFiles();

        // Bind contracts to implementations
        $this->app->bind(BillingAggregator::class, BillingService::class);

        // Singleton services
        $this->app->singleton(CurrencyService::class);
        $this->app->singleton(BillingService::class);
        $this->app->singleton(TaxService::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(CreditService::class);
        $this->app->singleton(RefundService::class);
        $this->app->singleton(TransactionService::class);
        $this->app->singleton(CouponService::class);
        $this->app->singleton(CouponScaffoldService::class);
    }

    protected function registerAllConfigFiles(): void
    {
        $configPath = $this->modulePath('config');

        if (! is_dir($configPath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
            $configKey = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
            $segments = explode('.', $this->moduleSlug().'.'.$configKey);

            $normalized = [];

            foreach ($segments as $segment) {
                if (end($normalized) !== $segment) {
                    $normalized[] = $segment;
                }
            }

            $key = $relativePath === 'config.php' ? $this->moduleSlug() : implode('.', $normalized);

            if (! $this->app->configurationIsCached()) {
                $existing = config($key, []);
                $moduleConfig = require $file->getPathname();
                config([$key => array_replace_recursive($existing, $moduleConfig)]);
            }
        }
    }
}
