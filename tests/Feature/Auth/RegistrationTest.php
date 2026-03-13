<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendAuthEmail;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('auth/register')
                ->where('canLogin', true)
                ->has('socialProviders.google')
                ->has('socialProviders.github'));
    }

    public function test_new_users_can_register(): void
    {
        Queue::fake();

        $response = $this->post(route('register.store'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => '1',
        ]);

        $user = User::query()->where('email', 'test@example.com')->first();

        $this->assertNotNull($user);
        $this->assertAuthenticated();
        $response->assertRedirect(route('verification.notice', absolute: false));
        $response->assertSessionHas('status', 'verification-link-sent');

        Queue::assertPushed(SendAuthEmail::class, function (SendAuthEmail $job) use ($user): bool {
            return $job->type === 'verification' && $job->userId === $user?->id;
        });
    }
}
