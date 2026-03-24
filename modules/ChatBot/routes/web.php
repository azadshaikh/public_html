<?php

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\ChatController;
use Modules\ChatBot\Http\Controllers\SettingsController;

/*
|--------------------------------------------------------------------------
| ChatBot Module Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth', 'module_access:chatbot'])
    ->prefix(config('app.admin_slug').'/chatbot')
    ->as('app.chatbot.')
    ->group(function (): void {
        // Settings (before parameterized routes)
        Route::group(['prefix' => 'settings', 'as' => 'settings.'], function (): void {
            Route::get('/', [SettingsController::class, 'settings'])->name('index');
            Route::post('/general', [SettingsController::class, 'updateGeneral'])->name('update-general');
            Route::post('/provider', [SettingsController::class, 'updateProvider'])->name('update-provider');
            Route::post('/tools', [SettingsController::class, 'updateTools'])->name('update-tools');
        });

        // Chat stream endpoint (POST to support CSRF)
        Route::post('/stream', [ChatController::class, 'stream'])->name('stream');
        Route::post('/conversations/{conversationId}/permissions/{permissionId}/approve', [ChatController::class, 'approveToolPermission'])
            ->name('permissions.approve')
            ->where('conversationId', '[0-9a-f\-]{36}')
            ->where('permissionId', '[0-9a-f\-]{36}');
        Route::post('/conversations/{conversationId}/permissions/{permissionId}/deny', [ChatController::class, 'denyToolPermission'])
            ->name('permissions.deny')
            ->where('conversationId', '[0-9a-f\-]{36}')
            ->where('permissionId', '[0-9a-f\-]{36}');
        Route::post('/conversations/{conversationId}/messages/{messageId}/stop', [ChatController::class, 'stopAssistantMessage'])
            ->name('messages.stop')
            ->where('conversationId', '[0-9a-f\-]{36}')
            ->where('messageId', '[0-9a-f\-]{36}');

        // Chat CRUD — all conversation URLs grouped under /conversations/
        Route::get('/', [ChatController::class, 'index'])->name('index');
        Route::get('/conversations/new', [ChatController::class, 'newChat'])->name('new');
        Route::get('/conversations/{conversationId}', [ChatController::class, 'show'])
            ->name('show')
            ->where('conversationId', '[0-9a-f\-]{36}');
        Route::delete('/conversations/{conversationId}', [ChatController::class, 'destroy'])
            ->name('destroy')
            ->where('conversationId', '[0-9a-f\-]{36}');

    });
