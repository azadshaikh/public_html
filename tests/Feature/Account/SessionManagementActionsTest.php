<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Enums\Status;
use App\Models\User;
use App\Models\UserProvider;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SessionManagementActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_password_verification_returns_success_for_the_authenticated_user(): void
    {
        $user = $this->createEligibleUser();

        $this->actingAs($user)
            ->postJson(route('app.profile.password.verify'), [
                'current_password' => 'password',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_session_list_endpoint_returns_the_authenticated_users_sessions(): void
    {
        $user = $this->createEligibleUser();

        $this->mock(AuthService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('getUserSessions')
                ->once()
                ->withArgs(fn (User $authUser, string $sessionId): bool => $authUser->is($user) && $sessionId !== '')
                ->andReturn([
                    [
                        'id' => 'current-session',
                        'ip_address' => '127.0.0.1',
                        'is_current' => true,
                    ],
                ]);
        });

        $this->actingAs($user)
            ->getJson(route('app.profile.sessions.get'))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'sessions' => [
                    [
                        'id' => 'current-session',
                        'ip_address' => '127.0.0.1',
                        'is_current' => true,
                    ],
                ],
            ]);
    }

    public function test_delete_session_returns_a_limited_response_when_session_management_is_unavailable(): void
    {
        $user = $this->createEligibleUser();

        $this->mock(AuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isSessionManagementSupported')
                ->once()
                ->andReturn(false);
        });

        $this->actingAs($user)
            ->deleteJson(route('app.profile.sessions.delete', 'other-session'))
            ->assertBadRequest()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_delete_other_sessions_returns_the_deleted_session_count(): void
    {
        $user = $this->createEligibleUser();

        $this->mock(AuthService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('isSessionManagementSupported')
                ->once()
                ->andReturn(true);

            $mock->shouldReceive('deleteOtherSessions')
                ->once()
                ->withArgs(fn (User $authUser, string $sessionId): bool => $authUser->is($user) && $sessionId !== '')
                ->andReturn(2);
        });

        $this->actingAs($user)
            ->deleteJson(route('app.profile.sessions.delete-others'))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'deleted_count' => 2,
            ]);
    }

    public function test_disconnect_social_login_removes_the_connected_provider(): void
    {
        $user = $this->createEligibleUser();

        UserProvider::query()->create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $this->actingAs($user)
            ->delete(route('app.profile.security.social-logins.disconnect', 'google'))
            ->assertRedirect(route('app.profile.security.social-logins'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('user_providers', [
            'user_id' => $user->id,
            'provider' => 'google',
        ]);
    }

    private function createEligibleUser(): User
    {
        return User::factory()->create([
            'first_name' => 'Session',
            'last_name' => 'User',
            'name' => 'Session User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }
}
