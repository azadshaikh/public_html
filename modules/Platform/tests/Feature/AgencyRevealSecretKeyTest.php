<?php

namespace Modules\Platform\Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Modules\Platform\Models\Agency;
use Tests\TestCase;

class AgencyRevealSecretKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_secret_key_reveal_requires_current_password(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'password' => Hash::make('secret-pass-123'),
        ]);

        $agency = $this->createAgency($user, 'agency-secret-xyz');

        $response = $this->actingAs($user)->postJson(
            route('platform.agencies.secret-key.reveal', ['agency' => $agency->id]),
            []
        );

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_agency_secret_key_reveal_succeeds_with_current_password(): void
    {
        $this->withoutMiddleware();

        $user = User::factory()->create([
            'password' => Hash::make('secret-pass-123'),
        ]);

        $agency = $this->createAgency($user, 'agency-secret-xyz');

        $response = $this->actingAs($user)->postJson(
            route('platform.agencies.secret-key.reveal', ['agency' => $agency->id]),
            ['password' => 'secret-pass-123']
        );

        $response->assertOk()->assertJson([
            'success' => true,
            'value' => 'agency-secret-xyz',
        ]);
    }

    public function test_agency_show_does_not_include_plain_secret_key_in_page_props(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('secret-pass-123'),
        ]);
        Permission::query()->firstOrCreate([
            'name' => 'view_agencies',
            'guard_name' => 'web',
        ], [
            'display_name' => 'View Agencies',
            'group' => 'platform',
            'module_slug' => 'platform',
        ]);
        $user->givePermissionTo('view_agencies');

        $agency = $this->createAgency($user, 'agency-secret-xyz');

        $this->actingAs($user)
            ->get(route('platform.agencies.show', ['agency' => $agency->id]))
            ->assertOk()
            ->assertDontSee('agency-secret-xyz', false);
    }

    private function createAgency(User $owner, string $plainSecretKey): Agency
    {
        /** @var Agency $agency */
        $agency = Agency::query()->create([
            'uid' => 'AGY-REVEAL-'.(string) str()->random(6),
            'name' => 'Reveal Test Agency',
            'email' => 'agency@example.test',
            'type' => 'default',
            'plan' => 'starter',
            'website_id_prefix' => 'WS',
            'website_id_zero_padding' => 5,
            'owner_id' => $owner->id,
            'status' => 'active',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $agency->forceFill([
            'secret_key' => encrypt($plainSecretKey),
        ])->save();

        return $agency->fresh();
    }
}
