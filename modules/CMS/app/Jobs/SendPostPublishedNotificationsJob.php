<?php

declare(strict_types=1);

namespace Modules\CMS\Jobs;

use App\Enums\Status;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Notifications\PostPublishedNotification;

class SendPostPublishedNotificationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $postId,
        public readonly bool $wasScheduled = false,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $post = CmsPost::query()
            ->with('createdBy')
            ->find($this->postId);

        if (! $post instanceof CmsPost || $post->type !== 'post' || $post->status !== 'published') {
            return;
        }

        User::query()
            ->where('status', Status::ACTIVE)
            ->chunkById(200, function ($users) use ($post): void {
                if ($users->isEmpty()) {
                    return;
                }

                Notification::sendNow($users, new PostPublishedNotification($post, $this->wasScheduled));
            });
    }
}
