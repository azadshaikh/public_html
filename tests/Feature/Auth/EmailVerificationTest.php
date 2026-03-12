<?php

namespace Tests\Feature\Auth;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create([
            'status' => Status::ACTIVE,
        ]);

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/verify-email'));
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create([
            'first_name' => 'Test',
            'status' => Status::ACTIVE,
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)],
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create([
            'status' => Status::ACTIVE,
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')],
        );

        $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_user_id(): void
    {
        $user = User::factory()->unverified()->create([
            'status' => Status::ACTIVE,
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => 123, 'hash' => sha1((string) $user->email)],
        );

        $this->actingAs($user)->get($verificationUrl);

        Event::assertNotDispatched(Verified::class);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verified_user_is_redirected_to_dashboard_from_verification_prompt(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'status' => Status::ACTIVE,
        ]);

        Event::fake();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        Event::assertNotDispatched(Verified::class);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_already_verified_user_visiting_verification_link_is_redirected_without_firing_event_again(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'status' => Status::ACTIVE,
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1((string) $user->email)],
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect(route('dashboard', absolute: false));

        Event::assertNotDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
