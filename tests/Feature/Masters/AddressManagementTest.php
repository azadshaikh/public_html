<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use App\Enums\Status;
use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AddressManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'first_name' => 'Address',
            'last_name' => 'Super',
            'name' => 'Address Super',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));

        $this->administrator = User::factory()->create([
            'first_name' => 'Address',
            'last_name' => 'Admin',
            'name' => 'Address Admin',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->administrator->assignRole(Role::findByName('administrator', 'web'));
    }

    public function test_guests_are_redirected_from_address_management(): void
    {
        $this->get(route('app.masters.addresses.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_super_users_cannot_access_address_management(): void
    {
        $this->actingAs($this->administrator)
            ->get(route('app.masters.addresses.index'))
            ->assertForbidden();
    }

    public function test_super_users_can_view_the_address_index_page(): void
    {
        Address::query()->create($this->validPayload([
            'first_name' => 'Primary',
            'last_name' => 'Address',
        ]));

        $this->actingAs($this->superUser)
            ->get(route('app.masters.addresses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/addresses/index')
                ->has('config.columns', 8)
                ->where('config.columns.1.width', '250px')
                ->has('addresses.data', 1)
                ->where('addresses.data.0.full_name', 'Primary Address')
                ->where('statistics.total', 1)
                ->where('statistics.trash', 0)
                ->where('filters.status', 'all')
            );
    }

    public function test_super_users_can_create_an_address_and_replace_existing_primary_address(): void
    {
        $existingPrimary = Address::query()->create($this->validPayload([
            'first_name' => 'Existing',
            'last_name' => 'Primary',
            'is_primary' => true,
        ]));

        $this->actingAs($this->superUser)
            ->post(route('app.masters.addresses.store'), $this->validPayload([
                'first_name' => 'New',
                'last_name' => 'Primary',
                'address1' => '42 Replacement Street',
                'city' => 'Mumbai',
                'is_primary' => true,
            ]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('addresses', [
            'first_name' => 'New',
            'last_name' => 'Primary',
            'address1' => '42 Replacement Street',
            'city' => 'Mumbai',
            'is_primary' => true,
        ]);

        $this->assertFalse((bool) $existingPrimary->fresh()->is_primary);
    }

    public function test_super_users_can_view_show_and_edit_pages_for_an_address(): void
    {
        $address = Address::query()->create($this->validPayload());

        $this->actingAs($this->superUser)
            ->get(route('app.masters.addresses.show', $address))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/addresses/show')
                ->where('address.id', $address->id)
            );

        $this->actingAs($this->superUser)
            ->get(route('app.masters.addresses.edit', $address))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('masters/addresses/edit')
                ->where('address.id', $address->id)
                ->where('initialValues.address1', $address->address1)
            );
    }

    public function test_super_users_can_soft_delete_and_restore_an_address(): void
    {
        $address = Address::query()->create($this->validPayload());

        $this->actingAs($this->superUser)
            ->delete(route('app.masters.addresses.destroy', $address))
            ->assertRedirect(route('app.masters.addresses.index'))
            ->assertSessionHas('status');

        $this->assertSoftDeleted('addresses', ['id' => $address->id]);

        $this->actingAs($this->superUser)
            ->from(route('app.masters.addresses.index'))
            ->patch(route('app.masters.addresses.restore', $address->id))
            ->assertRedirect(route('app.masters.addresses.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'deleted_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'type' => 'home',
            'address1' => '221B Baker Street',
            'country' => 'India',
            'country_code' => 'IN',
            'state' => 'Maharashtra',
            'state_code' => 'MH',
            'city' => 'Pune',
            'zip' => '411001',
            'phone' => '9999999999',
            'phone_code' => '+91',
            'is_primary' => false,
            'is_verified' => true,
            'addressable_type' => User::class,
            'addressable_id' => $this->superUser->id,
        ], $overrides);
    }
}
