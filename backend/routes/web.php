<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InventoryReadController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockDocumentController;
use App\Http\Controllers\StockTransferController;
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
        Route::post('stores', [StoreController::class, 'store']);
        Route::patch('stores/{store}', [StoreController::class, 'update']);
        Route::apiResource('categories', CategoryController::class)->except('destroy');
        Route::apiResource('products', ProductController::class)->except('destroy');
        Route::get('inventory/balances', [InventoryReadController::class, 'balances']);
        Route::get('inventory/ledger', [InventoryReadController::class, 'ledger']);
        Route::get('dashboard/summary', [ReportController::class, 'summary']);
        Route::get('reports/stock-summary', [ReportController::class, 'stock']);
        Route::get('reports/low-stock', [ReportController::class, 'stock'])->name('reports.low-stock');
        Route::get('reports/movements', [ReportController::class, 'movements']);
        Route::get('reports/movements/export', [ReportController::class, 'export'])->middleware('throttle:exports');
        Route::get('audit-logs', [ReportController::class, 'audit']);
        foreach (['receipts' => 'receipt', 'issues' => 'issue'] as $path => $kind) {
            Route::get('inventory/'.$path, [StockDocumentController::class, 'index'])->defaults('kind', $kind);
            Route::post('inventory/'.$path, [StockDocumentController::class, 'store'])->defaults('kind', $kind);
            Route::get('inventory/'.$path.'/{document}', [StockDocumentController::class, 'show'])->defaults('kind', $kind);
            Route::patch('inventory/'.$path.'/{document}', [StockDocumentController::class, 'update'])->defaults('kind', $kind);
            Route::post('inventory/'.$path.'/{document}/cancel', [StockDocumentController::class, 'cancel'])->defaults('kind', $kind);
            Route::post('inventory/'.$path.'/{document}/post', [StockDocumentController::class, 'post'])->defaults('kind', $kind)->middleware('throttle:posting');
        }
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::get('roles', [UserController::class, 'roles']);
        Route::get('transfers', [StockTransferController::class, 'index']);
        Route::post('transfers', [StockTransferController::class, 'store']);
        Route::get('transfers/{transfer}', [StockTransferController::class, 'show']);
        Route::patch('transfers/{transfer}', [StockTransferController::class, 'update']);
        Route::post('transfers/{transfer}/submit', [StockTransferController::class, 'submit']);
        Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'cancel']);
        Route::post('transfers/{transfer}/reject', [StockTransferController::class, 'reject']);
        Route::post('transfers/{transfer}/approve', [StockTransferController::class, 'approve'])->middleware('throttle:posting');
    });
});
