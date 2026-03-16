<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\FormSubmission as FormSubmissionMail;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Form;
use Modules\CMS\Models\FormSubmission;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\PermaLinkService;
use Modules\CMS\Services\PostAccessProtectionService;
use Modules\CMS\Services\ThemeConfigService;
use Modules\CMS\Services\ThemeDataService;
use Modules\CMS\Services\ThemeService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ThemeFrontendController extends Controller
{
    public function __construct(
        protected ThemeDataService $themeDataService,
        protected ThemeService $themeService,
        protected PermaLinkService $permalinkService
    ) {}

    /**
     * Handle CMS permalink routes with flexible parameters
     * Route: /{path?} - supports unlimited path depth via request()->segments()
     */
    public function single(?string $path = null)
    {
        // Collect all URL segments
        $segments = request()->segments();

        abort_if(empty($segments), 404, 'No content identifier provided in the URL.');

        // Check if user can preview unpublished content
        $canPreview = auth()->check() && auth()->user()->can('preview_unpublished_content');

        // Use PermaLinkService to match the URL
        $match = $this->permalinkService->matchUrl($segments, $canPreview);

        // Handle classified URLs (future feature)
        abort_if($match['type'] === 'classified', 404);

        // Handle not found
        abort_if($match['type'] === 'not_found' || ! $match['model'], 404, 'The requested content could not be found.');

        // Validate canonical URL - must match expected permalink structure
        abort_unless($this->permalinkService->validatePath($segments, $match['model']), 404, 'The URL structure does not match the expected format.');

        // Handle author pages
        if ($match['type'] === 'author') {
            return $this->handleAuthorPage($match['model']);
        }

        // Handle CMS content (posts, pages, categories, tags)
        return $this->handleContentPage($match['model']);
    }

    /**
     * Show homepage
     */
    public function home(): ResponseFactory|Response
    {
        $template = Theme::getTemplate('home');

        if (! $template) {
            // Template not found - show error
            $activeTheme = Theme::getActiveTheme();
            $themeName = $activeTheme['name'] ?? 'none';
            $themeDir = $activeTheme['directory'] ?? 'none';

            $hierarchy = Theme::getTemplateHierarchy('home');

            Log::error('Homepage template not found', [
                'theme' => $themeName,
                'searched' => $hierarchy,
            ]);

            if (config('app.debug')) {
                $errorHtml = view('errors.twig-error', [
                    'template' => 'home template',
                    'theme' => $themeDir,
                    'error' => "Homepage template not found.\n\nSearched for: ".implode(', ', $hierarchy),
                    'file' => '/themes/'.$themeDir.'/templates/',
                    'line' => 0,
                    'trace' => "The theme is missing required template files.\n\nExpected one of:\n- ".implode("\n- ", $hierarchy),
                ])->render();

                return response($errorHtml, 500);
            }

            abort(500, 'Homepage template not found');
        }

        $home_page = setting('cms_default_pages_home_page', '');
        if (! empty($home_page)) {
            $candidate = CmsPost::query()->whereKey((int) $home_page)->first();
            $page = $candidate instanceof CmsPost ? $candidate : null;
            $themeData = $this->themeDataService->getHomepageData($page);
        } else {
            $themeData = $this->themeDataService->getHomepageData();
        }

        return $this->themeService->renderThemeTemplate($template, $themeData);
    }

    /**
     * Show single page
     */
    public function page(string $slug): Response
    {
        $canPreview = auth()->check() && auth()->user()->can('preview_unpublished_content');

        $query = CmsPost::query()->where('slug', $slug)
            ->where('type', '!=', 'design_block')
            ->with(['author']); // Eager load relationships to prevent N+1 queries

        if (! $canPreview) {
            $query->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now());
        }

        $page = $query->firstOrFail();
        abort_unless($page instanceof CmsPost, 404);

        // Store preview context for banner injection
        if ($canPreview && $page->status !== 'published') {
            request()->attributes->set('preview_page', $page);
        }

        $template = Theme::getTemplate('page', [
            'slug' => $slug,
            'id' => $page->id,
            'template' => $page->template,
        ]);

        if (! $template) {
            return $this->themeService->fallbackResponse('page', ['page' => $page]);
        }

        // Use appropriate data method based on content type
        $themeData = $page->type === 'post'
            ? $this->themeDataService->getPostData($page)
            : $this->themeDataService->getPageData($page);

        return $this->themeService->renderThemeTemplate($template, $themeData);
    }

    /**
     * Show archive/category pages
     */
    public function archive(Request $request): Response
    {
        $template = Theme::getTemplate('archive');

        if (! $template) {
            $themeData = $this->themeDataService->getArchiveData();

            return $this->themeService->fallbackResponse('archive', $themeData);
        }

        $blogs_page = setting('cms_default_pages_blogs_page', '');
        if (! empty($blogs_page)) {
            $candidate = CmsPost::query()->whereKey((int) $blogs_page)->with(['category'])->first();
            $page = $candidate instanceof CmsPost ? $candidate : null;

            $themeData = $this->themeDataService->getArchiveData($page);
        } else {
            $themeData = $this->themeDataService->getArchiveData();
        }

        return $this->themeService->renderThemeTemplate($template, $themeData);
    }

    /**
     * Show search results
     */
    public function search(Request $request): Response
    {
        $query = $request->query('q', '');

        $template = Theme::getTemplate('search');

        if (! $template) {
            $themeData = $this->themeDataService->getSearchData($query);

            return $this->themeService->fallbackResponse('search', $themeData);
        }

        $themeData = $this->themeDataService->getSearchData($query);

        return $this->themeService->renderThemeTemplate($template, $themeData);
    }

    /**
     * Show 404 error page
     */
    public function notFound(): Response
    {
        // Load theme if not already loaded
        $activeTheme = Theme::getActiveTheme();
        if ($activeTheme && ! Theme::isThemeLoaded()) {
            Theme::loadTheme($activeTheme['directory']);
        }

        $themeData = $this->themeDataService->get404Data();

        $template = Theme::getTemplate('404');

        if (! $template) {
            return $this->themeService->fallbackResponse('404', $themeData, 404);
        }

        // Pass full context for theme links/menus + is_404 flag for SEO
        return $this->themeService->renderThemeTemplate($template, $themeData, 404);
    }

    /**
     * Get theme asset URL
     * SECURITY: Only serves static asset files, blocks PHP and other executable files
     */
    public function asset(string $theme, string $asset): BinaryFileResponse
    {
        return $this->themeService->getThemeAsset($theme, $asset);
    }

    /**
     * Preview theme
     */
    public function preview(string $directory): Response|Factory|\Illuminate\Contracts\View\View
    {
        $theme = Theme::getThemeInfo($directory);

        abort_unless((bool) $theme, 404, 'Theme not found');

        // Temporarily set this theme as active for preview
        config(['theme.active' => $directory]);
        config(['theme.info' => $theme]);
        Theme::resetThemeLoadedState();

        // Add theme view path
        View::addLocation($theme['path']);
        View::addNamespace('themes', $theme['path']);
        View::share('theme', $theme);

        // Try to render index template
        $template = Theme::getTemplate('home');

        if ($template) {
            $pages = CmsPost::query()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->with(['author']) // Eager load relationships to prevent N+1 queries
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            return $this->themeService->renderThemeTemplate($template, ['pages' => $pages]);
        }

        // Fallback preview
        /** @var view-string $view */
        $view = 'fallback.preview';

        return view($view, ['theme' => $theme]);
    }

    /**
     * Serve custom CSS generated from theme configuration
     */
    public function customCSS(string $theme): Response
    {
        $themeInfo = Theme::getThemeInfo($theme);
        abort_unless((bool) $themeInfo, 404, 'Theme not found');

        $css = resolve(ThemeConfigService::class)->generateCustomCSS($theme);

        return response($css)->header('Content-Type', 'text/css');
    }

    public function formSubmission(Request $request) // TODO: Implement form submission logic
    {
        $form = Form::query()->where('shortcode', $request->shortcode)->first();
        abort_unless((bool) $form, 404);

        try {
            $form_data = $request->except('_token', 'shortcode');

            if ($form->store_in_database) {
                FormSubmission::query()->create([
                    'form_id' => $form->id,
                    'data' => $form_data,
                ]);
            }

            if ($form->email_template && setting('email_driver', 'smtp') === 'smtp') {
                $config = [
                    'driver' => 'smtp',
                    'host' => setting('email_smtp_host', ''),
                    'port' => setting('email_smtp_port', ''),
                    'from' => ['address' => setting('email_sent_from_address', ''), 'name' => setting('email_sent_from_name', '')],
                    'encryption' => setting('email_smtp_encryption', ''),
                    'username' => setting('email_smtp_username', ''),
                    'password' => setting('email_smtp_password', ''),
                    // 'sendmail' => '/usr/sbin/sendmail -bs',
                    // 'pretend' => false,
                ];
                config()->set('mail', $config);
                $submission_data = $form_data;
                $submission_data['app_name'] = theme_get_option('site_title', setting('site_title'));
                $all_data_html = '';
                if (count($form_data) > 0) {
                    foreach ($form_data as $key => $value) {
                        $all_data_html .= '<p>'.ucfirst(str_replace('_', ' ', $key)).': '.$value.'</p>';
                    }
                }

                $submission_data['all_fields'] = $all_data_html;
                $subject = $form->email_template['subject'];
                if (str_contains((string) $subject, '{')) {
                    $subject = str_replace('{app_name}', $submission_data['app_name'], $subject);

                    if (str_contains($subject, '{') && ! empty($form_data)) {
                        foreach ($form_data as $key => $value) {
                            $subject = str_replace('{'.$key.'}', $value, $subject);
                        }
                    }
                }

                $admin_emails = User::getAdminEmails();
                $form_send_to_emails = (empty($form->email_template['send_to_email']) ? [] : explode(',', (string) $form->email_template['send_to_email']));
                if (in_array('{admin_email}', $form_send_to_emails)) {
                    $find_key = array_search('{admin_email}', $form_send_to_emails, true);
                    unset($form_send_to_emails[$find_key]);
                    if ($admin_emails !== []) {
                        $form_send_to_emails = array_merge($form_send_to_emails, $admin_emails);
                    }
                }

                $send_to_email = $form_send_to_emails;
                $form_reply_to_emails = (empty($form->email_template['reply_to_email']) ? [] : explode(',', (string) $form->email_template['reply_to_email']));
                if (in_array('{admin_email}', $form_reply_to_emails)) {
                    $find_key = array_search('{admin_email}', $form_reply_to_emails, true);
                    unset($form_reply_to_emails[$find_key]);
                    if ($admin_emails !== []) {
                        $form_reply_to_emails = array_merge($form_reply_to_emails, $admin_emails);
                    }
                }

                $reply_to_email = ($form_reply_to_emails !== [] ? $form_reply_to_emails : null);
                $mail_data = collect([
                    'subject' => $subject,
                    'message' => $form->email_template['message'],
                    'send_to' => $send_to_email,
                    'reply_to' => $reply_to_email,
                    'submission_data' => $submission_data,
                ]);
                // send email if subject and send_to_email are not empty
                if (! empty($subject) && $send_to_email !== []) {
                    Mail::send(new FormSubmissionMail($mail_data));
                }
            }

            if (isset($form->confirmations['type'])) {
                if ($form->confirmations['type'] === 'redirect') {
                    return redirect()->to($form->confirmations['redirect']);
                }

                if ($form->confirmations['type'] === 'message') {
                    return back()->with('success', $form->confirmations['message'] ?? 'Form submitted successfully');
                }
            } else {
                return back()->with('success', 'Form submitted successfully');
            }
        } catch (Exception) {
            return back()->with('error', 'Failed to submit form');
        }
    }

    /**
     * Handle author page rendering
     */
    private function handleAuthorPage(User $author): Factory|\Illuminate\Contracts\View\View|Response
    {
        $template = Theme::getTemplate('author', [
            'slug' => $author->username,
            'id' => $author->id,
        ]);

        if (! $template) {
            Log::info('Using fallback template for author: '.$author->username);

            return view('fallback.page', ['author' => $author]);
        }

        $themeData = $this->themeDataService->getAuthorData($author);

        return $this->themeService->renderThemeTemplate($template, $themeData);
    }

    /**
     * Handle content page rendering (posts, pages, categories, tags)
     */
    private function handleContentPage(CmsPost $page)
    {
        // Design blocks are internal reusable fragments, not public pages.
        abort_if($page->type === 'design_block', 404);

        // Store preview context for banner injection
        $canPreview = auth()->check() && auth()->user()->can('preview_unpublished_content');
        if ($canPreview && $page->status !== 'published') {
            request()->attributes->set('preview_page', $page);
        }

        // Redirect if this is a special page (home or blog)
        if (setting('cms_default_pages_home_page', '') === $page->id) {
            return to_route('home');
        }

        if (setting('cms_default_pages_blogs_page', '') === $page->id) {
            return to_route('archive');
        }

        // Check private visibility - require authentication
        if (in_array($page->type, ['post', 'page']) && $page->visibility === 'private' && ! auth()->check()) {
            session(['url.intended' => request()->fullUrl()]);

            return to_route('login')->with('warning', __('general.private_content_login_required'));
        }

        // Check password protection
        if (in_array($page->type, ['post', 'page']) && $page->isPasswordProtected()) {
            $postAccessService = resolve(PostAccessProtectionService::class);
            if (! $postAccessService->isPostAccessVerified($page->id)) {
                $postAccessService->storeIntendedUrl(request()->fullUrl(), $page->id);

                return to_route('post.access.protection.form', $page);
            }
        }

        // Get the template
        $template = Theme::getTemplate($page->type, [
            'slug' => $page->slug,
            'id' => $page->id,
            'template' => $page->template,
        ]);

        // Handle template not found
        if (! $template) {
            return $this->handleMissingTemplate($page);
        }

        // Get appropriate theme data based on content type
        $themeData = match ($page->type) {
            'category', 'tag' => $this->themeDataService->getArchiveData($page),
            'post' => $this->themeDataService->getPostData($page),
            default => $this->themeDataService->getPageData($page),
        };

        return $this->themeService->renderThemeTemplate($template, $themeData);
    }

    /**
     * Handle missing template error
     */
    private function handleMissingTemplate(CmsPost $page)
    {
        // For archive types, try fallback
        if (in_array($page->type, ['category', 'tag'])) {
            $themeData = $this->themeDataService->getArchiveData($page);

            return $this->themeService->fallbackResponse('archive', $themeData);
        }

        $activeTheme = Theme::getActiveTheme();
        $themeName = $activeTheme['name'] ?? 'none';
        $themeDir = $activeTheme['directory'] ?? 'none';

        $hierarchy = Theme::getTemplateHierarchy($page->type, [
            'slug' => $page->slug,
            'id' => $page->id,
        ]);

        Log::error('Template not found', [
            'page_slug' => $page->slug,
            'page_type' => $page->type,
            'theme' => $themeName,
            'searched' => $hierarchy,
        ]);

        if (config('app.debug')) {
            $errorHtml = view('errors.twig-error', [
                'template' => $page->type.' template',
                'theme' => $themeDir,
                'error' => "Template not found for {$page->type} content type.\n\nSearched for: ".implode(', ', $hierarchy),
                'file' => '/themes/'.$themeDir.'/templates/',
                'line' => 0,
                'trace' => "The theme is missing required template files.\n\nExpected one of:\n- ".implode("\n- ", $hierarchy),
            ])->render();

            return response($errorHtml, 500);
        }

        abort(500, 'Template not found for '.$page->type);
    }
}
