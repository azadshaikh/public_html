<?php

use Illuminate\Support\Facades\Route;
use Modules\Todos\Http\Controllers\TodoController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function (): void {
    Route::apiResource('todos', TodoController::class)->names('todos');
});
