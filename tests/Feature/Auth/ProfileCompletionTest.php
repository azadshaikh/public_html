<?php

namespace Tests\Feature\Auth;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_profile_completion_screen_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'status' => Status::ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('profile.complete'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/profile-complete')
                ->where('user.first_name', null)
                ->where('user.last_name', null)
                ->where('user.email', $user->email));
    }

    public function test_profile_can_be_completed(): void
    {
        $user = User::factory()->create([
            'first_name' => null,
            'last_name' => null,
            'status' => Status::ACTIVE,
        ]);

        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();

        $this->assertSame('Test', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('Test User', $user->name);
    }
}
