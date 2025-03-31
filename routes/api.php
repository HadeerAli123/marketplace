<?php

use App\Http\Controllers\API\AdminDashbordController;
use App\Http\Controllers\MigrationController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CustomerMiddleware;
use App\Http\Middleware\DriverMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CartItemsController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\OrderItemsController;
use App\Http\Controllers\AuthController;

// Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'editProfile']);
});

// Admin Routes
Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
    Route::get('/admin/dashboard', fn() => response()->json(['message' => 'Welcome Admin']));
    Route::get('/drivers', [AdminDashbordController::class, 'getDrivers']);
    Route::get('/admin/orders', [OrderController::class, 'adminOrderDetails']);
    Route::post('/orders/assign', [OrderController::class, 'assignOrdersToDriver']);
    Route::apiResource('categories', CategoryController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
    Route::delete('products/{id}/force-delete', [ProductController::class, 'forceDestroy']);
});

// Customer Routes
Route::middleware(['auth:sanctum', CustomerMiddleware::class])->group(function () {
    Route::get('customer/dashboard', fn() => response()->json(['message' => 'Welcome customer']));
    Route::get('/products/details/{id}', [ProductController::class, 'productDetails']);
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);
    Route::post('/cart-items', [CartItemsController::class, 'store']);
    Route::put('/cart-items/{id}', [CartItemsController::class, 'update']);
    Route::delete('/cart-items/{id}', [CartItemsController::class, 'destroy']);
    Route::get('/cart-items/my-items', [CartItemsController::class , 'getMyItems']);
    Route::apiResource('orders', OrderController::class)->except(['store']);
    Route::post('/orders', [OrderController::class, 'createOrder']);
    Route::post('/orders/{orderId}/confirm', [OrderController::class, 'confirmOrder']);
    Route::delete('/orders/{orderId}', [OrderController::class, 'cancelOrder']);
});

// Driver Routes
Route::middleware(['auth:sanctum', DriverMiddleware::class])->group(function () {
    Route::get('/driver/dashboard', fn() => response()->json(['message' => 'Welcome Driver']));
    Route::get('/driver/orders', [OrderController::class, 'getDriverOrders']);
    Route::post('/orders/{orderId}/accept', [OrderController::class, 'acceptOrder']);
    Route::post('/orders/{orderId}/deliver', [OrderController::class, 'deliverOrder']);
});

// Public Routes
Route::apiResource('products', ProductController::class)->only(['index', 'show']);
Route::get('products/byCategory/{category}', [ProductController::class, 'getProductsByCategory']);
Route::get('/products/deleted', [ProductController::class, 'getAlldeleted'])->middleware('auth:sanctum');
Route::get('/products/restore/{product_id}', [ProductController::class, 'restore'])->middleware('auth:sanctum');
Route::post('run-migrations', [MigrationController::class, 'runMigrations']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
