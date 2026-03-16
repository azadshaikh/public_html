<?php

namespace Modules\CMS\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Modules\CMS\Models\CmsPost;

class AdminBar extends Component
{
    public function canShowAdminBar(mixed $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user || ! method_exists($user, 'can')) {
            return false;
        }

        if ($user->can('edit_pages')) {
            return true;
        }

        return (bool) $user->can('edit_posts');
    }

    public function render(): View
    {
        $canShowAdminBar = $this->canShowAdminBar();

        if (! $canShowAdminBar) {
            /** @var view-string $view */
            $view = 'cms::components.admin-bar';

            return view($view, [
                'can_show_admin_bar' => false,
                'edit_link' => null,
                'preview_page' => null,
            ]);
        }

        $routeSegments = request()->segments();
        $lastSegment = end($routeSegments) ?: '';

        $extension = setting('seo_url_extension', '');
        if ($extension !== '') {
            $lastSegment = str_replace($extension, '', $lastSegment);
        }

        // Check if we're in preview mode (set by ThemeFrontendController)
        $previewPage = request()->attributes->get('preview_page');

        if ($lastSegment !== '') {
            if (is_numeric($lastSegment)) {
                $query = CmsPost::query()->where('id', $lastSegment)->withTrashed();
                // If in preview mode, don't filter by status
                if (! $previewPage) {
                    $query->where('status', 'published');
                }

                $candidate = $query->first();
                $cmsPost = $candidate instanceof CmsPost ? $candidate : null;
            } else {
                $query = CmsPost::query()->where('slug', $lastSegment)->withTrashed();
                // If in preview mode, don't filter by status
                if (! $previewPage) {
                    $query->where('status', 'published');
                }

                $candidate = $query->first();
                $cmsPost = $candidate instanceof CmsPost ? $candidate : null;
            }
        } else {
            $homePageId = setting('cms_default_pages_home_page', null);

            if (filter_var($homePageId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false) {
                $candidate = CmsPost::query()->where('id', (int) $homePageId)
                    ->where('status', 'published')
                    ->withTrashed()
                    ->first();
                $cmsPost = $candidate instanceof CmsPost ? $candidate : null;
            } else {
                $cmsPost = null;
            }
        }

        $editLink = null;

        if ($cmsPost instanceof CmsPost) {
            $postType = $cmsPost->type;
            if ($postType === 'page') {
                $editLink = [
                    'url' => route('cms.pages.edit', $cmsPost->id),
                    'label' => 'Edit Page',
                ];
            } elseif ($postType === 'post') {
                $editLink = [
                    'url' => route('cms.posts.edit', $cmsPost->id),
                    'label' => 'Edit Post',
                ];
            } elseif ($postType === 'category') {
                $editLink = [
                    'url' => route('cms.categories.edit', $cmsPost->id),
                    'label' => 'Edit Category',
                ];
            } elseif ($postType === 'tag') {
                $editLink = [
                    'url' => route('cms.tags.edit', $cmsPost->id),
                    'label' => 'Edit Tag',
                ];
            }
        }

        /** @var view-string $view */
        $view = 'cms::components.admin-bar';

        return view($view, [
            'can_show_admin_bar' => $canShowAdminBar,
            'edit_link' => $editLink,
            'preview_page' => $previewPage,
        ]);
    }
}
