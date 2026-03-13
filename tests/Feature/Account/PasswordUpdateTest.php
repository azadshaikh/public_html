<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_update_page_is_displayed(): void
    {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

        $response = $this
            ->actingAs($user)
            ->get(route('app.profile.security.password'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('account/password')
                ->where('hasPassword', true));
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

        $response = $this
            ->actingAs($user)
            ->from(route('app.profile.security.password'))
            ->patch(route('app.profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create(['first_name' => 'Test', 'last_name' => 'User']);

        $response = $this
            ->actingAs($user)
            ->from(route('app.profile.security.password'))
            ->patch(route('app.profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('app.profile.security.password'));
    }
}
