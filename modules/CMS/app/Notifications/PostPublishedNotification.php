<?php

declare(strict_types=1);

namespace Modules\CMS\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\CMS\Models\CmsPost;

/**
 * PostPublishedNotification
 *
 * Notification sent to admins when a blog post is published.
 */
class PostPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly CmsPost $post,
        private readonly bool $wasScheduled = false
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $title = $this->wasScheduled
            ? 'Scheduled post published: '.$this->post->title
            : 'New post published: '.$this->post->title;

        $text = $this->wasScheduled
            ? sprintf('The scheduled blog post "%s" has been automatically published.', $this->post->title)
            : sprintf('A new blog post "%s" has been published by %s.', $this->post->title, $this->post->createdBy?->name);

        return [
            'title' => $title,
            'text' => $text,
            'icon' => 'ri-article-line',
            'url' => route('cms.posts.edit', $this->post->id),
            'url_backend' => route('cms.posts.edit', $this->post->id),
            'category' => NotificationCategory::Cms->value,
            'priority' => NotificationPriority::Medium->value,
            'module' => 'CMS',
            'type' => 'post_published',
            'post_id' => $this->post->id,
            'post_title' => $this->post->title,
            'was_scheduled' => $this->wasScheduled,
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
