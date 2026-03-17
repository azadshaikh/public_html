<?php

declare(strict_types=1);

namespace App\Scaffold;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * ScaffoldRequest - Convention-based form request for Scaffold system
 *
 * Provides validation with automatic unique/exists rules handling
 * and DataGrid compatible JSON error responses.
 *
 * @example
 * class AddressRequest extends ScaffoldRequest
 * {
 *     protected function definition(): ScaffoldDefinition
 *     {
 *         return new AddressDefinition();
 *     }
 *
 *     public function rules(): array
 *     {
 *         return [
 *             'name' => ['required', 'string', 'max:255', $this->uniqueRule('name')],
 *             'city' => ['required', 'string', 'max:100'],
 *             'status' => ['required', $this->enumRule(AddressStatus::class)],
 *         ];
 *     }
 * }
 */
abstract class ScaffoldRequest extends FormRequest
{
    /**
     * Cached scaffold definition
     */
    protected ?ScaffoldDefinition $definitionCache = null;

    /**
     * Cached model table name
     */
    protected ?string $tableCache = null;

    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the scaffold definition
     */
    abstract protected function definition(): ScaffoldDefinition;

    /**
     * Get cached scaffold definition
     */
    protected function scaffold(): ScaffoldDefinition
    {
        return $this->definitionCache ??= $this->definition();
    }

    /**
     * Get model class from scaffold definition
     */
    protected function getModelClass(): string
    {
        return $this->scaffold()->getModelClass();
    }

    /**
     * Get model table name (cached to prevent multiple instantiations)
     */
    protected function getModelTable(): string
    {
        if ($this->tableCache === null) {
            $modelClass = $this->getModelClass();
            $this->tableCache = (new $modelClass)->getTable();
        }

        return $this->tableCache;
    }

    /**
     * Get route parameter for model binding (e.g., 'address' from route)
     * Returns the ID (int/string) or null - handles both ID and model binding
     */
    protected function getRouteParameter(): int|string|null
    {
        // First try 'id' parameter (standard for Scaffold routes)
        $param = $this->route('id');

        // Fallback to entity name in camel case for backwards compatibility
        if ($param === null) {
            $modelKey = str($this->scaffold()->getEntityName())->camel()->toString();
            $param = $this->route($modelKey);
        }

        // Handle route model binding (Laravel may inject Model object)
        if ($param instanceof Model) {
            return $param->getKey();
        }

        return $param;
    }

    /**
     * Get the model instance for update operations
     */
    protected function getModel(): ?object
    {
        $id = $this->getRouteParameter();

        if (! $id) {
            return null;
        }

        $modelClass = $this->getModelClass();

        return $modelClass::find($id);
    }

    /**
     * Check if this is an update operation
     */
    protected function isUpdate(): bool
    {
        return $this->getRouteParameter() !== null;
    }

    /**
     * Check if this is a create operation
     */
    protected function isCreate(): bool
    {
        return ! $this->isUpdate();
    }

    // =========================================================================
    // VALIDATION RULE HELPERS
    // =========================================================================

    /**
     * Generate unique rule that ignores current model on update
     *
     * @param  string  $column  Column name to check uniqueness
     * @param  string|null  $table  Table name (defaults to model's table)
     */
    protected function uniqueRule(string $column, ?string $table = null): Unique
    {
        // Use cached table name to avoid redundant model instantiation
        $table ??= $this->getModelTable();

        $rule = Rule::unique($table, $column);

        // Ignore current model on update
        if ($this->isUpdate()) {
            $rule->ignore($this->getRouteParameter());
        }

        return $rule;
    }

    /**
     * Generate exists rule for foreign key validation
     *
     * @param  string  $table  Table name
     * @param  string  $column  Column name (defaults to 'id')
     */
    protected function existsRule(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column);
    }

    /**
     * Generate enum validation rule
     *
     * @param  string  $enumClass  Enum class name
     */
    protected function enumRule(string $enumClass): Enum
    {
        return Rule::enum($enumClass);
    }

    /**
     * Rule that is required only on create
     */
    protected function requiredOnCreate(): string
    {
        return $this->isCreate() ? 'required' : 'nullable';
    }


    /**
     * Prepare boolean field
     */
    protected function prepareBooleanField(string $field): void
    {
        if ($this->has($field)) {
            $this->merge([$field => $this->boolean($field)]);
        }
    }

    /**
     * Trim whitespace from string field
     */
    protected function trimField(string $field, bool $lowercase = false): void
    {
        if ($this->has($field)) {
            $value = trim($this->{$field} ?? '');
            if ($lowercase) {
                $value = strtolower($value);
            }

            $this->merge([$field => $value]);
        }
    }

    // =========================================================================
    // ERROR HANDLING (DataGrid JSON format)
    // =========================================================================

    /**
     * Handle a failed validation attempt - returns JSON for AJAX requests
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(
                response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }
}
