<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Exceptions\AccountBannedException;
use App\Exceptions\AccountSuspendedException;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Services\ActivityLogger;
use App\Services\SocialLoginService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SocialLoginControllerTest extends TestCase
{
    public function test_banned_social_login_returns_generic_banned_message(): void
    {
        $response = $this->runFailingSocialLoginCallback(
            new AccountBannedException(__('auth.account_banned'))
        );

        $this->assertSame(url('/'), $response->getTargetUrl());
        $this->assertSame(__('auth.account_banned'), session('error'));
    }

    public function test_suspended_social_login_returns_generic_suspended_message(): void
    {
        $response = $this->runFailingSocialLoginCallback(
            new AccountSuspendedException(__('auth.account_suspended'))
        );

        $this->assertSame(url('/'), $response->getTargetUrl());
        $this->assertSame(__('auth.account_suspended'), session('error'));
    }

    private function runFailingSocialLoginCallback(\Exception $exception)
    {
        config([
            'services.social_auth.enabled' => true,
            'services.google.enabled' => true,
        ]);

        session()->start();

        $socialUser = Mockery::mock(SocialiteUser::class);
        $socialUser->shouldReceive('getEmail')->andReturn('social@example.com');
        $socialUser->shouldReceive('getId')->andReturn('provider-user-1');

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($socialUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);

        $socialLoginService = Mockery::mock(SocialLoginService::class);
        $socialLoginService->shouldReceive('findOrCreateUser')
            ->once()
            ->with($socialUser, 'google')
            ->andThrow($exception);

        $controller = new class($socialLoginService, Mockery::mock(UserService::class), Mockery::mock(ActivityLogger::class)) extends SocialLoginController
        {
            protected function recordSocialLoginFailure(
                Request $request,
                string $provider,
                ?string $email,
                string $reason,
                ?int $userId = null,
                mixed $providerUserId = null
            ): void {}

            protected function resolveUserIdByEmail(?string $email): ?int
            {
                return null;
            }
        };

        return $controller->handleProviderCallback(
            Request::create('/login/google/callback', 'GET'),
            'google'
        );
    }
}
