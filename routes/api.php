<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

// Public routes — anyone can call these, no login required
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password',  [AuthController::class, 'resetPassword']);

// Protected routes — must send a valid Sanctum token, otherwise Laravel
// returns 401 automatically before even reaching the controller method
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::match(['put', 'post'], '/auth/profile', [AuthController::class, 'updateProfile']);

    Route::get('/stores', [StoreController::class, 'browse']);
    Route::post('/stores', [StoreController::class, 'store']);
    Route::get('/stores/me', [StoreController::class, 'myStore']);
    Route::patch('/stores/me', [StoreController::class, 'update']);
    Route::get('/stores/{store}', [StoreController::class, 'show']);

    Route::get('/services',  [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::patch('/services/{service}', [ServiceController::class, 'update']);
    Route::patch('/services/{service}/toggle', [ServiceController::class, 'toggle']);
    Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

    Route::get('/orders',                [OrderController::class, 'index']);
    Route::post('/orders',               [OrderController::class, 'store']);
    Route::get('/orders/stats',          [OrderController::class, 'stats']);
    Route::get('/my-orders',             [OrderController::class, 'myOrders']);
    Route::get('/orders/{order}',        [OrderController::class, 'show']);
    Route::patch('/orders/{order}/accept', [OrderController::class, 'accept']);
    Route::patch('/orders/{order}/reject', [OrderController::class, 'reject']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);
});
