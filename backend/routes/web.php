<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// First-party cookie API retains the web session and CSRF middleware.
Route::prefix('api/v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('stores', [StoreController::class, 'index']);
        Route::get('stores/{store}', [StoreController::class, 'show']);
        Route::get('users', [UserController::class, 'index']);
    });
});
