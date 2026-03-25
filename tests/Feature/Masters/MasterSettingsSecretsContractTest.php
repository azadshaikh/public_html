<?php

declare(strict_types=1);

namespace Tests\Feature\Masters;

use App\Http\Controllers\Masters\SettingsController;
use App\Http\Requests\SettingsRequest;
use App\Models\Settings;
use App\Services\SettingsCacheService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class MasterSettingsSecretsContractTest extends TestCase
{
    public function test_master_email_settings_mask_the_secret_and_expose_saved_state(): void
    {
        $this->asSuperUser();

        $secret = 'smtp-secret-for-test';

        putenv('MAIL_PASSWORD='.$secret);
        $_ENV['MAIL_PASSWORD'] = $secret;
        $_SERVER['MAIL_PASSWORD'] = $secret;

        try {
            $response = $this->makeController()->email();
            $props = $this->extractInertiaProps($response);

            $this->assertSame('', $props['settings']['email_password']);
            $this->assertTrue($props['secretState']['hasEmailPassword']);
        } finally {
            putenv('MAIL_PASSWORD');
            unset($_ENV['MAIL_PASSWORD'], $_SERVER['MAIL_PASSWORD']);
        }
    }

    public function test_master_storage_settings_mask_secrets_and_expose_saved_state(): void
    {
        $this->asSuperUser();

        putenv('FTP_PASSWORD=ftp-secret-for-test');
        putenv('AWS_ACCESS_KEY_ID=aws-access-test');
        putenv('AWS_SECRET_ACCESS_KEY=aws-secret-test');
        $_ENV['FTP_PASSWORD'] = 'ftp-secret-for-test';
        $_ENV['AWS_ACCESS_KEY_ID'] = 'aws-access-test';
        $_ENV['AWS_SECRET_ACCESS_KEY'] = 'aws-secret-test';
        $_SERVER['FTP_PASSWORD'] = 'ftp-secret-for-test';
        $_SERVER['AWS_ACCESS_KEY_ID'] = 'aws-access-test';
        $_SERVER['AWS_SECRET_ACCESS_KEY'] = 'aws-secret-test';

        try {
            $response = $this->makeController()->storage();
            $props = $this->extractInertiaProps($response);

            $this->assertSame('', $props['settings']['ftp_password']);
            $this->assertSame('', $props['settings']['access_key']);
            $this->assertSame('', $props['settings']['secret_key']);
            $this->assertTrue($props['secretState']['hasFtpPassword']);
            $this->assertTrue($props['secretState']['hasAccessKey']);
            $this->assertTrue($props['secretState']['hasSecretKey']);
        } finally {
            putenv('FTP_PASSWORD');
            putenv('AWS_ACCESS_KEY_ID');
            putenv('AWS_SECRET_ACCESS_KEY');
            unset(
                $_ENV['FTP_PASSWORD'],
                $_ENV['AWS_ACCESS_KEY_ID'],
                $_ENV['AWS_SECRET_ACCESS_KEY'],
                $_SERVER['FTP_PASSWORD'],
                $_SERVER['AWS_ACCESS_KEY_ID'],
                $_SERVER['AWS_SECRET_ACCESS_KEY'],
            );
        }
    }

    public function test_master_email_request_rules_follow_the_email_prefixed_fields(): void
    {
        $request = SettingsRequest::create('/masters/settings/email', 'PUT', [
            'meta_group' => 'email',
        ]);

        $rules = $request->rules();

        $this->assertArrayHasKey('email_driver', $rules);
        $this->assertArrayHasKey('email_password', $rules);
        $this->assertArrayHasKey('clear_email_password', $rules);
        $this->assertArrayNotHasKey('driver', $rules);
        $this->assertArrayNotHasKey('smtp_password', $rules);
    }

    public function test_resolved_secret_input_preserves_existing_secret_until_clear_is_requested(): void
    {
        $controller = $this->makeController();
        $method = new ReflectionMethod($controller, 'resolvedSecretInput');
        $method->setAccessible(true);

        putenv('MAIL_PASSWORD=existing-secret');
        $_ENV['MAIL_PASSWORD'] = 'existing-secret';
        $_SERVER['MAIL_PASSWORD'] = 'existing-secret';

        try {
            $preserveRequest = Request::create('/', 'POST', [
                'email_password' => '',
                'clear_email_password' => false,
            ]);
            $clearRequest = Request::create('/', 'POST', [
                'email_password' => '',
                'clear_email_password' => true,
            ]);

            $this->assertSame(
                'existing-secret',
                $method->invoke(
                    $controller,
                    $preserveRequest,
                    'email_password',
                    'clear_email_password',
                    'MAIL_PASSWORD',
                ),
            );

            $this->assertSame(
                '',
                $method->invoke(
                    $controller,
                    $clearRequest,
                    'email_password',
                    'clear_email_password',
                    'MAIL_PASSWORD',
                ),
            );
        } finally {
            putenv('MAIL_PASSWORD');
            unset($_ENV['MAIL_PASSWORD'], $_SERVER['MAIL_PASSWORD']);
        }
    }

    private function asSuperUser(): void
    {
        $user = new class implements Authenticatable
        {
            public function isSuperUser(): bool
            {
                return true;
            }

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return 'remember_token';
            }
        };

        $this->app['auth']->guard()->setUser($user);
    }

    private function makeController(): SettingsController
    {
        return new SettingsController(
            Mockery::mock(Settings::class),
            Mockery::mock(SettingsCacheService::class),
        );
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
