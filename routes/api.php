<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuyerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return response()->json([
        'user' => $request->user(),
    ]);
});

Route::middleware('auth:sanctum')->get('/buyer/dashboard', [BuyerController::class, 'dashboard']);

Route::middleware('auth:sanctum')->get('/buyer/products', [BuyerController::class, 'products']);

Route::middleware('auth:sanctum')->get('/buyer/products/{id}', [BuyerController::class, 'product']);

Route::middleware('auth:sanctum')->get('/buyer/shops', [BuyerController::class, 'shops']);