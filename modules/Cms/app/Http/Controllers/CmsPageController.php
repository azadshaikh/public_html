<?php

namespace Modules\Cms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ModuleManager;
use App\Modules\Support\ModuleManifest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Cms\Http\Requests\CmsPageRequest;
use Modules\Cms\Models\CmsPage;

class CmsPageController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $pages = CmsPage::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where('title', 'ilike', sprintf('%%%s%%', $filters['search']))
                        ->orWhere('slug', 'ilike', sprintf('%%%s%%', $filters['search']))
                        ->orWhere('summary', 'ilike', sprintf('%%%s%%', $filters['search']));
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->orderBy('title')
            ->paginate(8)
            ->withQueryString()
            ->through(function (mixed $page): array {
                /** @var CmsPage $page */
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'summary' => $page->summary,
                    'status' => $page->status,
                    'published_at' => $page->published_at?->toDateString(),
                    'is_featured' => $page->is_featured,
                ];
            });

        return Inertia::render('cms/index', [
            'module' => $this->module()->toSharedArray(),
            'filters' => $filters,
            'pages' => $pages,
            'stats' => [
                'total' => CmsPage::query()->count(),
                'published' => CmsPage::query()->where('status', 'published')->count(),
                'draft' => CmsPage::query()->where('status', 'draft')->count(),
                'featured' => CmsPage::query()->where('is_featured', true)->count(),
            ],
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('cms/create', [
            'module' => $this->module()->toSharedArray(),
            'page' => null,
            'initialValues' => CmsPage::defaultFormData(),
            'options' => $this->options(),
        ]);
    }

    public function store(CmsPageRequest $request): RedirectResponse
    {
        CmsPage::query()->create($request->pageAttributes());

        return to_route('cms.index')->with('status', 'Page created.');
    }

    public function edit(CmsPage $cmsPage): Response
    {
        return Inertia::render('cms/edit', [
            'module' => $this->module()->toSharedArray(),
            'page' => [
                'id' => $cmsPage->id,
                'title' => $cmsPage->title,
            ],
            'initialValues' => [
                'title' => $cmsPage->title,
                'slug' => $cmsPage->slug,
                'summary' => $cmsPage->summary ?? '',
                'body' => $cmsPage->body,
                'status' => $cmsPage->status,
                'published_at' => $cmsPage->published_at?->toDateString() ?? '',
                'is_featured' => $cmsPage->is_featured,
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(CmsPageRequest $request, CmsPage $cmsPage): RedirectResponse
    {
        $cmsPage->update($request->pageAttributes());

        return to_route('cms.index')->with('status', 'Page updated.');
    }

    public function destroy(CmsPage $cmsPage): RedirectResponse
    {
        $cmsPage->delete();

        return to_route('cms.index')->with('status', 'Page deleted.');
    }

    /**
     * @return array{search: string, status: string}
     */
    protected function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'status' => $this->sanitizeFilter((string) $request->query('status', ''), array_keys(CmsPage::STATUSES)),
        ];
    }

    /**
     * @return array{statusOptions: array<int, array{value: string, label: string}>}
     */
    protected function options(): array
    {
        return [
            'statusOptions' => collect(CmsPage::STATUSES)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    protected function sanitizeFilter(string $value, array $allowed): string
    {
        $value = trim($value);

        return in_array($value, $allowed, true) ? $value : '';
    }

    protected function module(): ModuleManifest
    {
        return resolve(ModuleManager::class)->findOrFail('cms');
    }
}
