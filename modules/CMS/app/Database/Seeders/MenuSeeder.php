<?php

namespace Modules\CMS\Database\Seeders;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Menu;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $primaryMenu = Menu::query()->create([
            'type' => Menu::TYPE_CONTAINER,
            'name' => 'Primary Navigation',
            'slug' => 'primary-navigation',
            'location' => 'primary',
            'description' => 'Main navigation menu for the website',
            'is_active' => true,
        ]);

        $footerMenu = Menu::query()->create([
            'type' => Menu::TYPE_CONTAINER,
            'name' => 'Footer Menu',
            'slug' => 'footer-menu',
            'location' => 'footer',
            'description' => 'Footer navigation links',
            'is_active' => true,
        ]);

        $this->createPrimaryMenuItems($primaryMenu);
        $this->createFooterMenuItems($footerMenu);
    }

    /**
     * Create primary menu items
     */
    private function createPrimaryMenuItems(Menu $menu): void
    {
        Menu::query()->create([
            'type' => Menu::TYPE_HOME,
            'parent_id' => $menu->id,
            'name' => 'Home',
            'title' => 'Home',
            'url' => '/',
            'target' => '_self',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $aboutItem = Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'About',
            'title' => 'About',
            'url' => '/about',
            'target' => '_self',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $aboutItem->id,
            'name' => 'Our Story',
            'title' => 'Our Story',
            'url' => '/about/story',
            'target' => '_self',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $aboutItem->id,
            'name' => 'Our Team',
            'title' => 'Our Team',
            'url' => '/about/team',
            'target' => '_self',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $aboutItem->id,
            'name' => 'Careers',
            'title' => 'Careers',
            'url' => '/about/careers',
            'target' => '_self',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $servicesItem = Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'Services',
            'title' => 'Services',
            'url' => '/services',
            'target' => '_self',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $servicesItem->id,
            'name' => 'Web Development',
            'title' => 'Web Development',
            'url' => '/services/web-development',
            'target' => '_self',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $servicesItem->id,
            'name' => 'Mobile Apps',
            'title' => 'Mobile Apps',
            'url' => '/services/mobile-apps',
            'target' => '_self',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $servicesItem->id,
            'name' => 'Consulting',
            'title' => 'Consulting',
            'url' => '/services/consulting',
            'target' => '_self',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_ARCHIVE,
            'parent_id' => $menu->id,
            'name' => 'Blog',
            'title' => 'Blog',
            'url' => '/blog',
            'target' => '_self',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        /** @var Collection<int, CmsPost> $pages */
        $pages = CmsPost::query()->published()->limit(2)->get();
        $sortOrder = 5;

        foreach ($pages as $page) {
            Menu::query()->create([
                'type' => Menu::TYPE_PAGE,
                'parent_id' => $menu->id,
                'name' => $page->post_title ?? 'Untitled Page',
                'title' => $page->post_title ?? 'Untitled Page',
                'object_id' => $page->id,
                'target' => '_self',
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
            $sortOrder++;
        }

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'Contact',
            'title' => 'Contact',
            'url' => '/contact',
            'target' => '_self',
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }

    /**
     * Create footer menu items
     */
    private function createFooterMenuItems(Menu $menu): void
    {
        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'About Us',
            'title' => 'About Us',
            'url' => '/about',
            'target' => '_self',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'Privacy Policy',
            'title' => 'Privacy Policy',
            'url' => '/privacy-policy',
            'target' => '_self',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'Terms of Service',
            'title' => 'Terms of Service',
            'url' => '/terms-of-service',
            'target' => '_self',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'Support',
            'title' => 'Support',
            'url' => '/support',
            'target' => '_self',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        Menu::query()->create([
            'type' => Menu::TYPE_CUSTOM,
            'parent_id' => $menu->id,
            'name' => 'Laravel Documentation',
            'title' => 'Laravel Documentation',
            'url' => 'https://laravel.com/docs',
            'target' => '_blank',
            'css_classes' => 'external-link',
            'sort_order' => 5,
            'is_active' => true,
        ]);
    }
}
