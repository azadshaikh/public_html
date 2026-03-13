<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecurityPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_is_displayed_with_expected_props(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('app.profile.security'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('account/security')
                ->where('twoFactorEnabled', false)
                ->where('twoFactorPending', false)
                ->where('connectedProviderCount', 0)
                ->where('activeSessionCount', 1)
                ->where('hasPassword', true)
                ->has('showSocialLoginCard')
                ->has('sessionManagementSupported'));
    }
}
