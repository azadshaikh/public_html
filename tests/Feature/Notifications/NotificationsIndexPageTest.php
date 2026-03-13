<?php

namespace Tests\Feature\Notifications;

use App\Enums\Status;
use App\Http\Middleware\CheckUserStatusMiddleware;
use App\Http\Middleware\EnsureEmailVerificationIsSatisfied;
use App\Http\Middleware\EnsureProfileCompletionIsSatisfied;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;
use Tests\TestCase;

class NotificationsIndexPageTest extends TestCase
{
    public function test_notifications_index_returns_expected_inertia_payload(): void
    {
        $user = User::factory()->make([
            'status' => Status::ACTIVE,
            'notifications_enabled' => true,
        ]);
        $user->id = 1;

        $paginator = new LengthAwarePaginator(
            items: [
                [
                    'id' => 'notif-1',
                    'type' => 'system',
                    'category' => 'system',
                    'priority' => 'high',
                    'title' => 'System alert',
                    'data' => [],
                    'read_at' => null,
                    'created_at' => '2026-03-13T10:30:00+05:30',
                    'updated_at' => '2026-03-13T10:30:00+05:30',
                    'is_read' => false,
                    'title_text' => 'System alert',
                    'message' => 'Action required',
                    'sanitized_message' => 'Action required',
                    'icon' => 'ri-settings-3-line',
                    'url' => '/admin/logs/activity-logs/1',
                    'category_label' => 'System',
                    'category_color' => 'danger',
                    'category_badge' => 'bg-danger-subtle text-danger',
                    'priority_label' => 'High',
                    'priority_badge' => 'bg-danger-subtle text-danger',
                ],
            ],
            total: 1,
            perPage: 10,
            currentPage: 1,
            options: ['path' => route('app.notifications.index', absolute: false)]
        );

        $this->mock(NotificationService::class, function (MockInterface $mock) use ($user, $paginator): void {
            $mock->shouldReceive('getForUser')
                ->once()
                ->withArgs(fn (User $authUser, array $filters): bool => $authUser->is($user)
                    && $filters === [])
                ->andReturn($paginator);

            $mock->shouldReceive('getStatsForUser')
                ->once()
                ->with($user)
                ->andReturn([
                    'total' => 1,
                    'unread' => 1,
                    'read' => 0,
                    'high_priority' => 1,
                ]);

            $mock->shouldReceive('getCategoryOptions')
                ->once()
                ->andReturn([
                    ['value' => 'system', 'label' => 'System'],
                ]);

            $mock->shouldReceive('getPriorityOptions')
                ->once()
                ->andReturn([
                    ['value' => 'high', 'label' => 'High'],
                ]);

            $mock->shouldReceive('getStatusOptions')
                ->once()
                ->andReturn([
                    ['value' => 'all', 'label' => 'All'],
                    ['value' => 'unread', 'label' => 'Unread'],
                    ['value' => 'read', 'label' => 'Read'],
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
            ->get(route('app.notifications.index'), [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('component', 'notifications/index')
            ->assertJsonPath('props.stats.total', 1)
            ->assertJsonPath('props.stats.unread', 1)
            ->assertJsonPath('props.filters.filter', 'all')
            ->assertJsonCount(1, 'props.notifications.data')
            ->assertJsonPath('props.notifications.data.0.id', 'notif-1')
            ->assertJsonPath('props.notifications.data.0.priority', 'high')
            ->assertJsonPath('props.notifications.data.0.category', 'system');
    }
}
