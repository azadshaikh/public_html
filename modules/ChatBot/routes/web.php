<?php

use Illuminate\Support\Facades\Route;
use Modules\ChatBot\Http\Controllers\ChatBotDashboardController;

Route::middleware(['auth', 'verified'])
    ->prefix('chatbot')
    ->name('chatbot.')
    ->group(function (): void {
        Route::get('/', ChatBotDashboardController::class)->name('index');
    });
