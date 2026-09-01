<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

// Public routes — anyone can call these, no login required
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

// Protected routes — must send a valid Sanctum token, otherwise Laravel
// returns 401 automatically before even reaching the controller method
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);

    Route::post('/stores', [StoreController::class, 'store']);
    Route::get('/stores/me', [StoreController::class, 'myStore']);
    Route::patch('/stores/me', [StoreController::class, 'update']);

    Route::get('/services',  [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);

    Route::get('/orders',                [OrderController::class, 'index']);
    Route::patch('/orders/{order}/accept', [OrderController::class, 'accept']);
    Route::patch('/orders/{order}/reject', [OrderController::class, 'reject']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
});
