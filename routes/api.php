<?php

use App\Http\Controllers\BenchmarkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('benchmark')->group(function () {
    Route::get('orders', [BenchmarkController::class, 'orders']);
    Route::get('orders/{order}', [BenchmarkController::class, 'orderShow']);
    Route::get('orders-enterprise', [BenchmarkController::class, 'enterpriseOrder']);

    Route::get('products', [BenchmarkController::class, 'products']);
    Route::get('products/by-category/{category}', [BenchmarkController::class, 'productsByCategory']);
    Route::get('products/{product}/reviews', [BenchmarkController::class, 'productReviews']);
    Route::get('products/null-category', [BenchmarkController::class, 'productsWithoutCategory']);
    
    Route::get('categories/{category}/products', [BenchmarkController::class, 'categoryProducts']);
    Route::get('users/{user}/orders', [BenchmarkController::class, 'userOrders']);
    Route::get('reviews/recent', [BenchmarkController::class, 'recentReviews']);
});

