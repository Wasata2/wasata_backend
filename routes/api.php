<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\StockItemController;
use App\Http\Controllers\Api\NotificationController;
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
    Route::put('/stores/me/delivery-zones', [StoreController::class, 'updateDeliveryZones']);
    Route::get('/stores/{store}', [StoreController::class, 'show']);
    Route::get('/stores/{store}/reviews', [ReviewController::class, 'forStore']);

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
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/review', [ReviewController::class, 'storeForOrder']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store']);

    Route::get('/notifications',               [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',  [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::patch('/notifications/read-all',    [NotificationController::class, 'markAllRead']);

    // Broker's own "القطع الراكدة" inventory
    Route::get('/stock-items',    [StockItemController::class, 'index']);
    Route::post('/stock-items',   [StockItemController::class, 'store']);
    Route::patch('/stock-items/{item}', [StockItemController::class, 'update']);
    Route::patch('/stock-items/{item}/list', [StockItemController::class, 'list']);
    Route::patch('/stock-items/{item}/unlist', [StockItemController::class, 'unlist']);
    Route::patch('/stock-items/{item}/cancel-reservation', [StockItemController::class, 'cancelReservation']);
    Route::patch('/stock-items/{item}/confirm-sale', [StockItemController::class, 'confirmSale']);
    Route::delete('/stock-items/{item}', [StockItemController::class, 'destroy']);

    // Customer-facing browsing and reservation
    Route::get('/stores/{store}/stock-items', [StockItemController::class, 'forStore']);
    Route::patch('/stock-items/{item}/reserve', [StockItemController::class, 'reserve']);
});
