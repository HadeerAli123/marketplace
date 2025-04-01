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
    
    Route::middleware(['auth:sanctum',  DriverMiddleware::class])->group(function () {
        Route::get('/driver/orders', [OrderController::class, 'getDriverOrders']);
        Route::post('/orders/{orderId}/accept', [OrderController::class, 'acceptOrder']);
        Route::post('/orders/{orderId}/deliver', [OrderController::class, 'deliverOrder']);
    });
    
    Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/admin/orders', [OrderController::class, 'adminOrderDetails']);
        Route::post('/orders/assign', [OrderController::class, 'assignOrdersToDriver']);
    });
  

    Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::apiResource('categories', CategoryController::class)
            ->only(['store', 'update', 'destroy']);
    });


















    /* GET|HEAD        / ............................................................................................................ 
  GET|HEAD        api/admin/dashboard .......................................................................................... 
  GET|HEAD        api/admin/orders ....................................................... Api\OrderController@adminOrderDetails 
  GET|HEAD        api/cart ............................................................................ Api\CartController@index 
  POST            api/cart ............................................................................ Api\CartController@store 
  POST            api/cart-items ................................................................. Api\CartItemsController@store 
  PUT             api/cart-items/{id} ........................................................... Api\CartItemsController@update 
  DELETE          api/cart-items/{id} .......................................................... Api\CartItemsController@destroy 
  PUT             api/cart/{id} ...................................................................... Api\CartController@update 
  DELETE          api/cart/{id} ..................................................................... Api\CartController@destroy 
  GET|HEAD        api/customer/dashboard ....................................................................................... 
  GET|HEAD        api/driver/dashboard .........................................................................................  
  GET|HEAD        api/driver/orders ........................................................ Api\OrderController@getDriverOrders
  POST            api/login ............................................................................... AuthController@login  
  POST            api/logout ............................................................................. AuthController@logout  
  GET|HEAD        api/order-items ........................................... order-items.index › Api\OrderItemsController@index
  POST            api/order-items ........................................... order-items.store › Api\OrderItemsController@store  
  GET|HEAD        api/order-items/{order_item} ................................ order-items.show › Api\OrderItemsController@show  
  PUT|PATCH       api/order-items/{order_item} ............................ order-items.update › Api\OrderItemsController@update  
  DELETE          api/order-items/{order_item} .......................... order-items.destroy › Api\OrderItemsController@destroy
  GET|HEAD        api/orders ......................................................................... Api\OrderController@index  
  POST            api/orders ................................................................... Api\OrderController@createOrder  
  POST            api/orders/assign ................................................... Api\OrderController@assignOrdersToDriver  
  GET|HEAD        api/orders/{id} ..................................................................... Api\OrderController@show
  PUT             api/orders/{orderId} ......................................................... Api\OrderController@updateOrder
  DELETE          api/orders/{orderId} ......................................................... Api\OrderController@cancelOrder
  POST            api/orders/{orderId}/accept .................................................. Api\OrderController@acceptOrder
  POST            api/orders/{orderId}/confirm ................................................ Api\OrderController@confirmOrder  
  POST            api/orders/{orderId}/deliver ................................................ Api\OrderController@deliverOrder
  GET|HEAD        api/orders/{order} .................................................... orders.show › Api\OrderController@show  
  PUT|PATCH       api/orders/{order} ................................................ orders.update › Api\OrderController@update  
  DELETE          api/orders/{order} .............................................. orders.destroy › Api\OrderController@destroy
  POST            api/products .................................................... products.store › Api\ProductController@store  
  GET|HEAD        api/products .................................................... products.index › Api\ProductController@index
  GET|HEAD        api/products/byCategory/{category} ............................... Api\ProductController@getProductsByCategory  
  GET|HEAD        api/products/deleted ..................................................... Api\ProductController@getAlldeleted  
  GET|HEAD        api/products/restore/{product_id} .............................................. Api\ProductController@restore
  DELETE          api/products/{id}/force-delete ............................................ Api\ProductController@forceDestroy  
  PUT|PATCH       api/products/{product} ........................................ products.update › Api\ProductController@update  
  DELETE          api/products/{product} ...................................... products.destroy › Api\ProductController@destroy  
  GET|HEAD        api/products/{product} ............................................ products.show › Api\ProductController@show  
  PUT             api/profile ....................................................................... AuthController@editProfile  
  POST            api/register ......................................................................... AuthController@register  
  POST            api/run-migrations ......................................................... MigrationController@runMigrations  
  GET|HEAD        api/user .....................................................................................................  
  GET|HEAD        sanctum/csrf-cookie ........................ sanctum.csrf-cookie › Laravel\Sanctum › CsrfCookieController@show  
  GET|HEAD        storage/{path} ................................................................................. storage.local  
  GET|HEAD        up ...........................................................................................................  

                */
