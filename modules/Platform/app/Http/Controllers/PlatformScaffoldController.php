<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Controllers;

use App\Contracts\ScaffoldServiceInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Scaffold\ScaffoldDefinition;
use App\Support\CacheInvalidation;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use LogicException;
use RuntimeException;

abstract class PlatformScaffoldController extends Controller
{
    use ActivityTrait;

    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $this->enforcePermission('view');

        $data = $this->service()->getData($request);

        return view($this->scaffold()->getIndexView(), [
            'config' => method_exists($this->service(), 'getDataGridConfig')
                ? $this->service()->getDataGridConfig()
                : $this->service()->getScaffoldDefinition()->toDataGridConfig(),
            'statistics' => $data['statistics'] ?? [],
            'initialData' => $data,
            'status' => session('status'),
            'error' => session('error'),
            ...$this->getIndexViewData($request),
        ]);
    }

    public function create(): View
    {
        $this->enforcePermission('add');

        $modelClass = $this->getModelClass();
        $model = new $modelClass;

        return view($this->scaffold()->getCreateView(), [
            $this->getModelKey() => $model->exists ? $model : null,
            ...$this->getFormViewData($model),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->enforcePermission('add');

        $validatedData = $this->validateRequest($request);
        $model = $this->service()->create($validatedData);

        CacheInvalidation::touchForModel($model, $this->getEntityName().' created');

        $this->handleCreationSideEffects($model);
        $this->logActivity($model, ActivityAction::CREATE, $this->getEntityName().' created successfully');

        return redirect()
            ->to($this->getAfterStoreRedirectUrl($model))
            ->with('success', $this->buildCreateSuccessMessage($model));
    }

    public function show(int|string $id): View|JsonResponse
    {
        $this->enforcePermission('view');

        $model = $this->findModel($id);

        return view($this->scaffold()->getShowView(), [
            $this->getModelKey() => $model,
            ...$this->getShowViewData($model),
        ]);
    }

    public function edit(int|string $id): View
    {
        $this->enforcePermission('edit');

        $model = $this->findModel($id);

        return view($this->scaffold()->getEditView(), [
            $this->getModelKey() => $model,
            ...$this->getFormViewData($model),
        ]);
    }

    public function update(Request $request, int|string $id): RedirectResponse|JsonResponse
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

        return to_route($this->scaffold()->getEditRoute(), $updatedModel)
            ->with('success', $this->buildUpdateSuccessMessage($updatedModel));
    }

    public function destroy(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $this->enforcePermission('delete');

        try {
            $model = $this->findModel($id);
        } catch (ModelNotFoundException) {
            return $request->expectsJson()
                ? response()->json(['status' => 'success', 'message' => $this->getEntityName().' already deleted.'])
                : to_route($this->scaffold()->getIndexRoute())->with('success', $this->getEntityName().' already deleted.');
        }

        if (method_exists($model, 'trashed') && $model->trashed()) {
            $message = $this->getEntityName().' is already in trash. Use permanent delete to remove it.';

            return $request->expectsJson()
                ? response()->json(['status' => 'error', 'message' => $message], 422)
                : back()->with('error', $message);
        }

        $this->service()->delete($model);

        CacheInvalidation::touchForModel($model, $this->getEntityName().' moved to trash');

        $this->handleDeletionSideEffects($model);
        $this->logActivity($model, ActivityAction::DELETE, $this->getEntityName().' moved to trash');

        $message = $this->getEntityName().' moved to trash.';

        return $request->expectsJson()
            ? response()->json(['status' => 'success', 'message' => $message])
            : to_route($this->scaffold()->getIndexRoute())->with('success', $message);
    }

    public function restore(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $this->enforcePermission('restore');

        $model = $this->service()->restore($id);

        CacheInvalidation::touchForModel($model, $this->getEntityName().' restored');

        $this->handleRestorationSideEffects($model);
        $this->logActivity($model, ActivityAction::RESTORE, $this->getEntityName().' restored');

        $message = $this->getEntityName().' restored successfully.';

        return $request->expectsJson()
            ? response()->json(['status' => 'success', 'message' => $message])
            : back()->with('success', $message);
    }

    public function forceDelete(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $this->enforcePermission('delete');

        try {
            $model = $this->findModel($id);
        } catch (ModelNotFoundException) {
            return $request->expectsJson()
                ? response()->json(['status' => 'success', 'message' => $this->getEntityName().' already deleted.'])
                : to_route($this->scaffold()->getIndexRoute())->with('success', $this->getEntityName().' already deleted.');
        }

        if (! method_exists($model, 'trashed') || ! $model->trashed()) {
            $message = $this->getEntityName().' must be in trash before permanent deletion.';

            return $request->expectsJson()
                ? response()->json(['status' => 'error', 'message' => $message], 422)
                : back()->with('error', $message);
        }

        try {
            $this->service()->forceDelete($model);
        } catch (RuntimeException $runtimeException) {
            return $request->expectsJson()
                ? response()->json(['status' => 'error', 'message' => $runtimeException->getMessage()], 422)
                : back()->with('error', $runtimeException->getMessage());
        }

        CacheInvalidation::touchForModel($model, $this->getEntityName().' permanently deleted');
        $this->logActivity($model, ActivityAction::FORCE_DELETE, $this->getEntityName().' permanently deleted');

        $message = $this->getEntityName().' permanently deleted.';

        return $request->expectsJson()
            ? response()->json(['status' => 'success', 'message' => $message])
            : to_route($this->scaffold()->getIndexRoute())->with('success', $message);
    }

    public function bulkAction(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string'],
            'ids' => ['required_without:select_all', 'array'],
            'ids.*' => ['required'],
            'select_all' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->service()->handleBulkAction($request);
        } catch (RuntimeException $runtimeException) {
            return $request->expectsJson()
                ? response()->json(['status' => 'error', 'message' => $runtimeException->getMessage(), 'affected' => 0], 422)
                : back()->with('error', $runtimeException->getMessage());
        }

        CacheInvalidation::touch('Bulk '.$request->input('action').': '.$this->getEntityPlural());

        $this->handleBulkActionSideEffects(
            $request->input('action'),
            $request->boolean('select_all') ? [] : $request->input('ids', [])
        );

        $this->logActivity(
            new ($this->getModelClass()),
            $this->getBulkActionType($request->input('action')),
            sprintf('Bulk %s: %d %s affected', $request->input('action'), $result['affected'] ?? 0, $this->getEntityPlural())
        );

        return $request->expectsJson()
            ? response()->json(['status' => 'success', 'message' => $result['message'], 'affected' => $result['affected'] ?? 0])
            : back()->with('success', $result['message']);
    }

    abstract protected function service(): ScaffoldServiceInterface;

    protected function scaffold(): ScaffoldDefinition
    {
        return $this->service()->scaffold();
    }

    protected function getModelClass(): string
    {
        return $this->service()->getModelClass();
    }

    protected function getEntityName(): string
    {
        return $this->service()->getEntityName();
    }

    protected function getEntityPlural(): string
    {
        return $this->service()->getEntityPlural();
    }

    protected function buildCreateSuccessMessage(Model $model): string
    {
        return $this->getEntityName().' created successfully.';
    }

    protected function buildUpdateSuccessMessage(Model $model): string
    {
        return $this->getEntityName().' updated successfully.';
    }

    protected function getAfterStoreRedirectUrl(Model $model): string
    {
        $showRoute = $this->scaffold()->getShowRoute();

        if ($showRoute) {
            try {
                return route($showRoute, $model);
            } catch (Exception) {
            }
        }

        $editRoute = $this->scaffold()->getEditRoute();

        if ($editRoute) {
            try {
                return route($editRoute, $model);
            } catch (Exception) {
            }
        }

        return route($this->scaffold()->getIndexRoute());
    }

    protected function getIndexViewData(Request $request): array
    {
        return [];
    }

    protected function getShowViewData(Model $model): array
    {
        return [];
    }

    protected function getFormViewData(Model $model): array
    {
        return [];
    }

    protected function handleCreationSideEffects(Model $model): void {}

    protected function handleUpdateSideEffects(Model $model): void {}

    protected function handleUpdateSideEffectsWithPrevious(Model $model, array $previousValues): void
    {
        $this->handleUpdateSideEffects($model);
    }

    protected function handleDeletionSideEffects(Model $model): void {}

    protected function handleRestorationSideEffects(Model $model): void {}

    protected function handleBulkActionSideEffects(string $action, array $ids): void {}

    protected function capturePreviousValues(Model $model): array
    {
        $attributes = $model->getAttributes();
        $hidden = $model->getHidden();

        return array_diff_key($attributes, array_flip($hidden));
    }

    protected function getModelKey(): string
    {
        return str($this->getEntityName())->camel()->toString();
    }

    protected function findModel(int|string $id): Model
    {
        $service = $this->service();

        if (method_exists($service, 'findModelForCrud')) {
            return $service->findModelForCrud($id, request());
        }

        $modelClass = $this->getModelClass();
        $model = new $modelClass;
        $routeKeyName = $model->getRouteKeyName();

        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query->where($routeKeyName, $id)->firstOrFail();
    }

    protected function enforcePermission(string $ability): void
    {
        if ($this->scaffold()->requiresSuperUserAccess()) {
            return;
        }

        $prefix = $this->scaffold()->getPermissionPrefix();

        if ($prefix === '') {
            return;
        }

        $permission = sprintf('%s_%s', $ability, $prefix);

        abort_if(! Auth::check() || ! Auth::user()->can($permission), 403);
    }

    protected function validateRequest(Request $request): array
    {
        $definition = $this->scaffold();
        $requestClass = $definition->getRequestClass();

        if ($requestClass && class_exists($requestClass)) {
            /** @var FormRequest $formRequest */
            $formRequest = $requestClass::createFrom($request);
            $formRequest->setContainer(app());
            $formRequest->setRedirector(resolve(Redirector::class));
            $formRequest->setRouteResolver($request->getRouteResolver());
            $formRequest->setUserResolver($request->getUserResolver());
            $formRequest->validateResolved();

            return $formRequest->validated();
        }

        throw new LogicException(
            sprintf("Scaffold validation is required. Definition for '%s' must return a valid FormRequest class from getRequestClass().", $this->getEntityName())
        );
    }

    protected function getBulkActionType(string $action): ActivityAction
    {
        return match ($action) {
            'delete' => ActivityAction::BULK_DELETE,
            'force_delete' => ActivityAction::BULK_FORCE_DELETE,
            'restore' => ActivityAction::BULK_RESTORE,
            default => ActivityAction::BULK_DELETE,
        };
    }
}
