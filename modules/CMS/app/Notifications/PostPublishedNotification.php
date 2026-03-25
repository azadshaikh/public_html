<?php

declare(strict_types=1);

namespace Modules\CMS\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Notifications\Support\NotificationPayload;
use Illuminate\Notifications\Notification;
use Modules\CMS\Models\CmsPost;

/**
 * PostPublishedNotification
 *
 * Notification sent to admins when a blog post is published.
 */
class PostPublishedNotification extends Notification
{
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

        $publicUrl = $this->publicPostUrl();

        $payload = NotificationPayload::make($title, $text)
            ->icon('ri-article-line')
            ->category(NotificationCategory::Cms)
            ->priority(NotificationPriority::Medium)
            ->module('CMS')
            ->type('post_published');

        if ($publicUrl !== null) {
            $payload = $payload
                ->frontendLink($publicUrl, 'Read post')
                ->extra([
                    'url' => $publicUrl,
                    'public_url' => $publicUrl,
                ]);
        }

        if ($this->canEditPosts($notifiable)) {
            $payload = $payload->backendLink(route('cms.posts.edit', $this->post->id), 'Edit post');
        }

        return $payload
            ->extra([
                'post_id' => $this->post->id,
                'post_title' => $this->post->title,
                'was_scheduled' => $this->wasScheduled,
            ])
            ->toArray();
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

    private function publicPostUrl(): ?string
    {
        $permalink = trim((string) $this->post->permalink_url);

        if ($permalink === '' || $permalink === '#') {
            $fallbackSlug = trim((string) $this->post->slug);

            if ($fallbackSlug === '') {
                return null;
            }

            return url('/'.$fallbackSlug);
        }

        return url($permalink);
    }

    private function canEditPosts(object $notifiable): bool
    {
        if (! method_exists($notifiable, 'can')) {
            return false;
        }

        return (bool) $notifiable->can('edit_posts');
    }
}
