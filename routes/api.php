<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\SaleHistoryController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me'])->name('user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/branches', [CatalogController::class, 'branches'])->name('branches.index');
    Route::get('/categories', [CatalogController::class, 'categories'])->name('categories.index');
    Route::get('/products', [CatalogController::class, 'products'])->name('products.index');
    Route::post('/sales', [SalesController::class, 'store'])->name('sales.store');
    Route::get('/sales', [SaleHistoryController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleHistoryController::class, 'show'])->name('sales.show');
    Route::patch('/sales/{sale}/cancel', [SaleHistoryController::class, 'cancel'])->name('sales.cancel');
    Route::get('/reports/dashboard/export', [ReportController::class, 'export'])->name('reports.dashboard.export');
    Route::get('/reports/dashboard', [ReportController::class, 'dashboard'])->name('reports.dashboard');
});
