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
use App\Http\Controllers\API\SpotModeController;
use App\Http\Controllers\AuthController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->delete('delete-account', [AuthController::class, 'deleteAccount']);
Route::middleware('auth:sanctum')->post('change-password', [AuthController::class, 'changePassword']);

Route::post('forgot-password/send-otp', [AuthController::class, 'sendOtpForPasswordReset']);
Route::post('forgot-password/verify-otp', [AuthController::class, 'verifyResetOtp']);
Route::post('forgot-password/reset', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('edit-profile', [AuthController::class, 'editProfile']);

      // For Customers
    Route::middleware(['auth:sanctum', CustomerMiddleware::class])->group(function () {
        Route::get('customer/get-profile', [AuthController::class, 'show']);
    });

    // For Drivers
    Route::middleware(['auth:sanctum', DriverMiddleware::class])->group(function () {
        Route::get('driver/get-profile', [AuthController::class, 'show']);
    });

    // For Admins
    Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('admin/get-profile', [AuthController::class, 'show']);
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
            Route::post('add-customer', [AdminDashbordController::class, 'addcustomer']);
            Route::get('stats', [AdminDashbordController::class, 'getStats']);
            Route::delete('destroyuser/{id}', [AdminDashbordController::class, 'destroyuser']);


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

Route::apiResource('products', ProductController::class)->only([ 'index','show']);
Route::middleware(['auth:sanctum', CustomerMiddleware::class])->group(function () {
    Route::get('/products/details/{id}', [ProductController::class, 'productDetails']);
    Route::get('/products/byCategory/{categoryId}', [ProductController::class, 'getProductsByCategory']);
});


    Route::middleware(['auth:sanctum',  CustomerMiddleware::class])->group(function () {
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'createOrder']);
        Route::post('/confirm-cart', [OrderController::class, 'confirmawaitCart']);
        Route::put('/orders/{orderId}', [OrderController::class, 'updateOrder']);
        Route::post('/orders/{orderId}/confirm', [OrderController::class, 'confirmOrder']);
        Route::delete('/orders/{orderId}', [OrderController::class, 'cancelOrder']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });

  Route::middleware(['auth:sanctum',  CustomerMiddleware::class])->group(function () {
   
      
      Route::post('/cart', [CartController::class, 'store']);
      Route::get('/cart', [CartController::class, 'index']);
      Route::post('/cart/cancel', [CartController::class, 'cancelCart']);
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

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('user-addresses', UserAddressesController::class);
});

Route::middleware(['auth:sanctum',  AdminMiddleware::class])->group(function () {
    Route::post('spot-mode/activate', [SpotModeController::class, 'activate']);
    Route::post('spot-mode/deactivate', [SpotModeController::class, 'deactivate']);
});
Route::get('spot-mode/status', [SpotModeController::class, 'getStatus']);









