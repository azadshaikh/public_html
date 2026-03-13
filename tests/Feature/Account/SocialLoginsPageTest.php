<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SocialLoginsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_logins_page_is_displayed_with_connected_and_available_providers(): void
    {
        config()->set('services.social_auth.enabled', true);
        config()->set('services.google.enabled', true);
        config()->set('services.github.enabled', true);

        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
        ]);

        UserProvider::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-123',
            'created_at' => now()->setDate(2026, 3, 13)->setTime(13, 32),
            'updated_at' => now()->setDate(2026, 3, 13)->setTime(13, 32),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('app.profile.security.social-logins'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('account/social-logins')
                ->has('connectedProviders', 1)
                ->where('connectedProviders.0.key', 'google')
                ->where('connectedProviders.0.label', 'Google')
                ->where('connectedProviders.0.connected_at_label', 'Mar 13, 2026 13:32')
                ->has('availableProviders', 1)
                ->where('availableProviders.0.key', 'github')
                ->where('availableProviders.0.label', 'GitHub'));
    }

    public function test_social_logins_page_redirects_when_social_login_is_disabled(): void
    {
        config()->set('services.social_auth.enabled', false);
        config()->set('services.google.enabled', false);
        config()->set('services.github.enabled', false);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.profile.security.social-logins'))
            ->assertRedirect(route('app.profile.security', absolute: false))
            ->assertSessionHas('info');
    }
}
