<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\Status;
use App\Http\Middleware\CheckUserStatusMiddleware;
use App\Http\Middleware\EnsureEmailVerificationIsSatisfied;
use App\Http\Middleware\EnsureProfileCompletionIsSatisfied;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_count_returns_only_the_authenticated_users_unread_notifications(): void
    {
        $user = $this->createEligibleUser();
        $otherUser = $this->createEligibleUser('Other');

        $this->createNotification($user);
        $this->createNotification($user, ['read_at' => now()]);
        $this->createNotification($otherUser);

        $this->actingAs($user)
            ->getJson(route('app.notifications.unread-count'))
            ->assertOk()
            ->assertJson([
                'count' => 1,
            ]);
    }

    public function test_mark_read_marks_an_owned_notification_as_read(): void
    {
        $user = $this->createEligibleUser();
        $notification = $this->createNotification($user);

        $this->actingAs($user)
            ->postJson(route('app.notifications.mark-read', $notification))
            ->assertOk()
            ->assertJson([
                'status' => 1,
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_read_rejects_notifications_owned_by_another_user(): void
    {
        $user = $this->createEligibleUser();
        $otherUser = $this->createEligibleUser('Other');
        $notification = $this->createNotification($otherUser);

        $this->actingAs($user)
            ->postJson(route('app.notifications.mark-read', $notification))
            ->assertForbidden()
            ->assertJson([
                'error' => 'Unauthorized',
            ]);
    }

    public function test_dropdown_returns_recent_notifications_and_unread_count_for_the_authenticated_user(): void
    {
        $user = $this->createEligibleUser();
        $otherUser = $this->createEligibleUser('Other');

        $olderUnread = $this->createNotification($user, [
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        $newerRead = $this->createNotification($user, [
            'read_at' => now()->subMinutes(5),
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
        $this->createNotification($otherUser, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('app.notifications.dropdown'))
            ->assertOk()
            ->assertJson([
                'count' => 1,
            ])
            ->assertJsonPath('notifications.0.title_text', $newerRead->title_text)
            ->assertJsonPath('notifications.0.sanitized_message', $newerRead->sanitized_message)
            ->assertJsonPath('notifications.0.category_label', $newerRead->category_label)
            ->assertJsonPath('notifications.0.priority', $newerRead->priority->value)
            ->assertJsonPath('notifications.0.priority_label', $newerRead->priority_label)
            ->assertJsonPath('notifications.0.id', $newerRead->id)
            ->assertJsonPath('notifications.1.id', $olderUnread->id);
    }

    public function test_show_marks_notification_as_read_without_redirecting_to_target_url(): void
    {
        $user = $this->createEligibleUser();
        $notification = $this->createNotification($user, [
            'data' => [
                'message' => 'Action required',
                'url_backend' => '/admin/websites/1',
            ],
        ]);

        $this->withoutMiddleware([
            CheckUserStatusMiddleware::class,
            EnsureEmailVerificationIsSatisfied::class,
            EnsureProfileCompletionIsSatisfied::class,
        ]);

        $this->actingAs($user)
            ->get(route('app.notifications.show', $notification))
            ->assertOk();

        $notification->refresh();

        $this->assertNotNull($notification->read_at);
    }

    public function test_show_json_returns_detail_payload_with_content_links(): void
    {
        $user = $this->createEligibleUser();
        $notification = $this->createNotification($user, [
            'data' => [
                'text' => '<p>Action required</p>',
                'url_backend' => '/admin/websites/1',
                'url_frontend' => 'https://example.com',
            ],
        ]);

        $this->actingAs($user)
            ->getJson(route('app.notifications.show', $notification))
            ->assertOk()
            ->assertJsonPath('notification.id', $notification->id)
            ->assertJsonPath('notification.content_links.0.href', '/admin/websites/1')
            ->assertJsonPath('notification.content_links.1.href', 'https://example.com');
    }

    public function test_toggle_enabled_updates_the_authenticated_users_notification_flag(): void
    {
        $user = $this->createEligibleUser();

        $this->actingAs($user)
            ->postJson(route('app.notifications.toggle-enabled'), [
                'enabled' => false,
            ])
            ->assertOk()
            ->assertJson([
                'status' => 1,
            ]);

        $this->assertFalse((bool) $user->fresh()->notifications_enabled);
    }

    public function test_delete_multiple_redirects_for_inertia_requests_instead_of_returning_plain_json(): void
    {
        $user = $this->createEligibleUser();
        $firstNotification = $this->createNotification($user);
        $secondNotification = $this->createNotification($user);

        $this->actingAs($user)
            ->from(route('app.notifications.index'))
            ->post(route('app.notifications.delete-multiple'), [
                'ids' => [$firstNotification->id, $secondNotification->id],
            ], [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertRedirect(route('app.notifications.index'))
            ->assertSessionHas('status', '2 notification(s) deleted.');

        $this->assertSoftDeleted('notifications', [
            'id' => $firstNotification->id,
        ]);
        $this->assertSoftDeleted('notifications', [
            'id' => $secondNotification->id,
        ]);
    }

    public function test_delete_all_read_redirects_for_inertia_requests_instead_of_returning_plain_json(): void
    {
        $user = $this->createEligibleUser();
        $readNotification = $this->createNotification($user, [
            'read_at' => now(),
        ]);
        $unreadNotification = $this->createNotification($user);

        $this->actingAs($user)
            ->from(route('app.notifications.index'))
            ->delete(route('app.notifications.delete-all-read'), [], [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertRedirect(route('app.notifications.index'))
            ->assertSessionHas('status', __('notifications.read_notifications_deleted'));

        $this->assertSoftDeleted('notifications', [
            'id' => $readNotification->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $unreadNotification->id,
        ]);
    }

    public function test_destroy_redirects_for_inertia_requests_instead_of_returning_plain_json(): void
    {
        $user = $this->createEligibleUser();
        $notification = $this->createNotification($user);

        $this->actingAs($user)
            ->delete(route('app.notifications.destroy', $notification), [], [
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertRedirect(route('app.notifications.index'))
            ->assertSessionHas('status', __('notifications.notification_deleted'));

        $this->assertSoftDeleted('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_notification_preferences_update_persists_checked_and_unchecked_values(): void
    {
        $user = $this->createEligibleUser();

        $this->actingAs($user)
            ->put(route('app.notifications.preferences.update'), [
                'notifications_enabled' => '1',
                'preferences' => [
                    'categories' => [
                        NotificationCategory::System->value => '1',
                        NotificationCategory::User->value => '1',
                    ],
                    'priorities' => [
                        NotificationPriority::High->value => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('app.notifications.preferences'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertTrue((bool) $user->notifications_enabled);
        $this->assertTrue(data_get($user->notification_preferences, 'categories.system'));
        $this->assertTrue(data_get($user->notification_preferences, 'categories.user'));
        $this->assertFalse(data_get($user->notification_preferences, 'categories.website'));
        $this->assertTrue(data_get($user->notification_preferences, 'priorities.high'));
        $this->assertFalse(data_get($user->notification_preferences, 'priorities.medium'));
    }

    private function createEligibleUser(string $firstName = 'Notify'): User
    {
        return User::factory()->create([
            'first_name' => $firstName,
            'last_name' => 'User',
            'name' => $firstName.' User',
            'status' => Status::ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createNotification(User $user, array $overrides = []): Notification
    {
        return Notification::query()->create(array_replace([
            'id' => (string) Str::uuid(),
            'type' => 'system',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'message' => 'Action required',
            ],
            'category' => NotificationCategory::System->value,
            'priority' => NotificationPriority::High->value,
            'title' => 'System alert',
            'read_at' => null,
        ], $overrides));
    }
}
