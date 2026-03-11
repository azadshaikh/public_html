<?php

namespace Modules\Todos\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Modules\Todos\Models\TodoTask;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the todos module tables.
     */
    public function run(): void
    {
        if (! Schema::hasTable('todo_tasks') || TodoTask::query()->exists()) {
            return;
        }

        collect([
            [
                'title' => 'Plan launch checklist',
                'slug' => 'plan-launch-checklist',
                'details' => 'Define rollout tasks, assign owners, and confirm go-live sequencing.',
                'status' => 'in_progress',
                'priority' => 'high',
                'owner' => 'Operations',
                'due_date' => now()->addDays(4)->toDateString(),
                'is_blocked' => false,
            ],
            [
                'title' => 'Review chatbot prompts',
                'slug' => 'review-chatbot-prompts',
                'details' => 'Audit prompt tone, fallback responses, and escalation wording.',
                'status' => 'backlog',
                'priority' => 'medium',
                'owner' => 'Support',
                'due_date' => now()->addWeek()->toDateString(),
                'is_blocked' => false,
            ],
            [
                'title' => 'Publish about page',
                'slug' => 'publish-about-page',
                'details' => 'Finalize content edits and move the CMS draft into the published state.',
                'status' => 'done',
                'priority' => 'low',
                'owner' => 'Marketing',
                'due_date' => now()->subDay()->toDateString(),
                'is_blocked' => false,
            ],
            [
                'title' => 'Resolve launch blocker',
                'slug' => 'resolve-launch-blocker',
                'details' => 'Confirm asset approvals and unblock the release checklist.',
                'status' => 'in_progress',
                'priority' => 'high',
                'owner' => 'Product',
                'due_date' => now()->addDays(2)->toDateString(),
                'is_blocked' => true,
            ],
        ])->each(fn (array $task) => TodoTask::query()->create($task));
    }
}
