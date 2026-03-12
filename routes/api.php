<?php

use App\Http\Controllers\Api\Geo\CityController;
use App\Http\Controllers\Api\Geo\CountryController;
use App\Http\Controllers\Api\Geo\GeoIpController;
use App\Http\Controllers\Api\Geo\StateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

/*
|--------------------------------------------------------------------------
| Geo Data API Routes
|--------------------------------------------------------------------------
|
| Routes for accessing geographic data (countries, states, cities)
| These routes provide a consistent API interface for frontend applications
|
*/

Route::prefix('geo')->name('geo.')->group(function (): void {
    // Country routes
    Route::prefix('countries')->name('countries.')->group(function (): void {
        Route::get('/', [CountryController::class, 'index'])->name('index');
        Route::get('/search', [CountryController::class, 'search'])->name('search');
        Route::get('/{code}', [CountryController::class, 'show'])->name('show');
        Route::get('/{code}/states', [CountryController::class, 'states'])->name('states');
        Route::get('/{code}/cities', [CountryController::class, 'cities'])->name('cities');
    });

    // State routes
    Route::prefix('states')->name('states.')->group(function (): void {
        Route::get('/', [StateController::class, 'index'])->name('index');
        Route::get('/search', [StateController::class, 'search'])->name('search');
        Route::get('/{code}', [StateController::class, 'show'])->name('show');
        Route::get('/{code}/cities', [StateController::class, 'cities'])->name('cities');
    });

    // City routes
    Route::prefix('cities')->name('cities.')->group(function (): void {
        Route::get('/', [CityController::class, 'index'])->name('index');
        Route::get('/search', [CityController::class, 'search'])->name('search');
        Route::get('/batches', [CityController::class, 'batches'])->name('batches');
        Route::post('/batch', [CityController::class, 'batch'])->name('batch');
        Route::get('/{id}', [CityController::class, 'show'])->name('show');
    });

    // GeoIP routes (IP to location lookup - public, no auth)
    Route::prefix('geoip')->name('geoip.')->middleware('throttle:geoip')->group(function (): void {
        Route::get('/lookup', [GeoIpController::class, 'lookup'])->name('lookup');
        Route::get('/lookup/{ip}', [GeoIpController::class, 'lookupIp'])->name('lookup.ip');
        Route::get('/status', [GeoIpController::class, 'status'])->name('status');
    });
});
