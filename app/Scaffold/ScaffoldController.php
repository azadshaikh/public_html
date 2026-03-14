<?php

declare(strict_types=1);

namespace App\Scaffold;

use App\Contracts\ScaffoldServiceInterface;
use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Support\CacheInvalidation;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use RuntimeException;

/**
 * ScaffoldController - Convention-based Inertia CRUD controller.
 *
 * Provides complete CRUD functionality via Inertia::render().
 * All configuration is derived from the ScaffoldDefinition via the service.
 *
 * Subclasses must implement:
 * - service(): returns the ScaffoldServiceInterface implementation
 * - inertiaPage(): returns the Inertia page component path prefix (e.g., 'users')
 *
 * @example
 * class UserController extends ScaffoldController
 * {
 *     public function __construct(private readonly UserService $userService) {}
 *
 *     protected function service(): UserService { return $this->userService; }
 *
 *     protected function inertiaPage(): string { return 'users'; }
 * }
 */
abstract class ScaffoldController extends Controller
{
    use ActivityTrait;

    // =========================================================================
    // CRUD ACTIONS
    // =========================================================================

    /**
     * Display a listing of the resource via Inertia.
     *
     * Inertia handles re-fetching on filter/sort/paginate interactions
     * via router.get() with preserveState, so no separate data() endpoint is needed.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $this->enforcePermission('view');

        $data = $this->service()->getData($request);

        return Inertia::render($this->inertiaPage().'/index', [
            'config' => $this->service()->getScaffoldDefinition()->toInertiaConfig(),
            ...$data,
            ...$this->getIndexViewData($request),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->enforcePermission('add');

        $modelClass = $this->getModelClass();
        $model = new $modelClass;

        return Inertia::render($this->inertiaPage().'/create', [
            ...$this->getFormViewData($model),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->enforcePermission('add');

        $validatedData = $this->validateRequest($request);
        $model = $this->service()->create($validatedData);

        CacheInvalidation::touchForModel($model, $this->getEntityName().' created');

        $this->handleCreationSideEffects($model);
        $this->logActivity($model, ActivityAction::CREATE, $this->getEntityName().' created successfully');

        return redirect()
            ->to($this->getAfterStoreRedirectUrl($model))
            ->with('status', $this->buildCreateSuccessMessage($model));
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $id): Response
    {
        $this->enforcePermission('view');

        $model = $this->findModel($id);

        return Inertia::render($this->inertiaPage().'/show', [
            $this->getModelKey() => $this->transformModelForShow($model),
            ...$this->getShowViewData($model),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int|string $id): Response
    {
        $this->enforcePermission('edit');

        $model = $this->findModel($id);

        return Inertia::render($this->inertiaPage().'/edit', [
            $this->getModelKey() => $this->transformModelForEdit($model),
            ...$this->getFormViewData($model),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
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

        return to_route($this->scaffold()->getEditRoute(), $updatedModel)
            ->with('status', $this->buildUpdateSuccessMessage($updatedModel));
    }

    /**
     * Remove the specified resource from storage (soft delete only).
     */
    public function destroy(int|string $id): RedirectResponse
    {
        $this->enforcePermission('delete');

        try {
            $model = $this->findModel($id);
        } catch (ModelNotFoundException) {
            return to_route($this->scaffold()->getIndexRoute())
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

        return to_route($this->scaffold()->getIndexRoute())
            ->with('status', $this->getEntityName().' moved to trash.');
    }

    /**
     * Restore a soft-deleted resource.
     */
    public function restore(int|string $id): RedirectResponse
    {
        $this->enforcePermission('restore');

        $model = $this->service()->restore($id);

        CacheInvalidation::touchForModel($model, $this->getEntityName().' restored');

        $this->handleRestorationSideEffects($model);
        $this->logActivity($model, ActivityAction::RESTORE, $this->getEntityName().' restored');

        return back()
            ->with('status', $this->getEntityName().' restored successfully.');
    }

    /**
     * Permanently delete a resource (force delete from trash only).
     */
    public function forceDelete(int|string $id): RedirectResponse
    {
        $this->enforcePermission('delete');

        try {
            $model = $this->findModel($id);
        } catch (ModelNotFoundException) {
            return to_route($this->scaffold()->getIndexRoute())
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

        return to_route($this->scaffold()->getIndexRoute())
            ->with('status', $this->getEntityName().' permanently deleted.');
    }

    /**
     * Handle bulk actions.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => ['required', 'string'],
            'ids' => ['required_without:select_all', 'array'],
            'ids.*' => ['required'],
            'select_all' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->service()->handleBulkAction($request);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        CacheInvalidation::touch('Bulk '.$request->input('action').': '.$this->getEntityPlural());

        $this->handleBulkActionSideEffects(
            $request->input('action'),
            $request->boolean('select_all') ? [] : $request->input('ids', [])
        );

        $this->logActivity(
            new ($this->getModelClass()),
            $this->getBulkActionType($request->input('action')),
            sprintf('Bulk %s: %d %s affected', $request->input('action'), $result['affected'], $this->getEntityPlural())
        );

        return back()->with('status', $result['message']);
    }

    // =========================================================================
    // ABSTRACT METHODS
    // =========================================================================

    /**
     * Get the service instance.
     */
    abstract protected function service(): ScaffoldServiceInterface;

    /**
     * Get the Inertia page component path prefix.
     *
     * Returns the base path for this resource's page components.
     * For example, 'users' maps to pages: users/index, users/create, users/edit, users/show.
     */
    abstract protected function inertiaPage(): string;

    // =========================================================================
    // CONFIGURATION (All derived from service/scaffold definition)
    // =========================================================================

    /**
     * Get scaffold definition from service (uses service cache).
     */
    protected function scaffold(): ScaffoldDefinition
    {
        return $this->service()->scaffold();
    }

    /**
     * Get model class from scaffold.
     */
    protected function getModelClass(): string
    {
        return $this->service()->getModelClass();
    }

    /**
     * Get entity name from scaffold.
     */
    protected function getEntityName(): string
    {
        return $this->service()->getEntityName();
    }

    /**
     * Get entity plural name from scaffold.
     */
    protected function getEntityPlural(): string
    {
        return $this->service()->getEntityPlural();
    }

    /**
     * Get route prefix from scaffold.
     */
    protected function getRoutePrefix(): string
    {
        return $this->scaffold()->getRoutePrefix();
    }

    // =========================================================================
    // RESPONSE BUILDERS
    // =========================================================================

    /**
     * Build success message for create operation.
     */
    protected function buildCreateSuccessMessage(Model $model): string
    {
        return $this->getEntityName().' created successfully.';
    }

    /**
     * Build success message for update operation.
     */
    protected function buildUpdateSuccessMessage(Model $model): string
    {
        return $this->getEntityName().' updated successfully.';
    }

    // =========================================================================
    // MODEL TRANSFORMERS
    // =========================================================================

    /**
     * Transform a model for the show page.
     *
     * Override in subclass to control what data the show page receives.
     *
     * @return array<string, mixed>
     */
    protected function transformModelForShow(Model $model): array
    {
        return $model->toArray();
    }

    /**
     * Transform a model for the edit form.
     *
     * Override in subclass to shape the form's initial values.
     *
     * @return array<string, mixed>
     */
    protected function transformModelForEdit(Model $model): array
    {
        return $model->toArray();
    }

    // =========================================================================
    // HOOKS (Override in subclass for custom behavior)
    // =========================================================================

    /**
     * Get the redirect URL after storing a new resource.
     * Priority: show > edit > index
     */
    protected function getAfterStoreRedirectUrl(Model $model): string
    {
        // Try show route first
        $showRoute = $this->scaffold()->getShowRoute();
        if ($showRoute) {
            try {
                return route($showRoute, $model);
            } catch (Exception) {
                // Route doesn't exist, try next
            }
        }

        // Fallback to edit route
        $editRoute = $this->scaffold()->getEditRoute();
        if ($editRoute) {
            try {
                return route($editRoute, $model);
            } catch (Exception) {
                // Route doesn't exist, use index
            }
        }

        // Final fallback to index route
        return route($this->scaffold()->getIndexRoute());
    }

    /**
     * Hook: Additional data for the index page.
     *
     * @return array<string, mixed>
     */
    protected function getIndexViewData(Request $request): array
    {
        return [];
    }

    /**
     * Hook: Additional data for the show page.
     *
     * @return array<string, mixed>
     */
    protected function getShowViewData(Model $model): array
    {
        return [];
    }

    /**
     * Hook: Additional data for create/edit forms (options, defaults, etc.).
     *
     * @return array<string, mixed>
     */
    protected function getFormViewData(Model $model): array
    {
        return [];
    }

    /**
     * Hook: Called after model creation.
     */
    protected function handleCreationSideEffects(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook: Called after model update.
     */
    protected function handleUpdateSideEffects(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook: Called after model update, with access to previous values.
     */
    protected function handleUpdateSideEffectsWithPrevious(Model $model, array $previousValues): void
    {
        $this->handleUpdateSideEffects($model);
    }

    /**
     * Hook: Called after model deletion.
     */
    protected function handleDeletionSideEffects(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook: Called after model restoration.
     */
    protected function handleRestorationSideEffects(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook: Called after bulk action
     */
    protected function handleBulkActionSideEffects(string $action, array $ids): void
    {
        // Override in subclass
    }

    /**
     * Capture previous values for activity logging.
     *
     * @param  Model  $model  The model before update
     * @return array<string, mixed>
     */
    protected function capturePreviousValues(Model $model): array
    {
        $attributes = $model->getAttributes();
        $hidden = $model->getHidden();

        return array_diff_key($attributes, array_flip($hidden));
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get model key for page props (e.g., 'user' for User model).
     */
    protected function getModelKey(): string
    {
        return str($this->getEntityName())->camel()->toString();
    }

    /**
     * Find model by ID or route key, including trashed records.
     */
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

    /**
     * Defense-in-depth permission check based on scaffold permissionPrefix.
     */
    protected function enforcePermission(string $ability): void
    {
        $prefix = $this->scaffold()->getPermissionPrefix();

        if ($prefix === '') {
            return;
        }

        $permission = sprintf('%s_%s', $ability, $prefix);

        abort_if(! Auth::check() || ! Auth::user()->can($permission), 403);
    }

    /**
     * Validate request using the definition's FormRequest class.
     */
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

    /**
     * Map bulk action to activity action type.
     */
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
