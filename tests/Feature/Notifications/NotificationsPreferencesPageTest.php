<?php

namespace Tests\Feature\Notifications;

use App\Enums\Status;
use App\Http\Middleware\CheckUserStatusMiddleware;
use App\Http\Middleware\EnsureEmailVerificationIsSatisfied;
use App\Http\Middleware\EnsureProfileCompletionIsSatisfied;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\NotificationService;
use Mockery\MockInterface;
use Tests\TestCase;

class NotificationsPreferencesPageTest extends TestCase
{
    public function test_notifications_preferences_returns_expected_inertia_payload(): void
    {
        $user = User::factory()->make([
            'status' => Status::ACTIVE,
            'notifications_enabled' => false,
        ]);
        $user->id = 1;

        $this->mock(NotificationService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('getPreferencesForUser')
                ->once()
                ->with($user)
                ->andReturn([
                    'enabled' => true,
                    'categories' => [
                        'system' => true,
                        'website' => true,
                        'user' => false,
                        'cms' => true,
                        'broadcast' => false,
                    ],
                    'priorities' => [
                        'high' => true,
                        'medium' => true,
                        'low' => false,
                    ],
                ]);
        });

        $this->withoutMiddleware([
            CheckUserStatusMiddleware::class,
            EnsureEmailVerificationIsSatisfied::class,
            EnsureProfileCompletionIsSatisfied::class,
            HandleInertiaRequests::class,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('app.notifications.preferences'), [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('component', 'notifications/preferences')
            ->assertJsonPath('props.notificationsEnabled', false)
            ->assertJsonPath('props.preferences.categories.system', true)
            ->assertJsonPath('props.preferences.categories.user', false)
            ->assertJsonPath('props.preferences.priorities.high', true)
            ->assertJsonPath('props.preferences.priorities.low', false)
            ->assertJsonCount(5, 'props.categoryPreferences')
            ->assertJsonCount(3, 'props.priorityPreferences')
            ->assertJsonPath('props.categoryPreferences.0.value', 'system')
            ->assertJsonPath('props.priorityPreferences.0.value', 'high');
    }
}
