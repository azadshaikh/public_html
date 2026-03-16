<?php

use App\Http\Middleware\FileUploadRateLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\Api\ThemeBlockController;
use Modules\CMS\Http\Controllers\BuilderController;
use Modules\CMS\Http\Controllers\CategoriesController;
use Modules\CMS\Http\Controllers\DefaultPagesSettingController;
use Modules\CMS\Http\Controllers\DesignBlockController;
use Modules\CMS\Http\Controllers\FormController;
use Modules\CMS\Http\Controllers\MenuController;
use Modules\CMS\Http\Controllers\PagesController;
use Modules\CMS\Http\Controllers\PostAccessProtectionController;
use Modules\CMS\Http\Controllers\PostsController;
use Modules\CMS\Http\Controllers\RedirectionsController;
use Modules\CMS\Http\Controllers\SeoDashboardController;
use Modules\CMS\Http\Controllers\SeoSettingController;
use Modules\CMS\Http\Controllers\SiteAccessProtectionController;
use Modules\CMS\Http\Controllers\SitemapController;
use Modules\CMS\Http\Controllers\TagsController;
use Modules\CMS\Http\Controllers\ThemeController;
use Modules\CMS\Http\Controllers\ThemeCustomizerController;
use Modules\CMS\Http\Controllers\ThemeEditorController;
use Modules\CMS\Http\Controllers\ThemeFrontendController;
use Modules\CMS\Http\Controllers\WidgetController;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::group(['prefix' => config('app.admin_slug').'/cms', 'as' => 'cms.', 'middleware' => ['module_access:cms']], function (): void {
        Route::group(['prefix' => 'categories', 'as' => 'categories.'], function (): void {
            Route::post('/bulk-action', [CategoriesController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [CategoriesController::class, 'create'])->name('create');
            Route::post('/', [CategoriesController::class, 'store'])->name('store');

            Route::get('/{category}/edit', [CategoriesController::class, 'edit'])->name('edit')->where('category', '[0-9]+');
            Route::put('/{category}', [CategoriesController::class, 'update'])->name('update')->where('category', '[0-9]+');
            Route::delete('/{category}', [CategoriesController::class, 'destroy'])->name('destroy')->where('category', '[0-9]+');
            Route::delete('/{category}/force-delete', [CategoriesController::class, 'forceDelete'])->name('force-delete')->where('category', '[0-9]+');
            Route::patch('/{category}/restore', [CategoriesController::class, 'restore'])->name('restore')->where('category', '[0-9]+');

            // Generic index route LAST (catch-all patterns)
            Route::get('/{status?}', [CategoriesController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|published|draft|trash)$');
        });

        Route::group(['prefix' => 'tags', 'as' => 'tags.'], function (): void {
            Route::post('/bulk-action', [TagsController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [TagsController::class, 'create'])->name('create');
            Route::post('/', [TagsController::class, 'store'])->name('store');

            Route::get('/{tag}/edit', [TagsController::class, 'edit'])->name('edit')->where('tag', '[0-9]+');
            Route::put('/{tag}', [TagsController::class, 'update'])->name('update')->where('tag', '[0-9]+');
            Route::delete('/{tag}', [TagsController::class, 'destroy'])->name('destroy')->where('tag', '[0-9]+');
            Route::delete('/{tag}/force-delete', [TagsController::class, 'forceDelete'])->name('force-delete')->where('tag', '[0-9]+');
            Route::patch('/{tag}/restore', [TagsController::class, 'restore'])->name('restore')->where('tag', '[0-9]+');

            // Generic index route LAST (catch-all patterns)
            Route::get('/{status?}', [TagsController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|published|draft|trash)$');
        });

        Route::prefix('posts')->name('posts.')->group(function (): void {
            Route::post('/bulk-action', [PostsController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [PostsController::class, 'create'])->name('create');
            Route::post('/', [PostsController::class, 'store'])->name('store');

            Route::get('/{post}/edit', [PostsController::class, 'edit'])->name('edit')->where('post', '[0-9]+');
            Route::put('/{post}', [PostsController::class, 'update'])->name('update')->where('post', '[0-9]+');
            Route::post('/{post}/duplicate', [PostsController::class, 'duplicate'])->name('duplicate')->where('post', '[0-9]+');
            Route::delete('/{post}', [PostsController::class, 'destroy'])->name('destroy')->where('post', '[0-9]+');
            Route::delete('/{post}/force-delete', [PostsController::class, 'forceDelete'])->name('force-delete')->where('post', '[0-9]+');
            Route::patch('/{post}/restore', [PostsController::class, 'restore'])->name('restore')->where('post', '[0-9]+');

            // Generic index route LAST (catch-all patterns)
            Route::get('/{status?}', [PostsController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|published|draft|scheduled|trash)$');
        });

        Route::group(['prefix' => 'pages', 'as' => 'pages.'], function (): void {
            Route::post('/bulk-action', [PagesController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [PagesController::class, 'create'])->name('create');
            Route::post('/', [PagesController::class, 'store'])->name('store');

            Route::get('/{page}/edit', [PagesController::class, 'edit'])->name('edit')->where('page', '[0-9]+');
            Route::put('/{page}', [PagesController::class, 'update'])->name('update')->where('page', '[0-9]+');
            Route::post('/{page}/duplicate', [PagesController::class, 'duplicate'])->name('duplicate')->where('page', '[0-9]+');
            Route::delete('/{page}', [PagesController::class, 'destroy'])->name('destroy')->where('page', '[0-9]+');
            Route::delete('/{page}/force-delete', [PagesController::class, 'forceDelete'])->name('force-delete')->where('page', '[0-9]+');
            Route::patch('/{page}/restore', [PagesController::class, 'restore'])->name('restore')->where('page', '[0-9]+');

            // Generic index route LAST (catch-all patterns)
            Route::get('/{status?}', [PagesController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|published|draft|scheduled|trash)$');
        });

        Route::prefix('builder')->name('builder.')->group(function (): void {
            Route::get('ajax-design-blocks', [BuilderController::class, 'ajaxDesignBlocks'])->name('ajax.design.blocks');
            Route::post('save/{page:id}', [BuilderController::class, 'save'])->name('save')->middleware('throttle:30,1');
            Route::get('{page:id}', [BuilderController::class, 'builder'])->name('edit');

            // Theme Block Service API
            Route::group(['prefix' => 'theme', 'as' => 'theme.'], function (): void {
                Route::get('/{type}', [ThemeBlockController::class, 'index'])
                    ->where('type', 'blocks|sections')
                    ->name('manifest');

                Route::post('/{type}/render', [ThemeBlockController::class, 'render'])
                    ->where('type', 'blocks|sections')
                    ->name('render');
            });
        });

        Route::prefix('form')->name('form.')->group(function (): void {
            Route::post('/bulk-action', [FormController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [FormController::class, 'create'])->name('create');
            Route::post('/', [FormController::class, 'store'])->name('store');

            Route::get('/{form}/edit', [FormController::class, 'edit'])->name('edit')->where('form', '[0-9]+');
            Route::put('/{form}', [FormController::class, 'update'])->name('update')->where('form', '[0-9]+');
            Route::delete('/{form}', [FormController::class, 'destroy'])->name('destroy')->where('form', '[0-9]+');
            Route::delete('/{form}/force-delete', [FormController::class, 'forceDelete'])->name('force-delete')->where('form', '[0-9]+');
            Route::patch('/{form}/restore', [FormController::class, 'restore'])->name('restore')->where('form', '[0-9]+');
            Route::get('/{form}', [FormController::class, 'redirectToEdit'])->name('show')->where('form', '[0-9]+');

            Route::get('/{status?}', [FormController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|published|draft|trash)$');
        });

        // Media management moved to main app routes
        // See routes/web.php for media management routes under app.media.*

        // File-based Theme Management Routes
        Route::prefix('appearance')->name('appearance.')->group(function (): void {
            Route::prefix('themes')->name('themes.')->group(function (): void {
                Route::get('/', [ThemeController::class, 'index'])->name('index');
                Route::middleware([FileUploadRateLimit::class])->post('/import', [ThemeController::class, 'import'])->name('import');

                // Theme Customizer Routes (must be before {directory} routes)
                Route::prefix('customizer')->name('customizer.')->group(function (): void {
                    Route::get('/', [ThemeCustomizerController::class, 'index'])->name('index');
                    Route::post('/update', [ThemeCustomizerController::class, 'update'])->name('update');
                    Route::post('/preview-css', [ThemeCustomizerController::class, 'previewCSS'])->name('preview-css'); // Azad: need to remove this later
                    Route::post('/reset', [ThemeCustomizerController::class, 'reset'])->name('reset');
                    Route::get('/export', [ThemeCustomizerController::class, 'export'])->name('export');
                    Route::post('/import', [ThemeCustomizerController::class, 'import'])->name('import');
                });

                // Theme Editor Routes (IDE-style editor - must be before {directory} routes)
                Route::prefix('editor')->name('editor.')->group(function (): void {
                    Route::get('/{directory}', [ThemeEditorController::class, 'index'])->name('index');
                    Route::get('/{directory}/files', [ThemeEditorController::class, 'files'])->name('files');
                    Route::get('/{directory}/file/{path}', [ThemeEditorController::class, 'read'])
                        ->where('path', '.*')->name('file.read');
                    Route::put('/{directory}/file/{path}', [ThemeEditorController::class, 'save'])
                        ->where('path', '.*')->name('file.save');
                    Route::post('/{directory}/file', [ThemeEditorController::class, 'create'])->name('file.create');
                    Route::post('/{directory}/upload', [ThemeEditorController::class, 'upload'])->name('upload');
                    Route::delete('/{directory}/file/{path}', [ThemeEditorController::class, 'delete'])
                        ->where('path', '.*')->name('file.delete');
                    Route::post('/{directory}/rename', [ThemeEditorController::class, 'rename'])->name('rename');
                    Route::post('/{directory}/duplicate', [ThemeEditorController::class, 'duplicate'])->name('duplicate');
                    Route::post('/{directory}/folder', [ThemeEditorController::class, 'createFolder'])->name('folder.create');
                    Route::delete('/{directory}/folder/{path}', [ThemeEditorController::class, 'deleteFolder'])
                        ->where('path', '.*')->name('folder.delete');
                    Route::post('/{directory}/search', [ThemeEditorController::class, 'search'])
                        ->name('search');

                    // Git-based history/diff/restore (incremental rollout)
                    Route::get('/{directory}/git/history', [ThemeEditorController::class, 'gitHistoryAll'])
                        ->name('git.history.all');
                    Route::get('/{directory}/git/history/{path}', [ThemeEditorController::class, 'gitHistory'])
                        ->where('path', '.*')->name('git.history');
                    Route::get('/{directory}/git/file/{commitHash}/{path}', [ThemeEditorController::class, 'gitFileAtCommit'])
                        ->where(['commitHash' => '(HEAD|[A-Fa-f0-9]{7,40})', 'path' => '.*'])->name('git.file');
                    Route::get('/{directory}/git/commit/{commitHash}/files', [ThemeEditorController::class, 'gitCommitFiles'])
                        ->where(['commitHash' => '[A-Fa-f0-9]{7,40}'])->name('git.commit.files');
                    Route::post('/{directory}/git/commit/{commitHash}/diff', [ThemeEditorController::class, 'gitCommitFileDiff'])
                        ->where(['commitHash' => '[A-Fa-f0-9]{7,40}'])->name('git.commit.diff');
                    Route::get('/{directory}/git/status', [ThemeEditorController::class, 'gitStatus'])->name('git.status');
                    Route::post('/{directory}/git/diff', [ThemeEditorController::class, 'gitDiff'])->name('git.diff');
                    Route::post('/{directory}/git/working-diff', [ThemeEditorController::class, 'gitWorkingDiff'])
                        ->name('git.working-diff');
                    Route::post('/{directory}/git/commit', [ThemeEditorController::class, 'gitCommit'])->name('git.commit');
                    Route::post('/{directory}/git/stage', [ThemeEditorController::class, 'gitStage'])->name('git.stage');
                    Route::post('/{directory}/git/unstage', [ThemeEditorController::class, 'gitUnstage'])->name('git.unstage');
                    Route::post('/{directory}/git/discard', [ThemeEditorController::class, 'gitDiscard'])->name('git.discard');
                    Route::post('/{directory}/git/restore', [ThemeEditorController::class, 'gitRestore'])->name('git.restore');
                    Route::post('/{directory}/git/restore-commit', [ThemeEditorController::class, 'gitRestoreCommit'])->name('git.restore.commit');
                });

                // Theme-specific routes (with {directory} wildcard)
                Route::post('/{directory}/activate', [ThemeController::class, 'activate'])->name('activate');
                Route::get('/{directory}/export', [ThemeController::class, 'export'])->name('export');
                Route::delete('/{directory}', [ThemeController::class, 'destroy'])->name('destroy');
                Route::get('/{directory}/preview', [ThemeController::class, 'preview'])->name('preview');

                // Child theme routes
                Route::post('/create-child', [ThemeController::class, 'createChild'])->name('create-child');
                Route::post('/{directory}/detach', [ThemeController::class, 'detach'])->name('detach');
            });

            // Menu Management Routes (Scaffold Pattern)
            Route::prefix('menus')->name('menus.')->group(function (): void {
                // Bulk action
                Route::post('/bulk-action', [MenuController::class, 'bulkAction'])->name('bulk-action');

                // CRUD routes
                Route::get('/create', [MenuController::class, 'create'])->name('create');
                Route::post('/', [MenuController::class, 'store'])->name('store');
                Route::get('/{menu}/edit', [MenuController::class, 'edit'])->name('edit')->where('menu', '[0-9]+');
                Route::put('/{menu}', [MenuController::class, 'update'])->name('update')->where('menu', '[0-9]+');
                Route::delete('/{menu}', [MenuController::class, 'destroy'])->name('destroy')->where('menu', '[0-9]+');
                Route::delete('/{menu}/force-delete', [MenuController::class, 'forceDelete'])->name('force-delete')->where('menu', '[0-9]+');
                Route::patch('/{menu}/restore', [MenuController::class, 'restore'])->name('restore')->where('menu', '[0-9]+');

                // Custom actions
                Route::post('/{menu}/save-all', [MenuController::class, 'saveAll'])->name('save-all')->where('menu', '[0-9]+');
                Route::post('/{menu}/duplicate', [MenuController::class, 'duplicate'])->name('duplicate')->where('menu', '[0-9]+');

                // Index with status filter - MUST be last to avoid matching other routes
                Route::get('/{status?}', [MenuController::class, 'index'])
                    ->name('index')
                    ->where('status', '^(all|active|inactive|trash)$');
            });

            // Widget Management Routes
            Route::prefix('widgets')->name('widgets.')->group(function (): void {
                Route::get('/', [WidgetController::class, 'index'])->name('index');
                Route::get('{area_id}/edit', [WidgetController::class, 'edit'])->name('edit');

                // AJAX endpoint for saving all widgets
                Route::post('/save-all', [WidgetController::class, 'saveAllWidgets'])->name('save-all');
            });
        });

        Route::group(['prefix' => 'designblock', 'as' => 'designblock.'], function (): void {
            Route::post('/bulk-action', [DesignBlockController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [DesignBlockController::class, 'create'])->name('create');
            Route::post('/', [DesignBlockController::class, 'store'])->name('store');

            Route::get('/{designBlock}/edit', [DesignBlockController::class, 'edit'])->name('edit')->where('designBlock', '[0-9]+');
            Route::put('/{designBlock}', [DesignBlockController::class, 'update'])->name('update')->where('designBlock', '[0-9]+');
            Route::delete('/{designBlock}', [DesignBlockController::class, 'destroy'])->name('destroy')->where('designBlock', '[0-9]+');
            Route::delete('/{designBlock}/force-delete', [DesignBlockController::class, 'forceDelete'])->name('force-delete')->where('designBlock', '[0-9]+');
            Route::patch('/{designBlock}/restore', [DesignBlockController::class, 'restore'])->name('restore')->where('designBlock', '[0-9]+');

            Route::get('/{status?}', [DesignBlockController::class, 'index'])
                ->name('index')
                ->where('status', '^(all|published|draft|trash)$');
        });

        // CMS Settings Routes
        Route::prefix('settings')->name('settings.')->group(function (): void {
            // Default Pages (Homepage, Blog, Contact, About, etc.)
            Route::get('/default-pages', [DefaultPagesSettingController::class, 'index'])
                ->name('default-pages');
            Route::put('/default-pages', [DefaultPagesSettingController::class, 'update'])
                ->name('default-pages.update');
        });

        // Integrations Routes
        Route::prefix('integrations')->name('integrations.')->group(function (): void {
            Route::get('/', static fn (): RedirectResponse => to_route('cms.integrations.webmastertools'))
                ->name('index');

            Route::get('/webmaster-tools', [SeoSettingController::class, 'index'])
                ->name('webmastertools')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'webmaster_tools');

            Route::get('/google-analytics', [SeoSettingController::class, 'index'])
                ->name('googleanalytics')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'google_analytics');

            Route::get('/google-tags', [SeoSettingController::class, 'index'])
                ->name('googletags')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'google_tags');

            Route::get('/meta-pixel', [SeoSettingController::class, 'index'])
                ->name('metapixel')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'meta_pixel');

            Route::get('/microsoft-clarity', [SeoSettingController::class, 'index'])
                ->name('microsoftclarity')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'microsoft_clarity');

            Route::get('/google-adsense', [SeoSettingController::class, 'index'])
                ->name('googleadsense')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'google_adsense');

            Route::get('/custom-head-code', [SeoSettingController::class, 'index'])
                ->name('other')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'other');

            // Update routes for each integration
            Route::post('/googleanalytics/update', [SeoSettingController::class, 'update'])
                ->name('googleanalytics.update')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'google_analytics');

            Route::post('/googletags/update', [SeoSettingController::class, 'update'])
                ->name('googletags.update')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'google_tags');

            Route::post('/microsoftclarity/update', [SeoSettingController::class, 'update'])
                ->name('microsoftclarity.update')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'microsoft_clarity');

            Route::post('/metapixel/update', [SeoSettingController::class, 'update'])
                ->name('metapixel.update')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'meta_pixel');

            Route::post('/webmastertools/update', [SeoSettingController::class, 'update'])
                ->name('webmastertools.update')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'webmaster_tools');

            Route::post('/other/update', [SeoSettingController::class, 'update'])
                ->name('other.update')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'other');

            Route::post('/googleadsense/update', [SeoSettingController::class, 'update'])
                ->name('googleadsense.update')
                ->defaults('master_group', 'integrations')
                ->defaults('file_name', 'google_adsense');
        });

        // Redirections Routes
        Route::prefix('redirections')->name('redirections.')->group(function (): void {
            Route::post('/bulk-action', [RedirectionsController::class, 'bulkAction'])->name('bulk-action');
            Route::get('/create', [RedirectionsController::class, 'create'])->name('create');
            Route::post('/', [RedirectionsController::class, 'store'])->name('store');
            Route::get('/export', [RedirectionsController::class, 'export'])->name('export');
            Route::get('/import', [RedirectionsController::class, 'importForm'])->name('import.form');
            Route::post('/import', [RedirectionsController::class, 'import'])->name('import');
            Route::post('/test', [RedirectionsController::class, 'test'])->name('test');

            Route::get('/{redirection}/edit', [RedirectionsController::class, 'edit'])->name('edit')->where('redirection', '[0-9]+');
            Route::put('/{redirection}', [RedirectionsController::class, 'update'])->name('update')->where('redirection', '[0-9]+');
            Route::delete('/{redirection}', [RedirectionsController::class, 'destroy'])->name('destroy')->where('redirection', '[0-9]+');
            Route::delete('/{redirection}/force-delete', [RedirectionsController::class, 'forceDelete'])->name('force-delete')->where('redirection', '[0-9]+');
            Route::patch('/{redirection}/restore', [RedirectionsController::class, 'restore'])->name('restore')->where('redirection', '[0-9]+');
            Route::get('/{status?}', [RedirectionsController::class, 'index'])
                ->where('status', '^(all|active|inactive|trash)$')
                ->name('index');
        });
    });

    Route::group(['prefix' => config('app.admin_slug').'/seo', 'as' => 'seo.'], function (): void {
        // SEO Dashboard - main entry point
        Route::get('/', [SeoDashboardController::class, 'index'])->name('dashboard');

        Route::prefix('settings')->name('settings.')->group(function (): void {
            Route::post('/general/update', [SeoSettingController::class, 'update'])
                ->name('general.update')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'general');

            Route::get('/titlesmeta', [SeoSettingController::class, 'index'])
                ->name('titlesmeta')
                ->defaults('master_group', 'settings')
                ->defaults('file_name', 'titlesmeta');
            Route::post('/titlesmeta/update', [SeoSettingController::class, 'update'])
                ->name('titlesmeta.update')
                ->defaults('master_group', 'settings')
                ->defaults('file_name', 'titlesmeta');

            Route::get('/localseo', [SeoSettingController::class, 'index'])
                ->name('localseo')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'local_seo');
            Route::post('/localseo/update', [SeoSettingController::class, 'update'])
                ->name('localseo.update')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'local_seo');

            Route::get('/socialmedia', [SeoSettingController::class, 'index'])
                ->name('socialmedia')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'social_media');
            Route::post('/socialmedia/update', [SeoSettingController::class, 'update'])
                ->name('socialmedia.update')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'social_media');

            Route::get('/schema', [SeoSettingController::class, 'index'])
                ->name('schema')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'schema');
            Route::post('/schema/update', [SeoSettingController::class, 'update'])
                ->name('schema.update')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'schema');

            Route::get('/sitemap', [SeoSettingController::class, 'index'])
                ->name('sitemap')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'sitemap');
            Route::post('/sitemap/update', [SeoSettingController::class, 'update'])
                ->name('sitemap.update')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'sitemap');

            Route::get('/robots', [SeoSettingController::class, 'index'])
                ->name('robots')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'robots');
            Route::post('/robots/update', [SeoSettingController::class, 'update'])
                ->name('robots.update')
                ->defaults('master_group', 'common')
                ->defaults('file_name', 'robots');

            // Import & Export Routes
            Route::get('/importexport', [SeoSettingController::class, 'importExport'])->name('importexport');
            Route::post('/export', [SeoSettingController::class, 'exportSeoSettings'])->name('export');
            Route::post('/import', [SeoSettingController::class, 'importSeoSettings'])->name('import');

            // Legacy routes for backward compatibility
            Route::get('/{master_group}/{file_name}', [SeoSettingController::class, 'index'])->name('index');
            Route::post('/{master_group}/{file_name}/update', [SeoSettingController::class, 'update'])->name('update');
        });

        Route::post('/sitemap/regenerate', [SeoSettingController::class, 'regenerateSitemap'])->name('sitemap.regenerate');
    });
});

// Theme custom CSS (generated from theme customizer settings)
// Uses _customizer.css (underscore prefix) to indicate dynamically generated, not a static file
Route::get('/themes/{theme}/_customizer.css', [ThemeFrontendController::class, 'customCSS'])
    ->name('theme.custom-css')->middleware(['cdnCacheHeaders']);

// Theme asset serving
Route::get('/themes/{theme}/{asset}', [ThemeFrontendController::class, 'asset'])
    ->where('asset', '.*')
    ->name('theme.asset')->middleware(['cdnCacheHeaders']);

// Theme preview
Route::get('/theme-preview/{directory}', [ThemeFrontendController::class, 'preview'])
    ->name('theme.preview');

Route::post('/form/submit', [ThemeFrontendController::class, 'formSubmission'])
    ->middleware('throttle:5,1')
    ->name('form.submission');

// Frontend theme routes (with theme middleware)
Route::middleware(['theme', 'site.access.protection', 'url.extension', 'cdnCacheHeaders'])->group(function (): void {
    Route::get('/', [ThemeFrontendController::class, 'home'])->name('home');

    Route::get('/search{ext?}', [ThemeFrontendController::class, 'search'])
        ->where('ext', '(\.html)?')
        ->name('search');

    Route::get('/'.strtolower((string) setting('cms_default_pages_blog_base_url', 'blog')).'{ext?}', [ThemeFrontendController::class, 'archive'])
        ->where('ext', '(\.html)?')
        ->name('archive');
});

// Public CMS Routes (no authentication required)
Route::get('/site-access-protection', [SiteAccessProtectionController::class, 'create'])->name('site.access.protection.form');
Route::post('/site-access-protection', [SiteAccessProtectionController::class, 'store'])->name('site.access.protection.verify');

// Post Access Protection Routes
Route::get('/post-access-protection/{post}', [PostAccessProtectionController::class, 'create'])->name('post.access.protection.form');
Route::post('/post-access-protection/{post}', [PostAccessProtectionController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('post.access.protection.verify');

// Sitemap routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-{type}.xml', [SitemapController::class, 'show'])->name('sitemap.show');
