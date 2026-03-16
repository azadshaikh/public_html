<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\CMS\Http\Requests\SavePageRequest;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\CMSService;
use Modules\CMS\Services\SectionsService;
use Modules\CMS\Services\ThemeService;

class BuilderController extends Controller
{
    use AuthorizesRequests;

    private const string MODULE_PATH = 'cms::builder';

    public function __construct(
        protected CMSService $cmsService,
        protected ThemeService $themeService,
        protected SectionsService $sectionsService
    ) {}

    public function builder(CmsPost $page): View
    {
        $this->authorizeBuilderAccess($page);

        // Additionally check if user can edit this specific item (owner or has elevated permissions)
        abort_unless($this->canEditPage($page), 403, 'You do not have permission to edit this item.');

        $max_upload_size = config('media-library.max_file_size') / (1024 * 1024);
        $accepted_file_types = config('media.media_allowed_file_types');

        $allowed_types = explode(',', $accepted_file_types);

        // Categorize file types for simpler display
        $has_images = false;
        $has_videos = false;
        $has_documents = false;

        foreach ($allowed_types as $type) {
            $type = trim($type);
            if (str_starts_with($type, 'image/')) {
                $has_images = true;
            } elseif (str_starts_with($type, 'video/')) {
                $has_videos = true;
            } elseif (str_starts_with($type, 'application/') || str_starts_with($type, 'text/')) {
                $has_documents = true;
            }
        }

        $friendly_categories = [];
        if ($has_images) {
            $friendly_categories[] = 'Images';
        }

        if ($has_videos) {
            $friendly_categories[] = 'Videos';
        }

        if ($has_documents) {
            $friendly_categories[] = 'Documents';
        }

        $upload_settings = [
            'max_size_mb' => $max_upload_size,
            'max_size_bytes' => config('media-library.max_file_size'),
            'accepted_mime_types' => $accepted_file_types,
            'friendly_file_types' => implode(', ', $friendly_categories),
            'upload_route' => route('app.media.upload-media'),
            'settings_route' => route('app.media.upload-settings'),
        ];

        $ismodal = 'true';

        /** @var view-string $view */
        $view = self::MODULE_PATH.'.builder';

        return view($view, ['page' => $page, 'upload_settings' => $upload_settings, 'ismodal' => $ismodal]);
    }

    public function save(SavePageRequest $request, CmsPost $page): JsonResponse
    {
        $this->authorizeBuilderAccess($page);

        // Check if user can edit this specific item
        abort_unless($this->canEditPage($page), 403, 'You do not have permission to edit this item.');

        // Content update (Astero editor)
        if ($request->isContentUpdate()) {
            $this->cmsService->updatePageContent(
                $page,
                $request->getPageContent(),
                $request->getCss(),
                $request->getJs()
            );

            return response()->json([
                'success' => true,
                'message' => 'Page saved successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No valid data provided for update',
        ], 400);
    }

    public function ajaxDesignBlocks(Request $request)
    {
        // Get predefined blocks from service
        $response_data = $this->sectionsService->getAllDesignblocks();

        $response_data['success'] = true;
        $response_data['message'] = 'Blocks loaded successfully';

        return response()->json($response_data);
    }

    /**
     * Check if the current user can edit a specific page.
     * Users can edit if they own the page OR have elevated permissions.
     */
    protected function canEditPage(CmsPost $page): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Owners can always edit their items
        if ($page->created_by === $user->id) {
            return true;
        }

        // Users with delete_* permission can edit any item of that type (admins/managers)
        $deletePermission = $page->type === 'post' ? 'delete_posts' : 'delete_pages';

        return (bool) $user->can($deletePermission);
    }

    protected function authorizeBuilderAccess(CmsPost $page): void
    {
        $ability = $page->type === 'post' ? 'edit_posts' : 'edit_pages';
        $this->authorize($ability);
    }
}
