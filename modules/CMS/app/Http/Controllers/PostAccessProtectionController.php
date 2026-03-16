<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\CMS\Http\Requests\PostAccessProtectionRequest;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Services\PostAccessProtectionService;

class PostAccessProtectionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        /**
         * The post access protection service instance.
         */
        protected PostAccessProtectionService $postAccessProtectionService
    ) {}

    /**
     * Show the post access protection form.
     */
    public function create(CmsPost $post): View|RedirectResponse
    {
        $canPreview = auth()->check() && auth()->user()->can('preview_unpublished_content');
        abort_if(! $canPreview && ! $post->isPublished(), 404);

        // If post is not password protected, redirect to post
        if (! $post->isPasswordProtected()) {
            return redirect($post->permalink_url);
        }

        // If already verified, redirect to post
        if ($this->postAccessProtectionService->isPostAccessVerified($post->id)) {
            return redirect($post->permalink_url);
        }

        // Store the intended URL if not already stored
        $this->postAccessProtectionService->storeIntendedUrl(url()->previous(), $post->id);

        /** @var view-string $view */
        $view = 'cms::post-access-protection.form';

        return view($view, [
            'post' => $post,
        ]);
    }

    /**
     * Verify the post access password and redirect to intended URL.
     */
    public function store(PostAccessProtectionRequest $request, CmsPost $post): RedirectResponse
    {
        $canPreview = auth()->check() && auth()->user()->can('preview_unpublished_content');
        abort_if(! $canPreview && ! $post->isPublished(), 404);

        // Check if post is password protected
        if (! $post->isPasswordProtected()) {
            return redirect($post->permalink_url);
        }

        // Verify the password
        if (! $this->postAccessProtectionService->verifyPassword($post, $request->password)) {
            return back()->withErrors([
                'password' => __('general.post_password_incorrect'),
            ])->withInput();
        }

        // Mark post access as verified in session
        $this->postAccessProtectionService->markPostAccessAsVerified($post->id);

        // Get the intended URL and clear it from session
        $intendedUrl = $this->postAccessProtectionService->getAndClearIntendedUrl(
            $post->id,
            $post->permalink_url
        );

        return redirect($intendedUrl)->with('success', __('general.post_access_verified'));
    }
}
