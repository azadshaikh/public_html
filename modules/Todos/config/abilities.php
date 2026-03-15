<?php

use App\Helpers\AbilityAggregator;

/**
 * Abilities shared with the Inertia frontend.
 *
 * Each key is a camelCase ability name exposed in `auth.abilities`.
 * Values are permission strings resolved via `$user->can()`.
 *
 * @see AbilityAggregator
 */

return [
    'viewTodos' => 'view_todos',
    'addTodos' => 'add_todos',
    'editTodos' => 'edit_todos',
    'deleteTodos' => 'delete_todos',
    'restoreTodos' => 'restore_todos',
];
