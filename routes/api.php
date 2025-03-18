<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemsController;
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
