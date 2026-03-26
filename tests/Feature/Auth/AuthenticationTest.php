<?php

namespace Tests\Feature\Auth;

use App\Enums\Status;
use App\Models\Settings;
use App\Models\User;
use App\Services\SettingsCacheService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/login')
                ->where('canResetPassword', true)
                ->where('canRegister', true)
                ->has('socialProviders.google')
                ->has('socialProviders.github'));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'status' => Status::ACTIVE,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('login'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $user = User::factory()->withTwoFactor()->create([
            'status' => Status::ACTIVE,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge'));
        $response->assertSessionHas('auth.two_factor.user_id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'status' => Status::ACTIVE,
        ]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect();

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'User',
            'causer_id' => $user->id,
            'causer_type' => User::class,
            'subject_id' => $user->id,
            'subject_type' => User::class,
            'event' => 'logout',
            'description' => 'User logged out.',
        ]);
    }

    public function test_users_are_rate_limited(): void
    {
        $user = User::factory()->create();

        Settings::query()->create([
            'key' => 'login_security_limit_login_attempts_enabled',
            'value' => 'true',
            'type' => 'boolean',
        ]);

        Settings::query()->create([
            'key' => 'login_security_limit_login_attempts',
            'value' => '5',
            'type' => 'integer',
        ]);

        Settings::query()->create([
            'key' => 'login_security_lockout_time',
            'value' => '60',
            'type' => 'integer',
        ]);

        app(SettingsCacheService::class)->refresh();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            RateLimiter::hit('127.0.0.1', 3600);
        }

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }
}
