<?php

namespace Tests\Feature\Account;

use App\Enums\Status;
use App\Models\User;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_start_two_factor_setup(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user)
            ->post(route('app.profile.two-factor.store'))
            ->assertRedirect(route('app.profile.security.two-factor', absolute: false))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
    }

    public function test_authenticated_user_can_confirm_two_factor_setup(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'status' => Status::ACTIVE,
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => null,
        ]);

        $code = app(TwoFactorAuthenticationService::class)->generateCodeForSecret((string) $user->two_factor_secret);

        $this->actingAs($user)
            ->post(route('app.profile.two-factor.confirm'), [
                'code' => $code,
            ])
            ->assertRedirect(route('app.profile.security.two-factor', absolute: false))
            ->assertSessionHas('success')
            ->assertSessionHas('two_factor.recovery_codes');

        $user->refresh();

        $this->assertNotNull($user->two_factor_confirmed_at);
        $this->assertNotEmpty($user->two_factor_recovery_codes);
    }

    public function test_authenticated_user_can_disable_two_factor(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'first_name' => 'Test',
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user)
            ->delete(route('app.profile.two-factor.destroy'), [
                'current_password' => 'password',
            ])
            ->assertRedirect(route('app.profile.security.two-factor', absolute: false))
            ->assertSessionHas('success');

        $user->refresh();

        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_guest_is_redirected_from_two_factor_setup_route(): void
    {
        $this->post(route('app.profile.two-factor.store'))
            ->assertRedirect(route('login'));
    }
}
