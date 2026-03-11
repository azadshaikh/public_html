<?php

namespace Modules\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Cms\Models\CmsPage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the CMS module tables.
     */
    public function run(): void
    {
        if (CmsPage::query()->exists()) {
            return;
        }

        collect([
            [
                'title' => 'Home',
                'slug' => 'home',
                'summary' => 'The main landing page for the product and marketing site.',
                'body' => "# Welcome\n\nHighlight the core product value, a featured CTA, and a quick overview of what the team ships.",
                'status' => 'published',
                'published_at' => now()->subDays(10),
                'is_featured' => true,
            ],
            [
                'title' => 'About',
                'slug' => 'about',
                'summary' => 'Company story, positioning, and team context.',
                'body' => "# About us\n\nShare the company story, mission, and why this product exists.",
                'status' => 'published',
                'published_at' => now()->subDays(6),
                'is_featured' => false,
            ],
            [
                'title' => 'Spring launch checklist',
                'slug' => 'spring-launch-checklist',
                'summary' => 'Draft planning page for the next campaign launch.',
                'body' => "# Launch checklist\n\nOutline launch tasks, owners, review dates, and the publish sequence.",
                'status' => 'draft',
                'published_at' => null,
                'is_featured' => false,
            ],
        ])->each(fn (array $page) => CmsPage::query()->create($page));
    }
}
