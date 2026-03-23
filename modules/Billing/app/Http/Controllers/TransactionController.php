<?php

declare(strict_types=1);

namespace Modules\Billing\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Billing\Definitions\TransactionDefinition;
use Modules\Billing\Services\TransactionService;

class TransactionController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly TransactionService $transactionService
    ) {}

    public static function middleware(): array
    {
        return (new TransactionDefinition)->getMiddleware();
    }

    protected function service(): TransactionService
    {
        return $this->transactionService;
    }
}
