<?php

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\PromptTemplateController;

Route::middleware(['auth', 'verified'])
    ->prefix('chatbot')
    ->name('chatbot.')
    ->group(function (): void {
        Route::get('/', [PromptTemplateController::class, 'index'])->name('index');
        Route::get('/create', [PromptTemplateController::class, 'create'])->name('create');
        Route::post('/', [PromptTemplateController::class, 'store'])->name('store');
        Route::get('/{promptTemplate}/edit', [PromptTemplateController::class, 'edit'])->name('edit');
        Route::patch('/{promptTemplate}', [PromptTemplateController::class, 'update'])->name('update');
        Route::delete('/{promptTemplate}', [PromptTemplateController::class, 'destroy'])->name('destroy');
    });
