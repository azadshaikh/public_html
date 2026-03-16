<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\Redirection;
use Modules\CMS\Services\RedirectionService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RedirectionsController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly RedirectionService $redirectionService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_redirections', only: ['index', 'data', 'export', 'test']),
            new Middleware('permission:add_redirections', only: ['create', 'store', 'importForm', 'import']),
            new Middleware('permission:edit_redirections', only: ['edit', 'update']),
            new Middleware('permission:restore_redirections', only: ['restore']),
            new Middleware('permission:delete_redirections', only: ['destroy', 'bulkAction', 'forceDelete']),
        ];
    }

    /**
     * Export redirections to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $status = $request->query('status', 'all');

        return $this->redirectionService->exportToCsv($status);
    }

    /**
     * Show import form.
     */
    public function importForm(): Response
    {
        return Inertia::render($this->inertiaPage().'/import');
    }

    /**
     * Process CSV import.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'skip_duplicates' => ['boolean'],
            'update_existing' => ['boolean'],
        ]);

        $result = $this->redirectionService->importFromCsv(
            $request->file('file'),
            $request->boolean('skip_duplicates', true),
            $request->boolean('update_existing', false)
        );

        if ($result['errors'] > 0) {
            return to_route('cms.redirections.index')
                ->with('warning', sprintf('Import completed with issues: %s created, %s updated, %s skipped, %s errors.', $result['created'], $result['updated'], $result['skipped'], $result['errors']))
                ->with('import_errors', $result['error_details']);
        }

        return to_route('cms.redirections.index')
            ->with('success', sprintf('Import completed: %s created, %s updated, %s skipped.', $result['created'], $result['updated'], $result['skipped']));
    }

    /**
     * Test a redirect rule.
     */
    public function test(Request $request): JsonResponse
    {
        $request->validate([
            'source_url' => ['required', 'string'],
            'match_type' => ['required', 'in:exact,wildcard,regex'],
            'test_path' => ['required', 'string'],
        ]);

        $result = $this->redirectionService->testRedirect(
            $request->input('source_url'),
            $request->input('match_type'),
            $request->input('test_path')
        );

        return response()->json($result);
    }

    protected function service(): RedirectionService
    {
        return $this->redirectionService;
    }

    protected function inertiaPage(): string
    {
        return 'cms/redirections';
    }

    protected function getFormViewData(Model $model): array
    {
        return [
            'initialValues' => $this->buildInitialValues($model),
            'statusOptions' => $this->redirectionService->getStatusOptions(),
            'redirectTypeOptions' => $this->redirectionService->getRedirectTypeOptions(),
            'urlTypeOptions' => $this->redirectionService->getUrlTypeOptions(),
            'matchTypeOptions' => $this->redirectionService->getMatchTypeOptions(),
            'baseUrl' => rtrim(url('/'), '/'),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Redirection $model */
        $sourceUrl = (string) $model->source_url;
        $normalizedSourceUrl = $sourceUrl === ''
            ? '/'
            : (str_starts_with($sourceUrl, '/') ? $sourceUrl : '/'.$sourceUrl);

        return [
            'id' => $model->getKey(),
            'source_url' => $sourceUrl,
            'target_url' => (string) $model->target_url,
            'match_type' => (string) $model->match_type,
            'url_type' => (string) $model->url_type,
            'hits' => (int) ($model->hits ?? 0),
            'last_hit_at_formatted' => $model->last_hit_at
                ? app_date_time_format($model->last_hit_at, 'datetime')
                : null,
            'last_hit_at_human' => $model->last_hit_at?->diffForHumans(),
            'created_at_formatted' => $model->created_at
                ? app_date_time_format($model->created_at, 'datetime')
                : null,
            'created_at_human' => $model->created_at?->diffForHumans(),
            'preview_url' => $model->match_type === 'exact'
                ? rtrim(url('/'), '/').$normalizedSourceUrl
                : null,
        ];
    }

    protected function handleBulkActionSideEffects(string $action, array $ids): void
    {
        $this->redirectionService->flushCache();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInitialValues(Model $model): array
    {
        /** @var Redirection $model */
        return [
            'source_url' => (string) ($model->source_url ?? ''),
            'target_url' => (string) ($model->target_url ?? ''),
            'redirect_type' => (string) ($model->redirect_type ?? '301'),
            'url_type' => (string) ($model->url_type ?? 'internal'),
            'match_type' => (string) ($model->match_type ?? 'exact'),
            'status' => (string) ($model->status ?? 'active'),
            'notes' => (string) ($model->notes ?? ''),
            'expires_at' => $model->expires_at?->format('Y-m-d\TH:i') ?? '',
        ];
    }
}
