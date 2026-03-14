<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\Status;
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
            ->assertSessionHas('success');

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
