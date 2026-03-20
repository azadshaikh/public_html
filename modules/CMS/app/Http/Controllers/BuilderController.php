<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\CMS\Http\Requests\SavePageRequest;
use Modules\CMS\Models\CmsPost;
use Modules\CMS\Models\Theme;
use Modules\CMS\Services\Builder\ThemeBlockService;
use Modules\CMS\Services\CMSService;
use Modules\CMS\Services\SectionsService;
use Modules\CMS\Services\ThemeService;

class BuilderController extends Controller
{
    use AuthorizesRequests;

    private const string BUILDER_METADATA_KEY = 'builder_v1';

    private const array SUPPORTED_LIBRARY_TYPES = ['sections', 'blocks'];

    public function __construct(
        protected CMSService $cmsService,
        protected ThemeService $themeService,
        protected SectionsService $sectionsService,
        protected ThemeBlockService $themeBlockService
    ) {}

    public function builder(CmsPost $page): InertiaResponse
    {
        $this->authorizeBuilderAccess($page);

        // Additionally check if user can edit this specific item (owner or has elevated permissions)
        abort_unless($this->canEditPage($page), 403, 'You do not have permission to edit this item.');

        return Inertia::render('cms/builder/edit', [
            'activeTheme' => $this->resolveActiveThemeSummary(),
            'page' => [
                'id' => $page->getKey(),
                'title' => (string) $page->getAttribute('title'),
                'permalink_url' => $page->permalink_url ? url($page->permalink_url) : null,
                'editor_url' => route($page->type === 'post' ? 'cms.posts.edit' : 'cms.pages.edit', $page),
                'updated_at_formatted' => $page->updated_at
                    ? app_date_time_format($page->updated_at, 'datetime')
                    : null,
                'updated_at_human' => $page->updated_at?->diffForHumans(),
                'content' => (string) ($page->getAttribute('content') ?? ''),
                'css' => (string) ($page->getAttribute('css') ?? ''),
                'js' => (string) ($page->getAttribute('js') ?? ''),
            ],
            'palette' => $this->buildPalette(),
            'builderState' => $this->resolveBuilderState($page),
        ]);
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

            $builderState = $request->getBuilderState();

            if (is_array($builderState)) {
                $page->setMetadata(self::BUILDER_METADATA_KEY, $this->normalizeBuilderState($builderState));
                $page->save();
            }

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

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function buildPalette(): array
    {
        $catalog = $this->sectionsService->getAllDesignblocks();
        $palette = [
            'sections' => [],
            'blocks' => [],
        ];

        $activeTheme = Theme::getActiveTheme();

        if (is_array($activeTheme) && filled($activeTheme['directory'] ?? null)) {
            $this->mergeThemePalette($palette, (string) $activeTheme['directory']);
        }

        foreach (self::SUPPORTED_LIBRARY_TYPES as $type) {
            foreach (($catalog[$type] ?? []) as $category => $items) {
                $normalizedItems = [];

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $normalizedItems[] = [
                        'id' => (string) ($item['id'] ?? ''),
                        'type' => $type,
                        'category' => (string) $category,
                        'category_label' => Str::headline(str_replace('_', ' ', (string) $category)),
                        'name' => (string) ($item['name'] ?? $item['title'] ?? 'Untitled'),
                        'html' => (string) ($item['html'] ?? ''),
                        'css' => (string) ($item['css'] ?? ''),
                        'js' => (string) ($item['js'] ?? ''),
                        'preview_image_url' => filled($item['image'] ?? null)
                            ? (string) $item['image']
                            : null,
                        'source' => 'database',
                    ];
                }

                if ($normalizedItems === []) {
                    continue;
                }

                $this->appendPaletteGroup(
                    $palette,
                    $type,
                    (string) $category,
                    Str::headline(str_replace('_', ' ', (string) $category)),
                    $normalizedItems,
                );
            }
        }

        return [
            'sections' => array_values($palette['sections']),
            'blocks' => array_values($palette['blocks']),
        ];
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>>>  $palette
     */
    protected function mergeThemePalette(array &$palette, string $themeDirectory): void
    {
        foreach (self::SUPPORTED_LIBRARY_TYPES as $type) {
            $manifest = $this->themeBlockService->getManifest($themeDirectory, $type);

            foreach (($manifest['items'] ?? []) as $item) {
                if (! is_array($item) || ! filled($item['slug'] ?? null)) {
                    continue;
                }

                $category = (string) ($item['category'] ?? 'general');
                $rendered = $this->themeBlockService->renderForBuilder(
                    $themeDirectory,
                    $type,
                    (string) $item['slug'],
                );

                $this->appendPaletteGroup(
                    $palette,
                    $type,
                    $category,
                    Str::headline(str_replace('_', ' ', $category)),
                    [[
                        'id' => (string) ($item['id'] ?? 'theme-'.$type.'-'.$item['slug']),
                        'type' => $type,
                        'category' => $category,
                        'category_label' => Str::headline(str_replace('_', ' ', $category)),
                        'name' => (string) ($item['name'] ?? Str::headline((string) $item['slug'])),
                        'html' => (string) ($rendered['html'] ?? ''),
                        'css' => '',
                        'js' => '',
                        'preview_image_url' => filled($item['image'] ?? null)
                            ? url((string) $item['image'])
                            : null,
                        'source' => 'theme',
                    ]],
                );
            }
        }
    }

    /**
     * @param  array<string, array<int|string, array<string, mixed>>>  $palette
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function appendPaletteGroup(
        array &$palette,
        string $type,
        string $groupKey,
        string $groupLabel,
        array $items,
    ): void {
        if (! isset($palette[$type][$groupKey])) {
            $palette[$type][$groupKey] = [
                'key' => $groupKey,
                'label' => $groupLabel,
                'items' => [],
            ];
        }

        $palette[$type][$groupKey]['items'] = [
            ...$palette[$type][$groupKey]['items'],
            ...$items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveActiveThemeSummary(): ?array
    {
        $activeTheme = Theme::getActiveTheme();

        if (! is_array($activeTheme)) {
            return null;
        }

        return [
            'name' => $activeTheme['name'] ?? Str::headline((string) ($activeTheme['directory'] ?? 'Theme')),
            'directory' => $activeTheme['directory'] ?? null,
            'description' => $activeTheme['description'] ?? null,
            'version' => $activeTheme['version'] ?? null,
            'screenshot' => $activeTheme['screenshot'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveBuilderState(CmsPost $page): array
    {
        $savedState = $page->getMetadata(self::BUILDER_METADATA_KEY);

        if (is_array($savedState)) {
            $normalized = $this->normalizeBuilderState($savedState);

            if (($normalized['items'] ?? []) !== [] || ($normalized['css'] ?? '') !== '' || ($normalized['js'] ?? '') !== '') {
                return [
                    ...$normalized,
                    'source' => 'metadata',
                ];
            }
        }

        $content = (string) ($page->getAttribute('content') ?? '');
        $css = (string) ($page->getAttribute('css') ?? '');
        $js = (string) ($page->getAttribute('js') ?? '');

        if (trim($content) !== '') {
            return [
                'source' => 'imported-content',
                'css' => $css,
                'js' => $js,
                'items' => [[
                    'uid' => 'imported-'.$page->getKey(),
                    'catalog_id' => null,
                    'type' => 'custom',
                    'category' => 'imported',
                    'label' => 'Imported page content',
                    'html' => $content,
                    'css' => '',
                    'js' => '',
                    'preview_image_url' => null,
                    'source' => 'imported-content',
                ]],
            ];
        }

        return [
            'source' => 'empty',
            'css' => $css,
            'js' => $js,
            'items' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $builderState
     * @return array<string, mixed>
     */
    protected function normalizeBuilderState(array $builderState): array
    {
        $items = [];

        foreach (($builderState['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $html = trim((string) ($item['html'] ?? ''));

            if ($html === '') {
                continue;
            }

            $items[] = [
                'uid' => (string) ($item['uid'] ?? Str::uuid()->toString()),
                'catalog_id' => filled($item['catalog_id'] ?? null)
                    ? (string) $item['catalog_id']
                    : null,
                'type' => (string) ($item['type'] ?? 'custom'),
                'category' => (string) ($item['category'] ?? 'custom'),
                'label' => (string) ($item['label'] ?? 'Untitled block'),
                'html' => $html,
                'css' => (string) ($item['css'] ?? ''),
                'js' => (string) ($item['js'] ?? ''),
                'preview_image_url' => filled($item['preview_image_url'] ?? null)
                    ? (string) $item['preview_image_url']
                    : null,
                'source' => (string) ($item['source'] ?? 'database'),
            ];
        }

        return [
            'css' => (string) ($builderState['css'] ?? ''),
            'js' => (string) ($builderState['js'] ?? ''),
            'items' => $items,
        ];
    }
}
