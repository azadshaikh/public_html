<?php

namespace Modules\CMS\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Notifications\PostPublishedNotification;

class PublishScheduledPostsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cms:publish-scheduled-posts
                            {--dry-run : Show what would be published without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Publish scheduled posts, pages, categories, and tags when their published_at datetime has passed.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 Dry run mode - no changes will be made');
        }

        $this->info('Checking for scheduled content to publish...');

        // Find all CmsPost entries (posts, pages, categories, tags) that are:
        // 1. Status is 'scheduled'
        // 2. published_at datetime is less than or equal to now
        $scheduledItems = CmsPost::query()
            ->where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        /** @var Collection<int, CmsPost> $scheduledItems */
        if ($scheduledItems->isEmpty()) {
            $this->info('No scheduled content ready to publish.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d item(s) ready to publish:', $scheduledItems->count()));

        // Group by type for better output
        $groupedByType = $scheduledItems->groupBy('type');

        foreach ($groupedByType as $type => $items) {
            $this->line(sprintf('  • %s: %d item(s)', $type, $items->count()));
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('Items that would be published:');
            $this->table(
                ['ID', 'Type', 'Title', 'Scheduled For'],
                $scheduledItems->map(fn (CmsPost $item): array => [
                    $item->id,
                    $item->type,
                    Str::limit($item->title, 40),
                    $item->published_at->format('Y-m-d H:i:s'),
                ])->all()
            );

            return self::SUCCESS;
        }

        // Bulk update all scheduled items to published
        $updatedCount = CmsPost::query()
            ->whereIn('id', $scheduledItems->pluck('id'))
            ->update(['status' => 'published']);

        // Log the action
        $ids = $scheduledItems->pluck('id')->toArray();
        Log::info('Published scheduled content', [
            'count' => $updatedCount,
            'ids' => $ids,
            'types' => $groupedByType->map->count()->toArray(),
        ]);

        // Send notifications for published blog posts
        $publishedPosts = $scheduledItems->where('type', 'post');
        if ($publishedPosts->isNotEmpty()) {
            $this->notifyAdminsOfScheduledPosts($publishedPosts);
        }

        $this->info(sprintf('✓ Successfully published %d item(s).', $updatedCount));

        return self::SUCCESS;
    }

    /**
     * Notify admins about scheduled posts that were just published.
     *
     * @param  Collection<int, CmsPost>  $posts
     */
    protected function notifyAdminsOfScheduledPosts(Collection $posts): void
    {
        // Get all super users and administrators
        $admins = User::query()->whereHas('roles', function ($query): void {
            $query->whereIn('name', ['super_user', 'administrator']);
        })
            ->where('notifications_enabled', true)
            ->get();

        if ($admins->isEmpty()) {
            return;
        }

        foreach ($posts as $post) {
            // Exclude the post author from notifications
            $recipients = $admins->where('id', '!=', $post->created_by);

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new PostPublishedNotification($post, wasScheduled: true));
            }
        }

        $this->info(sprintf('  → Sent notifications to %s admin(s) for %d post(s).', $admins->count(), $posts->count()));
    }
}
