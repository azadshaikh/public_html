<?php

declare(strict_types=1);

namespace Modules\ReleaseManager\Http\Controllers;

use App\Enums\ActivityAction;
use App\Scaffold\ScaffoldController;
use App\Support\CacheInvalidation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Modules\ReleaseManager\Services\ReleaseService;
use RuntimeException;

class ReleaseController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly ReleaseService $releaseService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_releases', only: ['index', 'show']),
            new Middleware('permission:add_releases', only: ['create', 'store', 'getNextVersion']),
            new Middleware('permission:edit_releases', only: ['edit', 'update']),
            new Middleware('permission:delete_releases', only: ['destroy', 'bulkAction', 'forceDelete']),
            new Middleware('permission:restore_releases', only: ['restore']),
        ];
    }

    protected function service(): ReleaseService
    {
        return $this->releaseService;
    }

    protected function inertiaPage(): string
    {
        return 'releasemanager/releases';
    }

    protected function getFormViewData(Model $model): array
    {
        $type = $this->currentType();
        $releaseAt = $model->release_at;

        return [
            'initialValues' => [
                'package_identifier' => $model->package_identifier ?? ($type === 'application' ? 'main' : ''),
                'version' => $model->version ?? '',
                'version_type' => $model->version_type ?? 'minor',
                'status' => $model->status ?? 'draft',
                'release_at' => $releaseAt instanceof \DateTimeInterface ? $releaseAt->format('Y-m-d') : now()->format('Y-m-d'),
                'change_log' => $model->change_log ?? '',
                'release_link' => $model->release_link ?? '',
                'release_type' => $model->release_type ?? $type,
                'file_name' => $model->file_name ?? '',
                'checksum' => $model->checksum ?? '',
                'file_size' => $model->file_size ?? 0,
            ],
            'type' => $type,
            'versionTypes' => config('releasemanager.version_types', []),
            'statusOptions' => config('releasemanager.status_options', []),
        ];
    }

    protected function getIndexViewData(Request $request): array
    {
        return [
            'type' => $this->currentType(),
        ];
    }

    protected function getShowViewData(Model $model): array
    {
        return [
            'type' => (string) ($model->release_type ?? $this->currentType()),
            'statusOptions' => config('releasemanager.status_options', []),
        ];
    }

    protected function getAfterStoreRedirectUrl(Model $model): string
    {
        return $this->typedRoute('edit', ['release' => $model->getKey()]);
    }

    public function getNextVersion(Request $request): JsonResponse
    {
        $type = (string) ($request->route('type') ?? $request->input('type', 'application'));
        $versionType = (string) $request->input('versionType', 'minor');
        $packageId = (string) $request->input('package_identifier', 'main');

        return response()->json([
            'version' => $this->releaseService->generateNextVersion($type, $versionType, $packageId),
        ]);
    }

    public function update(Request $request, int|string $id): RedirectResponse
    {
        $this->enforcePermission('edit');

        $model = $this->findModel($id);
        $previousValues = $this->capturePreviousValues($model);
        $validatedData = $this->validateRequest($request);

        $updatedModel = $this->service()->update($model, $validatedData);

        CacheInvalidation::touchForModel($updatedModel, $this->getEntityName().' updated', $previousValues);

        $this->handleUpdateSideEffectsWithPrevious($updatedModel, $previousValues);
        $this->logActivityWithPreviousValues(
            $updatedModel,
            ActivityAction::UPDATE,
            $this->getEntityName().' updated successfully',
            $previousValues
        );

        return redirect()
            ->to($this->typedRoute('edit', ['release' => $updatedModel->getKey()]))
            ->with('status', $this->buildUpdateSuccessMessage($updatedModel));
    }

    public function destroy(int|string $id): RedirectResponse
    {
        $this->enforcePermission('delete');

        try {
            $model = $this->findModel($id);
        } catch (ModelNotFoundException) {
            return redirect()->to($this->typedIndexUrl())
                ->with('status', $this->getEntityName().' already deleted.');
        }

        if (method_exists($model, 'trashed') && $model->trashed()) {
            return back()
                ->with('error', $this->getEntityName().' is already in trash. Use permanent delete to remove it.');
        }

        $this->service()->delete($model);

        CacheInvalidation::touchForModel($model, $this->getEntityName().' moved to trash');

        $this->handleDeletionSideEffects($model);
        $this->logActivity($model, ActivityAction::DELETE, $this->getEntityName().' moved to trash');

        return redirect()->to($this->typedIndexUrl())
            ->with('status', $this->getEntityName().' moved to trash.');
    }

    public function forceDelete(int|string $id): RedirectResponse
    {
        $this->enforcePermission('delete');

        try {
            $model = $this->findModel($id);
        } catch (ModelNotFoundException) {
            return redirect()->to($this->typedIndexUrl())
                ->with('status', $this->getEntityName().' already deleted.');
        }

        if (! method_exists($model, 'trashed') || ! $model->trashed()) {
            return back()
                ->with('error', $this->getEntityName().' must be in trash before permanent deletion.');
        }

        try {
            $this->service()->forceDelete($model);
        } catch (RuntimeException $runtimeException) {
            return back()
                ->with('error', $runtimeException->getMessage());
        }

        CacheInvalidation::touchForModel($model, $this->getEntityName().' permanently deleted');
        $this->logActivity($model, ActivityAction::FORCE_DELETE, $this->getEntityName().' permanently deleted');

        return redirect()->to($this->typedIndexUrl())
            ->with('status', $this->getEntityName().' permanently deleted.');
    }

    private function typedIndexUrl(): string
    {
        return $this->typedRoute('index');
    }

    private function typedRoute(string $routeSuffix, array $parameters = []): string
    {
        return route($this->routeNamespace().'.'.$routeSuffix, $parameters);
    }

    private function routeNamespace(): string
    {
        return $this->currentType() === 'module'
            ? 'releasemanager.module'
            : 'releasemanager.application';
    }

    private function currentType(): string
    {
        return (string) (request()->route('type') ?? request()->input('type', 'application'));
    }
}
