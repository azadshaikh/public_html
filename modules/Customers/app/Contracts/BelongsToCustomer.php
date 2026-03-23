<?php

declare(strict_types=1);

namespace Modules\Customers\Contracts;

use Modules\Customers\Models\Customer;

interface BelongsToCustomer
{
    public function getCustomer(int $customerId): ?Customer;

    public function getCustomerId(?Customer $customer = null): ?int;

    public function setCustomer(array $data, ?Customer $customer = null): Customer;

    public function deleteCustomer(int $customerId): bool;
}
