<?php

use App\Http\Controllers\API\AdminDashbordController;
use App\Http\Controllers\MigrationController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CustomerMiddleware;
use App\Http\Middleware\DriverMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemsController;

use App\Http\Controllers\AuthController;

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::put('/profile', [AuthController::class, 'editProfile']);

        Route::middleware(['auth:sanctum',  CustomerMiddleware::class])->group(function () {
            Route::get('customer/dashboard', function () {
                return response()->json(['message' => 'Welcome customer']);
            });
        });

        Route::middleware(['auth:sanctum',  DriverMiddleware::class])->group(function () {
            Route::get('/driver/dashboard', fn() => response()->json(['message' => 'Welcome Driver']));
        });
        Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
            Route::get('/admin/dashboard', function () {
                return response()->json(['message' => 'Welcome Admin']);
            });

            Route::get('/drivers', [AdminDashbordController::class, 'getDrivers']);

        });

    });

    Route::post('run-migrations', [MigrationController::class, 'runMigrations']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/products/deleted',
    [ProductController::class, 'getAlldeleted'])
    ->middleware('auth:sanctum');
Route::get('/products/restore/{product_id}',
    [ProductController::class, 'restore'])
    ->middleware('auth:sanctum');
Route::apiResource('orders', OrderController::class)->middleware('auth:sanctum');
Route::apiResource('order-items', OrderItemsController::class);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('products', ProductController::class)->only([ 'store','update', 'destroy']);
    });

    Route::apiResource('products', ProductController::class)->only(['index', 'show']);
    Route::get('products/byCategory/{category}', [ProductController::class, 'getProductsByCategory']);
