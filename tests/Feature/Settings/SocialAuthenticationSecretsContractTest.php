<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Auth;
use Inertia\Response as InertiaResponse;
use ReflectionClass;
use Tests\TestCase;

class SocialAuthenticationSecretsContractTest extends TestCase
{
    public function test_social_authentication_settings_do_not_echo_raw_client_secrets(): void
    {
        $googleClientSecret = 'google-client-secret-for-test';
        $githubClientSecret = 'github-client-secret-for-test';

        putenv('GOOGLE_CLIENT_SECRET='.$googleClientSecret);
        putenv('GITHUB_CLIENT_SECRET='.$githubClientSecret);
        $_ENV['GOOGLE_CLIENT_SECRET'] = $googleClientSecret;
        $_ENV['GITHUB_CLIENT_SECRET'] = $githubClientSecret;
        $_SERVER['GOOGLE_CLIENT_SECRET'] = $googleClientSecret;
        $_SERVER['GITHUB_CLIENT_SECRET'] = $githubClientSecret;

        Auth::shouldReceive('user')
            ->andReturn(new class
            {
                public function can(string $ability): bool
                {
                    return true;
                }
            });

        try {
            $response = app(SettingsController::class)->socialAuthentication();
            $props = $this->extractInertiaProps($response);

            $this->assertNotSame($googleClientSecret, $props['settings']['google_client_secret']);
            $this->assertNotSame($githubClientSecret, $props['settings']['github_client_secret']);
        } finally {
            putenv('GOOGLE_CLIENT_SECRET');
            putenv('GITHUB_CLIENT_SECRET');
            unset($_ENV['GOOGLE_CLIENT_SECRET'], $_ENV['GITHUB_CLIENT_SECRET']);
            unset($_SERVER['GOOGLE_CLIENT_SECRET'], $_SERVER['GITHUB_CLIENT_SECRET']);
        }
    }

    private function extractInertiaProps(InertiaResponse $response): array
    {
        $reflection = new ReflectionClass($response);
        $property = $reflection->getProperty('props');
        $property->setAccessible(true);

        /** @var array<string, mixed> $props */
        $props = $property->getValue($response);

        return $props;
    }
}
