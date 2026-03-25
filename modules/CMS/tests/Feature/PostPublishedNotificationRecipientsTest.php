<?php

declare(strict_types=1);

namespace Modules\CMS\Tests\Feature;

use App\Enums\Status;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\CMS\Enums\CmsPostType;
use Modules\CMS\Jobs\SendPostPublishedNotificationsJob;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Notifications\PostPublishedNotification;
use Tests\TestCase;

class PostPublishedNotificationRecipientsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_published_post_queues_notification_job(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Permission::query()->firstOrCreate(
            ['name' => 'edit_posts', 'guard_name' => 'web'],
            [
                'display_name' => 'Edit Posts',
                'group' => 'posts',
                'module_slug' => 'cms',
            ],
        );

        $author = $this->createUser('Author');
        $admin = $this->createUser('Admin');
        $superUser = $this->createUser('Super', notificationsEnabled: false);
        $reader = $this->createUser('Reader');
        $inactive = $this->createUser('Inactive', notificationsEnabled: true, status: Status::INACTIVE);

        $admin->assignRole(Role::findByName('administrator', 'web'));
        $admin->givePermissionTo('edit_posts');
        Queue::fake();

        $post = $this->createPost($author, status: 'published');
        $post->update([
            'title' => $post->title,
            'status' => 'published',
            'published_at' => now(),
        ]);

        Queue::assertPushed(SendPostPublishedNotificationsJob::class, function (SendPostPublishedNotificationsJob $job) use ($post): bool {
            return $job->postId === $post->id;
        });
    }

    public function test_scheduled_publish_command_queues_notification_job(): void
    {
        $author = $this->createUser('Author');
        $superUser = $this->createUser('Super', notificationsEnabled: false);
        $reader = $this->createUser('Reader');
        $inactive = $this->createUser('Inactive', notificationsEnabled: true, status: Status::INACTIVE);

        $post = $this->createPost($author, status: 'scheduled');
        $post->forceFill([
            'published_at' => now()->subMinute(),
        ])->save();

        Queue::fake();

        $this->artisan('cms:publish-scheduled-posts')->assertSuccessful();

        self::assertSame('published', (string) $post->fresh()->status);
        Queue::assertPushed(SendPostPublishedNotificationsJob::class, 1);
    }

    public function test_notification_job_sends_to_all_active_users_including_super_user(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        Permission::query()->firstOrCreate(
            ['name' => 'edit_posts', 'guard_name' => 'web'],
            [
                'display_name' => 'Edit Posts',
                'group' => 'posts',
                'module_slug' => 'cms',
            ],
        );

        $author = $this->createUser('Author');
        $admin = $this->createUser('Admin');
        $superUser = $this->createUser('Super', notificationsEnabled: false);
        $reader = $this->createUser('Reader');
        $inactive = $this->createUser('Inactive', notificationsEnabled: true, status: Status::INACTIVE);

        $admin->assignRole(Role::findByName('administrator', 'web'));
        $admin->givePermissionTo('edit_posts');

        $post = $this->createPost($author, status: 'published');
        $post->forceFill(['published_at' => now()])->save();

        Notification::fake();

        (new SendPostPublishedNotificationsJob($post->id, false))->handle();

        Notification::assertSentTo($author, PostPublishedNotification::class);
        Notification::assertSentTo($superUser, PostPublishedNotification::class);
        Notification::assertSentTo($admin, PostPublishedNotification::class, function (PostPublishedNotification $notification, array $channels, object $notifiable): bool {
            $payload = $notification->toDatabase($notifiable);

            self::assertSame('CMS', $payload['module']);
            self::assertSame('post_published', $payload['type']);
            self::assertArrayHasKey('url_backend', $payload);
            self::assertArrayHasKey('url_frontend', $payload);

            return true;
        });
        Notification::assertSentTo($reader, PostPublishedNotification::class, function (PostPublishedNotification $notification, array $channels, object $notifiable): bool {
            $payload = $notification->toDatabase($notifiable);

            self::assertSame('CMS', $payload['module']);
            self::assertSame('post_published', $payload['type']);
            self::assertArrayNotHasKey('url_backend', $payload);
            self::assertArrayHasKey('url_frontend', $payload);

            return true;
        });
        Notification::assertNotSentTo($inactive, PostPublishedNotification::class);
    }

    private function createUser(string $name, bool $notificationsEnabled = true, Status $status = Status::ACTIVE): User
    {
        return User::factory()->create([
            'first_name' => $name,
            'last_name' => 'User',
            'status' => $status,
            'email_verified_at' => now(),
            'notifications_enabled' => $notificationsEnabled,
        ]);
    }

    private function createPost(User $author, string $status): CmsPost
    {
        return CmsPost::query()->create([
            'title' => 'Published Post '.Str::random(6),
            'slug' => 'published-post-'.Str::lower(Str::random(10)),
            'type' => CmsPostType::POST->value,
            'status' => $status,
            'visibility' => 'public',
            'author_id' => $author->id,
            'created_by' => $author->id,
            'updated_by' => $author->id,
            'content' => '<p>Post content</p>',
            'excerpt' => 'Post excerpt',
        ]);
    }
}
