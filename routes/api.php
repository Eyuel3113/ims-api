<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\AnalyticsController;


Route::prefix('v1')->group(function () {

    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Protected routes — authenticated
    Route::middleware('auth:sanctum')->group(function () {

      Route::prefix('auth')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/change-email', [AuthController::class, 'changeEmail']);
     
      });


      Route::prefix('categories')->group(function () {

        Route::get('/active', [CategoryController::class, 'activeCategories']);
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{id}', [CategoryController::class, 'show']);
        Route::patch('/{id}', [CategoryController::class, 'update']);
        Route::patch('/{id}/status', [CategoryController::class, 'toggleStatus']);
        Route::delete('/{id}', [CategoryController::class, 'destroy']);

        // Route::patch('/jobs/{id}/status', [JobController::class, 'toggleStatus']);

      });
    // Suppliers
    Route::apiResource('suppliers', SupplierController::class);

    // Warehouses
    Route::apiResource('warehouses', WarehouseController::class);

    // Products
    Route::apiResource('products', ProductController::class);

    // Purchases
    Route::apiResource('purchases', PurchaseController::class)->only(['index', 'store', 'show']);
    Route::get('/purchases/{id}/invoice', [PurchaseController::class, 'invoice']);

    // Sales
    Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);
    Route::get('/sales/{id}/invoice', [SaleController::class, 'invoice']);

    // Stock History
    Route::get('/stock-movements', [StockMovementController::class, 'index']);

    // Analytics Dashboard
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/stock-by-warehouse', [AnalyticsController::class, 'stockByWarehouse']);
        Route::get('/monthly-trend', [AnalyticsController::class, 'monthlyTrend']);
    });
});

});