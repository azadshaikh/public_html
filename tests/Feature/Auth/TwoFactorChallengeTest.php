<?php

namespace Tests\Feature\Auth;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyFeature(Features::twoFactorAuthentication());
    }

    public function test_two_factor_challenge_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get(route('two-factor.challenge'));

        $response->assertRedirect(route('login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
    {
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create([
            'status' => Status::ACTIVE,
        ]);

        $this->withSession([
            'auth.two_factor' => [
                'user_id' => $user->id,
                'remember' => false,
                'created_at' => now()->timestamp,
            ],
        ])->get(route('two-factor.challenge'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/two-factor-challenge')
                ->where('email', $user->email),
            );
    }
}
