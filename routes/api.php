<?php

use App\Http\Controllers\API\AdminDashbordController;
use App\Http\Controllers\MigrationController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CustomerMiddleware;
use App\Http\Middleware\DriverMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CartItemsController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\OrderItemsController;
use App\Http\Controllers\API\ContactUsController;
use App\Http\Controllers\API\UserAddressesController;
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

            Route::get('drivers', [AdminDashbordController::class, 'getDrivers']);
            Route::get('dashbord-orders', [AdminDashbordController::class, 'getOrders']);
            Route::get('categories', [AdminDashbordController::class, 'getCategories']);
            Route::get('categories/{id}', [AdminDashbordController::class, 'getCategory']);
            Route::post('categories', [AdminDashbordController::class, 'createCategory']);
            Route::post('categories/{id}', [AdminDashbordController::class, 'updateCategory']);
            Route::delete('categories/{id}', [AdminDashbordController::class, 'deleteCategory']);
            Route::get('daily-summaries', [AdminDashbordController::class, 'getDailySummaries']);
            Route::get('daily-customer-summaries', [AdminDashbordController::class, 'getDailyCustomerSummaries']);
            Route::get('customers', [AdminDashbordController::class, 'getCustomers']);
            Route::post('add-driver', [AdminDashbordController::class, 'addDriver']);


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
Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
    Route::apiResource('products', ProductController::class)->only(['store', 'update', 'destroy']);
    Route::delete('products/{id}/force-delete', [ProductController::class, 'forceDestroy']);
});

Route::apiResource('products', ProductController::class)->only(['index', 'show']);
Route::get('products/byCategory/{category}', [ProductController::class, 'getProductsByCategory']);
Route::middleware(['auth:sanctum', CustomerMiddleware::class])->group(function () {
    Route::get('/products/details/{id}', [ProductController::class, 'productDetails']);
});

    Route::middleware(['auth:sanctum',  CustomerMiddleware::class])->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'createOrder']);
        Route::put('/orders/{orderId}', [OrderController::class, 'updateOrder']);
        Route::post('/orders/{orderId}/confirm', [OrderController::class, 'confirmOrder']);
        Route::delete('/orders/{orderId}', [OrderController::class, 'cancelOrder']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });

  Route::middleware(['auth:sanctum',  CustomerMiddleware::class])->group(function () {
   
      
      Route::post('/cart', [CartController::class, 'store']);
      Route::get('/cart', [CartController::class, 'index']);
        Route::put('/cart/{id}', [CartController::class, 'update']);
        Route::delete('/cart/{id}', [CartController::class, 'destroy']);
      Route::post('/cart-items', [CartItemsController::class, 'store']);
        Route::put('/cart-items/{id}', [CartItemsController::class, 'update']);
        Route::delete('/cart-items/{id}', [CartItemsController::class, 'destroy']);
        Route::get('/cart-items/my-items', [CartItemsController::class , 'getMyItems']);
      
    });

    Route::middleware(['auth:sanctum',  DriverMiddleware::class])->group(function () {
        Route::get('/driver/orders', [OrderController::class, 'getDriverOrders']);
        Route::post('/orders/{orderId}/accept', [OrderController::class, 'acceptOrder']);
        Route::post('/orders/{orderId}/deliver', [OrderController::class, 'deliverOrder']);
    });
    
    Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/admin/orders', [OrderController::class, 'adminOrderDetails']);
        Route::post('/orders/assign', [OrderController::class, 'assignOrdersToDriver']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('contact-messages', ContactUsController::class)->only(['index', 'destroy']);
    });
Route::post('contact-messages', [ContactUsController::class, 'store']);

Route::middleware(['auth:sanctum',  CustomerMiddleware::class])->group(function () {
    Route::apiResource('user-addresses', UserAddressesController::class);
});










