<?php

declare(strict_types=1);

namespace Modules\Customers\Services;

use App\Enums\Status;
use App\Jobs\SendAuthEmail;
use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Customers\Models\Customer;
use Nwidart\Modules\Facades\Module;

class CustomerUserSyncService
{
    private static bool $syncing = false;

    public function createUserForCustomer(Customer $customer, array $data = []): ?User
    {
        if (! $this->customersModuleEnabled()) {
            return null;
        }

        $email = $customer->email;
        if (empty($email)) {
            return null;
        }

        if (User::query()->where('email', $email)->exists()) {
            return null;
        }

        $password = $data['user_password'] ?? null;
        $sendPasswordReset = empty($password);

        if ($sendPasswordReset) {
            $password = Str::random(16);
        }

        $payload = [
            'first_name' => $customer->contact_first_name,
            'last_name' => $customer->contact_last_name,
            'email' => $email,
            'password' => $password,
            'status' => Status::ACTIVE,
            'phone' => $customer->phone,
            'roles' => $this->resolveCustomerRoleIds(),
        ];

        $user = resolve(UserService::class)->create($payload);

        if ($sendPasswordReset) {
            $this->queuePasswordReset($user);
        }

        return $user;
    }

    public function ensureCustomerRole(User $user): void
    {
        $role = Role::query()->where('name', 'customer')->first();

        if (! $role) {
            return;
        }

        if (! $user->hasRole($role->name)) {
            $user->assignRole($role->name);
        }
    }

    public function syncUserFromCustomer(Customer $customer, ?User $user = null): void
    {
        if ($this->isSyncing() || ! $this->customersModuleEnabled()) {
            return;
        }

        $user ??= $customer->user;

        if (! $user) {
            return;
        }

        $updates = [
            'email' => $customer->email,
        ];

        if ($customer->contact_first_name !== null) {
            $updates['first_name'] = $customer->contact_first_name;
        }

        if ($customer->contact_last_name !== null) {
            $updates['last_name'] = $customer->contact_last_name;
        }

        $this->runWithSyncLock(function () use ($user, $updates, $customer): void {
            $dirty = $this->filterDirtyAttributes($user, $updates);

            if ($dirty !== []) {
                $user->forceFill($dirty)->saveQuietly();
            }

            $this->syncUserPhoneFromCustomer($user, $customer);
        });
    }

    public function syncCustomerFromUser(User $user, ?Customer $customer = null): void
    {
        if ($this->isSyncing() || ! $this->customersModuleEnabled()) {
            return;
        }

        if (! $customer instanceof Customer) {
            /** @var Customer|null $resolvedCustomer */
            $resolvedCustomer = Customer::query()->where('user_id', $user->id)->first();
            $customer = $resolvedCustomer;
        }

        if (! $customer) {
            return;
        }

        $updates = [
            'contact_first_name' => $user->first_name,
            'contact_last_name' => $user->last_name,
            'email' => $user->email,
            'status' => $user->status,
        ];

        /** @var Address|null $primaryAddress */
        $primaryAddress = $user->primaryAddress;

        if ($primaryAddress?->phone) {
            $updates['phone'] = $primaryAddress->phone;
        }

        $this->runWithSyncLock(function () use ($customer, $updates): void {
            $dirty = $this->filterDirtyAttributes($customer, $updates);

            if ($dirty !== []) {
                $customer->forceFill($dirty)->saveQuietly();
            }
        });
    }

    private function syncUserPhoneFromCustomer(User $user, Customer $customer): void
    {
        if ($customer->phone === null || $customer->phone === '') {
            return;
        }

        /** @var Address|null $address */
        $address = $user->primaryAddress;

        if ($address) {
            if ($address->phone !== $customer->phone) {
                $address->forceFill(['phone' => $customer->phone])->saveQuietly();
            }

            return;
        }

        $user->addresses()->create([
            'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $customer->phone,
            'type' => 'home',
            'is_primary' => true,
            'is_verified' => false,
        ]);
    }

    /**
     * @return array<int>
     */
    private function resolveCustomerRoleIds(): array
    {
        $role = Role::query()->where('name', 'customer')->first();

        if (! $role) {
            return [];
        }

        return [$role->id];
    }

    private function queuePasswordReset(User $user): void
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        dispatch(new SendAuthEmail('password_reset', $user->id, ['token' => $token]));
    }

    private function runWithSyncLock(callable $callback): void
    {
        if (self::$syncing) {
            return;
        }

        self::$syncing = true;

        try {
            $callback();
        } finally {
            self::$syncing = false;
        }
    }

    private function isSyncing(): bool
    {
        return self::$syncing;
    }

    private function customersModuleEnabled(): bool
    {
        if (! class_exists(Customer::class)) {
            return false;
        }

        if (! class_exists(Module::class)) {
            return true;
        }

        return Module::find('Customers')->isEnabled();
    }

    /**
     * @param  array<string, mixed>  $updates
     * @return array<string, mixed>
     */
    private function filterDirtyAttributes(object $model, array $updates): array
    {
        $dirty = [];

        foreach ($updates as $key => $value) {
            if ($model->{$key} !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }
}
