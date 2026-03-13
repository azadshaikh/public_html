<?php

namespace Tests\Feature\Account;

use App\Enums\Status;
use App\Http\Middleware\CheckUserStatusMiddleware;
use App\Http\Middleware\EnsureEmailVerificationIsSatisfied;
use App\Http\Middleware\EnsureProfileCompletionIsSatisfied;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\AuthService;
use Mockery\MockInterface;
use Tests\TestCase;

class SessionsPageTest extends TestCase
{
    public function test_sessions_page_is_displayed_with_supported_session_management(): void
    {
        $user = User::factory()->make([
            'first_name' => 'Super',
            'last_name' => 'User',
            'name' => 'Super User',
            'status' => Status::ACTIVE,
        ]);
        $user->id = 1;

        $currentSession = now()->subMinutes(5);
        $otherSession = now()->subHour();

        $this->mock(AuthService::class, function (MockInterface $mock) use ($user, $currentSession, $otherSession): void {
            $mock->shouldReceive('getUserSessions')
                ->once()
                ->withArgs(fn (User $authUser, string $sessionId): bool => $authUser->is($user) && $sessionId !== '')
                ->andReturn([
                    [
                        'id' => 'current-session',
                        'ip_address' => '127.0.0.1',
                        'is_current' => true,
                        'last_activity' => $currentSession->timestamp,
                        'last_active_at' => $currentSession,
                        'device' => 'Desktop',
                        'platform' => 'Linux',
                        'browser' => 'Chrome',
                    ],
                    [
                        'id' => 'other-session',
                        'ip_address' => '10.0.0.12',
                        'is_current' => false,
                        'last_activity' => $otherSession->timestamp,
                        'last_active_at' => $otherSession,
                        'device' => 'Mobile',
                        'platform' => 'iOS',
                        'browser' => 'Safari',
                    ],
                ]);

            $mock->shouldReceive('isSessionManagementSupported')
                ->once()
                ->andReturn(true);
        });

        $this->withoutMiddleware([
            CheckUserStatusMiddleware::class,
            EnsureEmailVerificationIsSatisfied::class,
            EnsureProfileCompletionIsSatisfied::class,
            HandleInertiaRequests::class,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('app.profile.security.sessions'), [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('component', 'account/sessions')
            ->assertJsonPath('props.sessionManagementSupported', true)
            ->assertJsonCount(2, 'props.sessions')
            ->assertJsonPath('props.sessions.0.id', 'current-session')
            ->assertJsonPath('props.sessions.0.is_current', true)
            ->assertJsonPath('props.sessions.1.id', 'other-session')
            ->assertJsonPath('props.sessions.1.browser', 'Safari')
            ->assertJsonPath('props.sessions.1.device', 'Mobile');
    }

    public function test_sessions_page_is_displayed_with_limited_session_management(): void
    {
        $user = User::factory()->make([
            'status' => Status::ACTIVE,
        ]);
        $user->id = 1;
        $currentSession = now()->subMinutes(2);

        $this->mock(AuthService::class, function (MockInterface $mock) use ($user, $currentSession): void {
            $mock->shouldReceive('getUserSessions')
                ->once()
                ->withArgs(fn (User $authUser, string $sessionId): bool => $authUser->is($user) && $sessionId !== '')
                ->andReturn([
                    [
                        'id' => 'current-session',
                        'ip_address' => '127.0.0.1',
                        'is_current' => true,
                        'last_activity' => $currentSession->timestamp,
                        'last_active_at' => $currentSession,
                        'device' => 'Desktop',
                        'platform' => 'Linux',
                        'browser' => 'Chrome',
                    ],
                ]);

            $mock->shouldReceive('isSessionManagementSupported')
                ->once()
                ->andReturn(false);
        });

        $this->withoutMiddleware([
            CheckUserStatusMiddleware::class,
            EnsureEmailVerificationIsSatisfied::class,
            EnsureProfileCompletionIsSatisfied::class,
            HandleInertiaRequests::class,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('app.profile.security.sessions'), [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('component', 'account/sessions')
            ->assertJsonPath('props.sessionManagementSupported', false)
            ->assertJsonCount(1, 'props.sessions')
            ->assertJsonPath('props.sessions.0.id', 'current-session')
            ->assertJsonPath('props.sessions.0.is_current', true);
    }
}
