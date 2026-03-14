<?php

declare(strict_types=1);

namespace Tests\Feature\Ajax;

use App\Enums\Status;
use App\Models\Role;
use App\Models\User;
use App\Services\GeoDataService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AjaxLookupEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superUser = User::factory()->create([
            'first_name' => 'Ajax',
            'last_name' => 'Manager',
            'name' => 'Ajax Manager',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
        $this->superUser->assignRole(Role::findByName('super_user', 'web'));
    }

    public function test_ajax_users_returns_matching_user_options(): void
    {
        $matchingUser = User::factory()->create([
            'name' => 'Alpha Example',
            'email' => 'alpha@example.com',
            'status' => Status::ACTIVE,
        ]);

        User::factory()->create([
            'name' => 'Beta Example',
            'email' => 'beta@example.com',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($this->superUser)
            ->getJson(route('app.ajax.users', ['search' => 'Alpha']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.value', (string) $matchingUser->id)
            ->assertJsonPath('items.0.label', 'Alpha Example (alpha@example.com)');
    }

    public function test_geo_countries_returns_select_options(): void
    {
        $this->actingAs($this->superUser);

        $this->mock(GeoDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getAllCountries')
                ->once()
                ->andReturn([
                    ['iso2' => 'IN', 'name' => 'India'],
                    ['iso2' => 'US', 'name' => 'United States'],
                ]);
        });

        $this->getJson(route('app.ajax.geo.countries'))
            ->assertOk()
            ->assertJson([
                'items' => [
                    [
                        'value' => 'IN',
                        'label' => 'India',
                    ],
                    [
                        'value' => 'US',
                        'label' => 'United States',
                    ],
                ],
            ]);
    }
}
