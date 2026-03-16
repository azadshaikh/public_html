<?php

namespace Modules\CMS\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\CMS\Models\CmsPost;
use stdClass;

class CmsDefaultContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var stdClass|null $user */
        $user = DB::table('users')->find(2);

        if (! $user) {
            $this->command->warn('User with ID 2 not found. Using earliest available user instead.');
            $user = DB::table('users')->orderBy('id')->first();
        }

        if (! $user) {
            $this->command->warn('No users found. Please create at least one user before running this seeder.');

            return;
        }

        $userId = (int) $user->id;

        $this->command->info('Creating default CMS content...');

        $categories = $this->createCategories($userId);
        $this->command->info('Created '.count($categories).' categories');

        $tags = $this->createTags($userId);
        $this->command->info('Created '.count($tags).' tags');

        $posts = $this->createPosts($userId, $categories, $tags);
        $this->command->info('Created '.count($posts).' posts');

        $pages = $this->createPages($userId);
        $this->command->info('Created '.count($pages).' pages');

        $this->command->info('✓ Default CMS content created successfully!');
    }

    /**
     * Generate a random published_at timestamp within the last 7 days
     */
    private function randomPublishedAt(): Carbon
    {
        return now()
            ->subDays(random_int(0, 7))
            ->subHours(random_int(0, 23))
            ->subMinutes(random_int(0, 59));
    }

    /**
     * Create default categories
     */
    private function createCategories(int $userId): array
    {
        $categories = [];

        $categoryData = [
            [
                'title' => 'Company News',
                'slug' => 'company-news',
                'excerpt' => 'Milestones, announcements, and organizational highlights ready for you to personalize.',
                'content' => 'Use this category to share company milestones, leadership updates, and important announcements that matter to your audience.',
            ],
            [
                'title' => 'Guides & Tutorials',
                'slug' => 'guides-tutorials',
                'excerpt' => 'Step-by-step resources and how-to articles to help your users succeed.',
                'content' => 'Publish in-depth walkthroughs, onboarding resources, and educational material that empowers visitors to get the most value from your product or service.',
            ],
            [
                'title' => 'Product Updates',
                'slug' => 'product-updates',
                'excerpt' => 'Release notes, feature launches, and changelog highlights for your platform.',
                'content' => 'Keep customers informed with a transparent product roadmap, new feature announcements, and version release notes.',
            ],
        ];

        foreach ($categoryData as $data) {
            $category = CmsPost::query()->create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'type' => 'category',
                'excerpt' => $data['excerpt'],
                'content' => $data['content'],
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => $this->randomPublishedAt(),
                'author_id' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $categories[] = $category;
        }

        return $categories;
    }

    /**
     * Create default tags
     */
    private function createTags(int $userId): array
    {
        $tags = [];

        $tagData = [
            ['title' => 'Announcement', 'slug' => 'announcement', 'content' => 'Updates you want users to notice right away.'],
            ['title' => 'How-To', 'slug' => 'how-to', 'content' => 'Practical, actionable instructions for your audience.'],
            ['title' => 'Product Update', 'slug' => 'product-update', 'content' => 'Feature releases and iteration notes.'],
            ['title' => 'Customer Story', 'slug' => 'customer-story', 'content' => 'Case studies and community highlights.'],
            ['title' => 'Resource', 'slug' => 'resource', 'content' => 'Downloadable assets, templates, and helpful tools.'],
        ];

        foreach ($tagData as $data) {
            $tags[] = CmsPost::query()->create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'type' => 'tag',
                'content' => $data['content'],
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => $this->randomPublishedAt(),
                'author_id' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        return $tags;
    }

    /**
     * Create default posts
     */
    private function createPosts(int $userId, array $categories, array $tags): array
    {
        $posts = [];

        $postData = [
            [
                'title' => 'Welcome to Your New CMS',
                'slug' => 'welcome-to-your-new-cms',
                'excerpt' => 'A ready-made welcome post to help you introduce your brand and set expectations.',
                'content' => '<h2>Introduce Your Brand</h2><p>This starter post is here to help you launch quickly. Replace it with a short welcome message that explains who you are, the value you provide, and what visitors can expect to find here.</p><h3>Tips for Customizing</h3><ul><li>Share your mission and the problem you solve.</li><li>Point to key pages like product features or pricing.</li><li>Add a call-to-action for new visitors.</li></ul><p>Happy publishing!</p>',
                'categories' => ['Company News'],
                'tags' => ['Announcement'],
            ],
            [
                'title' => 'Getting Started Guide (Template)',
                'slug' => 'getting-started-guide-template',
                'excerpt' => 'Help customers onboard with a customizable guide that covers the basics.',
                'content' => '<h2>Kickstart Your Onboarding</h2><p>Use this template to walk new customers through their first steps. Highlight the actions they should take immediately after signing up and link to supporting documentation or videos.</p><h3>Suggested Sections</h3><ol><li>Welcome message from your team.</li><li>Step-by-step instructions for the core workflow.</li><li>Where to get support and additional resources.</li></ol><p>Swap in your product screenshots and brand voice to make it feel complete.</p>',
                'categories' => ['Guides & Tutorials'],
                'tags' => ['How-To', 'Resource'],
            ],
            [
                'title' => 'Product Update Template',
                'slug' => 'product-update-template',
                'excerpt' => 'Share what is new, improved, or fixed with your platform in a structured format.',
                'content' => "<h2>Headline for Your Update</h2><p>Quickly summarize the highlight of this release. Keep it concise and link to deeper documentation when needed.</p><h3>What's New</h3><ul><li>Describe new features and why they matter.</li><li>Celebrate customer feedback that influenced the change.</li></ul><h3>Improvements & Fixes</h3><p>Offer a short list of enhancements, performance improvements, or bug fixes to keep transparency high.</p><p>Close with a thank you message or a prompt for users to share feedback.</p>",
                'categories' => ['Product Updates'],
                'tags' => ['Product Update'],
            ],
        ];

        foreach ($postData as $data) {
            $categoryIds = collect($categories)->whereIn('title', $data['categories'])->pluck('id');
            $primaryCategoryId = $categoryIds->first();

            /** @var CmsPost $post */
            $post = CmsPost::query()->create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'type' => 'post',
                'excerpt' => $data['excerpt'],
                'content' => $data['content'],
                'status' => 'published',
                'visibility' => 'public',
                'published_at' => $this->randomPublishedAt(),
                'category_id' => $primaryCategoryId,
                'author_id' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($categoryIds->isNotEmpty()) {
                $post->terms()->attach($categoryIds, ['term_type' => 'category']);
            }

            $tagIds = collect($tags)->whereIn('title', $data['tags'])->pluck('id');
            if ($tagIds->isNotEmpty()) {
                $post->terms()->attach($tagIds, ['term_type' => 'tag']);
            }

            $posts[] = $post;
        }

        return $posts;
    }

    /**
     * Create default pages
     */
    private function createPages(int $userId): array
    {
        $pages = [];

        $pageData = [
            [
                'title' => 'Homepage',
                'slug' => 'home',
                'content' => '<h1>Welcome to Your CMS</h1><p>This is your default homepage. Replace this content with a short overview of your product or brand, highlight the value you provide, and guide visitors to take the next step.</p>',
                'status' => 'published',
            ],
            [
                'title' => 'Contact Us',
                'slug' => 'contact-us',
                'content' => '<h1>Get in Touch</h1><p>Let customers know how they can reach your team. Include a contact form, support email, or calendar link.</p>',
                'status' => 'published',
            ],
            [
                'title' => 'About',
                'slug' => 'about',
                'content' => '<h1>Tell Your Story</h1><p>Use this page to share your mission, values, and the people behind your product. Add timelines, images, or videos to build trust.</p>',
                'status' => 'published',
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => '<h1>Privacy Policy</h1><p>Outline how you collect, use, and store visitor data. Update this template to reflect your compliance requirements.</p>',
                'status' => 'published',
            ],
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'content' => '<h1>Terms of Service</h1><p>Define the rules for using your platform. Include information about acceptable use, billing, and account management.</p>',
                'status' => 'draft',
            ],
        ];

        foreach ($pageData as $data) {
            $pages[] = CmsPost::query()->create([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'type' => 'page',
                'content' => $data['content'],
                'status' => $data['status'],
                'visibility' => 'public',
                'published_at' => $data['status'] === 'published' ? $this->randomPublishedAt() : null,
                'author_id' => $userId,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        return $pages;
    }
}
