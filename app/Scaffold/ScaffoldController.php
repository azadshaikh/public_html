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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use LogicException;
use RuntimeException;

/**
 * ScaffoldController - Convention-based CRUD controller
 *
 * Provides complete CRUD functionality with ZERO abstract methods.
 * All configuration is derived from the ScaffoldDefinition via the service.
 *
 * Response format for DataGrid:
 * {
 *     status: 'success',
 *     data: {
 *         items: [...],
 *         pagination: {...},
 *         columns: [...],
 *         filters: [...],
 *         bulk_actions: [...],
 *         statistics: {...},
 *         empty_state_config: {...}
 *     }
 * }
 *
 * @example
 * class AddressController extends ScaffoldController
 * {
 *     public function __construct(private readonly AddressService $service)
 *     {
 *         // That's it! Everything auto-configured.
 *     }
 *
 *     protected function service(): AddressService
 *     {
 *         return $this->service;
 *     }
 *
 *     // Optional: customize side effects
 *     protected function handleCreationSideEffects(Model $model): void
 *     {
 *         // Dispatch events, clear cache, etc.
 *     }
 * }
 */
abstract class ScaffoldController extends Controller
{
    use ActivityTrait;

    // =========================================================================
    // CRUD ACTIONS
    // =========================================================================

    /**
     * Display a listing of the resource (HTML view or JSON for AJAX)
     * Note: RedirectResponse is allowed for nested resources that redirect to parent
     *
     * Server-Side Rendering: Initial page load includes data directly,
     * so the DataGrid renders immediately without an additional AJAX call.
     * AJAX is only used for subsequent interactions (filters, pagination, etc.)
     */
    public function index(Request $request): View|JsonResponse|RedirectResponse
    {
        $this->enforcePermission('view');

        // Return JSON for AJAX requests (DataGrid interactions: filter, paginate, etc.)
        if ($request->ajax() || $request->wantsJson()) {
            $data = $this->service()->getData($request);

            return $this->buildDataGridResponse($data);
        }

        // For initial HTML page load, include data for immediate rendering
        // This avoids a "loading" state and extra round-trip to the server
        $data = $this->service()->getData($request);

        return view($this->scaffold()->getIndexView(), [
            'config' => $this->service()->getDataGridConfig(),
            'initialData' => $data, // Pass data for server-side rendering
        ]);
    }

    /**
     * Get data for DataGrid (JSON API endpoint)
     */
    public function data(Request $request): JsonResponse
    {
        $this->enforcePermission('view');

        $data = $this->service()->getData($request);

        return $this->buildDataGridResponse($data);
    }

    /**
     * Show the form for creating a new resource
     */
    /**
     * Show the form for creating a new resource
     */
    public function create(): View|RedirectResponse
    {
        $this->enforcePermission('add');

        $modelClass = $this->getModelClass();
        $model = new $modelClass;

        return view($this->scaffold()->getCreateView(), [
            $this->getModelKey() => $model,
            ...$this->getFormViewData($model),
        ]);
    }

    /**
     * Store a newly created resource in storage
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->enforcePermission('add');

        $validatedData = $this->validateRequest($request);
        $model = $this->service()->create($validatedData);

        // Invalidate caches only for public-affecting records.
        CacheInvalidation::touchForModel($model, $this->getEntityName().' created');

        $this->handleCreationSideEffects($model);
        $this->logActivity($model, ActivityAction::CREATE, $this->getEntityName().' created successfully');

        $successMessage = $this->buildCreateSuccessMessage($model);

        // Determine redirect route: show > edit > index
        $redirectRoute = $this->getAfterStoreRedirectUrl($model);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMessage,
                'data' => ['id' => $model->getKey()],
                'redirect' => $redirectRoute,
            ], 201);
        }

        return redirect()
            ->to($redirectRoute)
            ->with('success', $successMessage);
    }

    /**
     * Display the specified resource
     */
    public function show(int|string $id): View|JsonResponse|RedirectResponse
    {
        $this->enforcePermission('view');

        $model = $this->findModel($id);

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'data' => $model,
            ]);
        }

        return view($this->scaffold()->getShowView(), [
            $this->getModelKey() => $model,
        ]);
    }

    /**
     * Show the form for editing the specified resource
     */
    public function edit(int|string $id): View|RedirectResponse
    {
        $this->enforcePermission('edit');

        $model = $this->findModel($id);

        return view($this->scaffold()->getEditView(), [
            $this->getModelKey() => $model,
            ...$this->getFormViewData($model),
        ]);
    }

    /**
     * Update the specified resource in storage
     */
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

        $successMessage = $this->buildUpdateSuccessMessage($updatedModel);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $successMessage,
                'redirect' => route($this->scaffold()->getEditRoute(), $updatedModel),
            ]);
        }

        return to_route($this->scaffold()->getEditRoute(), $updatedModel)
            ->with('success', $successMessage);
    }

    /**
     * Remove the specified resource from storage (soft delete only)
     *
     * For permanent deletion, use the dedicated forceDelete() endpoint.
     */
    public function destroy(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $this->enforcePermission('delete');

        try {
            $model = $this->findModel($id);
        } catch (ModelNotFoundException) {
            $message = $this->getEntityName().' already deleted';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $message,
                    'redirect' => route($this->scaffold()->getIndexRoute()),
                ]);
            }

            return to_route($this->scaffold()->getIndexRoute())
                ->with('success', $message);
        }

        // If already trashed, redirect to forceDelete endpoint instead
        if (method_exists($model, 'trashed') && $model->trashed()) {
            $message = $this->getEntityName().' is already in trash. Use permanent delete to remove it.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 400);
            }

            return back()
                ->with('warning', $message);
        }

        // Soft delete only
        $this->service()->delete($model);
        $message = $this->getEntityName().' moved to trash';

        CacheInvalidation::touchForModel($model, $message);

        $this->handleDeletionSideEffects($model);
        $this->logActivity($model, ActivityAction::DELETE, $message);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'redirect' => route($this->scaffold()->getIndexRoute()),
            ]);
        }

        return to_route($this->scaffold()->getIndexRoute())
            ->with('success', $message);
    }

    /**
     * Restore a soft-deleted resource
     */
    public function restore(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $this->enforcePermission('restore');

        $model = $this->service()->restore($id);

        CacheInvalidation::touchForModel($model, $this->getEntityName().' restored');

        $this->handleRestorationSideEffects($model);
        $this->logActivity($model, ActivityAction::RESTORE, $this->getEntityName().' restored');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $this->getEntityName().' restored successfully',
                'redirect' => route($this->scaffold()->getIndexRoute()),
            ]);
        }

        return back()
            ->with('success', $this->getEntityName().' restored successfully');
    }

    /**
     * Permanently delete a resource (force delete)
     * This is a dedicated endpoint for force deletion from trash
     */
    public function forceDelete(Request $request, int|string $id): RedirectResponse|JsonResponse
    {
        $this->enforcePermission('delete');

        try {
            $model = $this->findModel($id);
        } catch (ModelNotFoundException) {
            $message = $this->getEntityName().' already deleted';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $message,
                    'redirect' => route($this->scaffold()->getIndexRoute()),
                ]);
            }

            return to_route($this->scaffold()->getIndexRoute())
                ->with('success', $message);
        }

        // Ensure the model is trashed before force deleting
        if (! method_exists($model, 'trashed') || ! $model->trashed()) {
            $message = $this->getEntityName().' must be in trash before permanent deletion';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 400);
            }

            return back()
                ->with('error', $message);
        }

        try {
            $this->service()->forceDelete($model);
        } catch (RuntimeException $runtimeException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $runtimeException->getMessage(),
                ], 422);
            }

            return back()
                ->with('error', $runtimeException->getMessage());
        }

        CacheInvalidation::touchForModel($model, $this->getEntityName().' permanently deleted');
        $this->logActivity($model, ActivityAction::FORCE_DELETE, $this->getEntityName().' permanently deleted');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $this->getEntityName().' permanently deleted',
                'redirect' => route($this->scaffold()->getIndexRoute()),
            ]);
        }

        return to_route($this->scaffold()->getIndexRoute())
            ->with('success', $this->getEntityName().' permanently deleted');
    }

    /**
     * Handle bulk actions (DataGrid format)
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string'],
            'ids' => ['required_without:select_all', 'array'],
            'ids.*' => ['required'],  // Accept any scalar ID (int, UUID, slug)
            'select_all' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->service()->handleBulkAction($request);
        } catch (RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
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

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'affected' => $result['affected'],
        ]);
    }

    /**
     * Get the service instance
     * This is the ONLY method that must be implemented
     */
    abstract protected function service(): ScaffoldServiceInterface;

    // =========================================================================
    // CONFIGURATION (All derived from service/scaffold definition)
    // =========================================================================

    /**
     * Get scaffold definition from service (uses service cache)
     */
    protected function scaffold(): ScaffoldDefinition
    {
        return $this->service()->scaffold();
    }

    /**
     * Get model class from scaffold
     */
    protected function getModelClass(): string
    {
        return $this->service()->getModelClass();
    }

    /**
     * Get entity name from scaffold
     */
    protected function getEntityName(): string
    {
        return $this->service()->getEntityName();
    }

    /**
     * Get entity plural name from scaffold
     */
    protected function getEntityPlural(): string
    {
        return $this->service()->getEntityPlural();
    }

    /**
     * Get route prefix from scaffold
     */
    protected function getRoutePrefix(): string
    {
        return $this->scaffold()->getRoutePrefix();
    }

    // =========================================================================
    // RESPONSE BUILDERS (DataGrid Format)
    // =========================================================================

    /**
     * Build JSON response for DataGrid
     */
    protected function buildDataGridResponse(array $data): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Build success message for create operation
     */
    protected function buildCreateSuccessMessage(Model $model): array
    {
        return [
            'title' => $this->getEntityName().' Created!',
            'message' => sprintf('Your %s has been created successfully.', $this->getEntityName()),
        ];
    }

    /**
     * Build success message for update operation
     */
    protected function buildUpdateSuccessMessage(Model $model): array
    {
        return [
            'title' => $this->getEntityName().' Updated!',
            'message' => 'All changes have been saved successfully.',
        ];
    }

    // =========================================================================
    // HOOKS (Override in subclass for custom behavior)
    // =========================================================================

    /**
     * Get the redirect URL after storing a new resource
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
     * Hook: Called after model creation
     */
    protected function handleCreationSideEffects(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook: Called after model update
     */
    protected function handleUpdateSideEffects(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook: Called after model update, with access to previous values.
     * Default behavior calls handleUpdateSideEffects() for backward compatibility.
     */
    protected function handleUpdateSideEffectsWithPrevious(Model $model, array $previousValues): void
    {
        $this->handleUpdateSideEffects($model);
    }

    /**
     * Hook: Called after model deletion
     */
    protected function handleDeletionSideEffects(Model $model): void
    {
        // Override in subclass
    }

    /**
     * Hook: Called after model restoration
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
     * Get additional data for create/edit views
     * Override in subclass to add select options, etc.
     */
    protected function getFormViewData(Model $model): array
    {
        return [];
    }

    /**
     * Capture previous values for activity logging
     *
     * By default, captures all attributes to ensure we have a complete baseline
     * for comparison against the post-update state.
     *
     * @param  Model  $model  The model before update
     * @return array<string, mixed> Previous values keyed by field name
     */
    protected function capturePreviousValues(Model $model): array
    {
        // Capture ALL attributes (except hidden) to ensure we have value for every field
        // This avoids "old: null" issues for non-fillable fields (like id, timestamps)
        // when comparing against the full post-update state.
        $attributes = $model->getAttributes();
        $hidden = $model->getHidden();

        return array_diff_key($attributes, array_flip($hidden));
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Get model key for views (e.g., 'address' for Address model)
     */
    protected function getModelKey(): string
    {
        return str($this->getEntityName())->camel()->toString();
    }

    /**
     * Find model by ID or route key (includes trashed items for show/edit of soft-deleted records)
     *
     * Supports integer IDs, UUIDs, and slug-based route keys.
     */
    protected function findModel(int|string $id): Model
    {
        // Prefer service-scoped lookup (prevents bypassing visibility/tenant scopes).
        $service = $this->service();
        if (method_exists($service, 'findModelForCrud')) {
            return $service->findModelForCrud($id, request());
        }

        $modelClass = $this->getModelClass();
        $model = new $modelClass;
        $routeKeyName = $model->getRouteKeyName();

        // Build query with trashed support if model uses SoftDeletes.
        // Note: `withTrashed()` is a forwarded builder method, so `method_exists($modelClass, 'withTrashed')`
        // is false even when SoftDeletes is present.
        $query = $modelClass::query();

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        // Use the model's route key name for lookup (supports id, uuid, slug, etc.)
        return $query->where($routeKeyName, $id)->firstOrFail();
    }

    /**
     * Defense-in-depth permission check based on scaffold permissionPrefix.
     * Controllers still typically apply middleware, but this prevents accidental gaps.
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
     * Validate request using FormRequest or rules
     * Override in subclass to use custom FormRequest
     */
    protected function validateRequest(Request $request): array
    {
        // Check if definition has a specific request class
        $definition = $this->scaffold();
        $requestClass = $definition->getRequestClass();

        if ($requestClass && class_exists($requestClass)) {
            // Create FormRequest instance with current request data
            /** @var FormRequest $formRequest */
            $formRequest = $requestClass::createFrom($request);
            $formRequest->setContainer(app());
            $formRequest->setRedirector(resolve(Redirector::class));

            // Trigger validation (throws ValidationException on failure)
            $formRequest->validateResolved();

            return $formRequest->validated();
        }

        throw new LogicException(
            sprintf("Scaffold validation is required. Definition for '%s' must return a valid FormRequest class from getRequestClass().", $this->getEntityName())
        );
    }

    /**
     * Map bulk action to activity action type
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
