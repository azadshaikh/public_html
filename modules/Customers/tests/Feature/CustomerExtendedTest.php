<?php

namespace Modules\Customers\Tests\Feature;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Modules\Customers\Enums\AnnualRevenue;
use Modules\Customers\Enums\CustomerSource;
use Modules\Customers\Enums\CustomerTier;
use Modules\Customers\Enums\Industry;
use Modules\Customers\Models\Customer;
use Modules\Customers\Providers\CustomersServiceProvider;
use Modules\Customers\Services\CustomerService;
use Tests\Support\InteractsWithModuleManifest;
use Tests\TestCase;

class CustomerExtendedTest extends TestCase
{
    use InteractsWithModuleManifest;
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->setUpModuleManifest('customers-extended.json', [
            'Customers' => 'enabled',
        ]);

        $this->ensureCustomersModuleEnvironment();
    }

    protected function tearDown(): void
    {
        $this->tearDownModuleManifest();

        parent::tearDown();
    }

    public function test_can_create_customer_with_extended_fields(): void
    {
        $user = User::factory()->create();
        $accountManager = User::factory()->create();

        $data = [
            'type' => 'company',
            'company_name' => 'Acme Corp',
            'email' => 'contact@acme.com',
            'phone' => '1234567890',
            'source' => CustomerSource::WEBSITE->value,
            'tier' => CustomerTier::GOLD->value,
            'status' => 'active',
            'industry' => Industry::Technology->value,
            'account_manager_id' => $accountManager->id,
            'org_size' => '51-200',
            'revenue' => AnnualRevenue::RANGE_1M_5M->value,
            'opt_in_marketing' => true,
            'tags' => ['vip', 'tech-giant'],
            'additional_emails' => ['support@acme.com'],
            'social_links' => ['twitter' => '@acme'],
            'user_id' => $user->id, // Service needs this if validation passed
        ];

        // Use Service directly
        $service = new CustomerService;
        /** @var Customer $customer */
        $customer = $service->setCustomer($data);

        $this->assertEquals('Acme Corp', $customer->company_name);
        $this->assertEquals('company', $customer->type);
        $this->assertEquals('gold', $customer->tier->value);
        $this->assertTrue($customer->opt_in_marketing);
        $this->assertEquals(Industry::Technology, $customer->industry);
        $this->assertEquals($accountManager->id, $customer->account_manager_id);

        $this->assertNotNull($customer->unique_id);
        $this->assertStringStartsWith('CUS', $customer->unique_id);
        $this->assertEquals(['vip', 'tech-giant'], $customer->tags);
        $this->assertEquals('support@acme.com', $customer->metadata['additional_emails'][0]);
    }

    public function test_unique_id_generation(): void
    {
        /** @var Customer $c1 */
        $c1 = Customer::factory()->create(['company_name' => 'C1', 'email' => 'c1@test.com']);
        /** @var Customer $c2 */
        $c2 = Customer::factory()->create(['company_name' => 'C2', 'email' => 'c2@test.com']);

        $this->assertNotNull($c1->unique_id);
        $this->assertNotNull($c2->unique_id);
        $this->assertNotEquals($c1->unique_id, $c2->unique_id);
    }

    public function test_addresses_saving(): void
    {
        $data = [
            'type' => 'person',
            'contact_first_name' => 'John',
            'contact_last_name' => 'Doe',
            'email' => 'john@doe.com',
            'phone' => '9876543210',
            'status' => 'active',
            'addresses' => [
                [
                    'type' => 'billing',
                    'address1' => '123 Main St',
                    'city' => 'New York',
                    'country_code' => 'US',
                    'is_primary' => true,
                ],
                [
                    'type' => 'shipping',
                    'address1' => '456 Ware Rd',
                    'city' => 'Jersey',
                    'country_code' => 'US',
                ],
            ],
        ];

        $service = new CustomerService;
        /** @var Customer $customer */
        $customer = $service->setCustomer($data);

        $this->assertCount(2, $customer->addresses);
        $this->assertEquals('billing', $customer->addresses->first()->type);
    }

    public function test_customer_can_create_user_account_without_password(): void
    {
        $data = [
            'type' => 'person',
            'contact_first_name' => 'Jane',
            'contact_last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '1234567890',
            'status' => 'active',
            'user_action' => 'create',
        ];

        $service = new CustomerService;
        /** @var Customer $customer */
        $customer = $service->setCustomer($data);

        $this->assertNotNull($customer->user_id);
        /** @var User|null $user */
        $user = User::query()->find($customer->user_id);
        $this->assertNotNull($user);
        $this->assertSame('jane@example.com', $user->email);
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_customer_updates_sync_to_user_account(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old@example.com',
        ]);
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'type' => 'person',
            'contact_first_name' => 'Old',
            'contact_last_name' => 'Name',
            'email' => $user->email,
            'phone' => '5551111',
            'status' => 'active',
        ]);

        $service = new CustomerService;
        $service->setCustomer([
            'type' => 'person',
            'contact_first_name' => 'New',
            'contact_last_name' => 'User',
            'email' => 'new@example.com',
            'phone' => '5552222',
            'status' => 'active',
            'user_action' => 'associate',
            'user_id' => $user->id,
        ], $customer);

        $user->refresh();

        $this->assertSame('New', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('new@example.com', $user->email);
    }

    public function test_user_updates_sync_to_customer(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'sync@example.com',
        ]);
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'type' => 'person',
            'contact_first_name' => 'Old',
            'contact_last_name' => 'Name',
            'email' => $user->email,
            'phone' => '4441111',
            'status' => 'active',
        ]);

        $userService = resolve(UserService::class);
        $userService->update($user, [
            'first_name' => 'Updated',
            'last_name' => 'Person',
            'email' => 'updated@example.com',
            'status' => Status::SUSPENDED,
        ]);

        $customer->refresh();

        $this->assertSame('Updated', $customer->contact_first_name);
        $this->assertSame('Person', $customer->contact_last_name);
        $this->assertSame('updated@example.com', $customer->email);
        $this->assertSame(Status::SUSPENDED->value, $customer->status?->value);
    }

    public function test_user_trash_and_restore_syncs_customer(): void
    {
        $user = User::factory()->create();
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'type' => 'person',
            'contact_first_name' => 'Jane',
            'contact_last_name' => 'Doe',
            'email' => $user->email,
            'status' => 'active',
        ]);

        $user->delete();

        /** @var Customer|null $trashedCustomer */
        $trashedCustomer = Customer::withTrashed()->find($customer->id);
        $this->assertNotNull($trashedCustomer);
        $this->assertTrue($trashedCustomer->trashed());

        $user->restore();

        /** @var Customer|null $restoredCustomer */
        $restoredCustomer = Customer::withTrashed()->find($customer->id);
        $this->assertNotNull($restoredCustomer);
        $this->assertFalse($restoredCustomer->trashed());
    }

    public function test_user_force_delete_removes_customer(): void
    {
        $user = User::factory()->create();
        /** @var Customer $customer */
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'type' => 'person',
            'contact_first_name' => 'John',
            'contact_last_name' => 'Smith',
            'email' => $user->email,
            'status' => 'active',
        ]);

        $user->forceDelete();

        $this->assertNull(Customer::withTrashed()->find($customer->id));
    }

    private function ensureCustomersModuleEnvironment(): void
    {
        if (! app()->providerIsLoaded(CustomersServiceProvider::class)) {
            app()->register(CustomersServiceProvider::class);
        }

        if (! Schema::hasTable('customers_customers')) {
            Artisan::call('migrate', [
                '--path' => base_path('modules/Customers/database/migrations'),
                '--realpath' => true,
                '--force' => true,
            ]);
        }

        if (! Role::query()->where('name', 'customer')->exists()) {
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\RolesAndPermissionsSeeder',
                '--force' => true,
            ]);
        }
    }
}
