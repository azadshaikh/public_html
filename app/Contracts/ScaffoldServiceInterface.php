<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Scaffold\ScaffoldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Contract for Scaffold service classes.
 *
 * All services used by ScaffoldController must implement this interface
 * to ensure type safety and consistent API across the application.
 */
interface ScaffoldServiceInterface
{
    /**
     * Get the cached scaffold definition.
     */
    public function scaffold(): ScaffoldDefinition;

    /**
     * Get the scaffold definition instance.
     */
    public function getScaffoldDefinition(): ScaffoldDefinition;

    /**
     * Get the model class name.
     */
    public function getModelClass(): string;

    /**
     * Get the entity name (singular).
     */
    public function getEntityName(): string;

    /**
     * Get the entity name (plural).
     */
    public function getEntityPlural(): string;

    /**
     * Get paginated/filtered data for DataGrid.
     *
     * @return array{items: array, pagination: array, columns: array, filters: array, actions: array, empty_state_config: array, statistics?: array}
     */
    public function getData(Request $request): array;

    /**
     * Create a new model instance.
     */
    public function create(array $data): Model;

    /**
     * Update an existing model instance.
     */
    public function update(Model $model, array $data): Model;

    /**
     * Delete (soft delete) a model instance.
     */
    public function delete(Model $model): void;

    /**
     * Restore a soft-deleted model instance.
     */
    public function restore(int|string $id): Model;

    /**
     * Permanently delete a trashed model instance.
     * Accepts a Model directly to avoid redundant lookups.
     */
    public function forceDelete(int|string|Model $modelOrId): void;

    /**
     * Handle a bulk action request.
     *
     * @return array{success: bool, message: string, affected?: int}
     */
    public function handleBulkAction(Request $request): array;

    /**
     * Find a model by ID (or route key), optionally including trashed.
     */
    public function findModel(int|string $id, bool $withTrashed = false): Model;
}
