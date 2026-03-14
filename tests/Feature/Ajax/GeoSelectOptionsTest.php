<?php

declare(strict_types=1);

namespace Tests\Feature\Ajax;

use App\Enums\Status;
use App\Models\User;
use App\Services\GeoDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class GeoSelectOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function signInActiveUser(): void
    {
        $this->actingAs(User::factory()->create([
            'status' => Status::ACTIVE,
            'first_name' => 'Geo',
            'last_name' => 'Tester',
            'email_verified_at' => now(),
        ]));
    }

    public function test_geo_states_use_iso_3166_2_codes_for_option_values(): void
    {
        $this->signInActiveUser();

        $this->mock(GeoDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getStatesByCountryCode')
                ->once()
                ->with('IN')
                ->andReturn([
                    [
                        'id' => 4034,
                        'name' => 'Chandigarh',
                        'iso2' => 'CH',
                        'iso3166_2' => 'IN-CH',
                    ],
                ]);
        });

        $this->getJson(route('app.ajax.geo.states', ['country_code' => 'IN']))
            ->assertOk()
            ->assertJson([
                'items' => [
                    [
                        'value' => 'IN-CH',
                        'label' => 'Chandigarh',
                    ],
                ],
            ]);
    }

    public function test_geo_cities_use_city_ids_for_option_values(): void
    {
        $this->signInActiveUser();

        $this->mock(GeoDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getCitiesByStateCode')
                ->once()
                ->with('IN-CH')
                ->andReturn([
                    [
                        'id' => 58190,
                        'name' => 'Chandigarh',
                    ],
                ]);
        });

        $this->getJson(route('app.ajax.geo.cities', ['state_code' => 'IN-CH']))
            ->assertOk()
            ->assertJson([
                'items' => [
                    [
                        'value' => '58190',
                        'label' => 'Chandigarh',
                    ],
                ],
            ]);
    }

    public function test_geo_cities_can_fall_back_to_country_lookup_when_no_state_code_is_provided(): void
    {
        $this->signInActiveUser();

        $this->mock(GeoDataService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getCitiesByCountryCode')
                ->once()
                ->with('IN')
                ->andReturn([
                    [
                        'id' => 58190,
                        'name' => 'Chandigarh',
                    ],
                ]);
        });

        $this->getJson(route('app.ajax.geo.cities', ['country_code' => 'IN']))
            ->assertOk()
            ->assertJson([
                'items' => [
                    [
                        'value' => '58190',
                        'label' => 'Chandigarh',
                    ],
                ],
            ]);
    }
}
