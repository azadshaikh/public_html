<?php

declare(strict_types=1);

namespace Modules\Customers\Observers;

use App\Models\User;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerUserSyncService;

class UserCustomerSyncObserver
{
    public function updated(User $user): void
    {
        resolve(CustomerUserSyncService::class)->syncCustomerFromUser($user);
    }

    public function forceDeleting(User $user): void
    {
        $customer = Customer::withTrashed()->where('user_id', $user->id)->first();

        if ($customer) {
            $customer->forceDelete();
        }
    }

    public function deleted(User $user): void
    {
        if ($user->isForceDeleting()) {
            return;
        }

        $customer = Customer::withTrashed()->where('user_id', $user->id)->first();

        if ($customer && ! $customer->trashed()) {
            $customer->delete();
        }
    }

    public function restored(User $user): void
    {
        $customer = Customer::withTrashed()->where('user_id', $user->id)->first();

        if ($customer && $customer->trashed()) {
            $customer->restore();
        }
    }
}
