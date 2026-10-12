<?php

use App\Http\Controllers\MobileAccountController;
use App\Http\Controllers\MobileAuthenticationController;
use App\Http\Controllers\MobileDeliveryChatController;
use App\Http\Controllers\MobileOrderController;
use App\Http\Controllers\MobileRiderAccountController;
use App\Http\Controllers\MobileRiderAuthenticationController;
use App\Http\Controllers\MobileRiderDashboardController;
use App\Http\Controllers\MobileRiderSupportController;
use App\Http\Controllers\MobileShoppingController;
use App\Http\Controllers\MobileStorefrontController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile storefront API
|--------------------------------------------------------------------------
|
| These endpoints query the same models and database as the browser
| storefront and share its cart, checkout and address services. Buyer
| endpoints authenticate with Sanctum bearer tokens issued at sign-in.
| Philippine location lookups reuse the public /api/locations routes.
|
*/
Route::prefix('mobile')->middleware('throttle:60,1')->group(function () {
    Route::get('/home', [MobileStorefrontController::class, 'home']);
    Route::get('/products', [MobileStorefrontController::class, 'products']);
    Route::get('/products/{product:slug}', [MobileStorefrontController::class, 'show']);

    // Pre-upload check of rider sign-up fields; shares the 60/min group limit.
    Route::post('/rider/register/check', [MobileRiderAuthenticationController::class, 'checkRegistration']);

    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/login', [MobileAuthenticationController::class, 'login']);
        Route::post('/rider/login', [MobileRiderAuthenticationController::class, 'login']);
        Route::post('/rider/register', [MobileRiderAuthenticationController::class, 'register']);
        Route::post('/register', [MobileAuthenticationController::class, 'register']);
        Route::post('/verify-email', [MobileAuthenticationController::class, 'verifyEmail']);
        Route::post('/resend-verification', [MobileAuthenticationController::class, 'resendVerification']);
        Route::post('/forgot-password', [MobileAuthenticationController::class, 'forgotPassword']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [MobileAuthenticationController::class, 'me']);
        Route::post('/logout', [MobileAuthenticationController::class, 'logout']);

        Route::middleware('mobile.rider')->prefix('rider')->group(function () {
            Route::get('/dashboard', [MobileRiderDashboardController::class, 'dashboard']);
            Route::post('/deliveries/{shipment}/claim', [MobileRiderDashboardController::class, 'claim']);
            Route::patch('/deliveries/{shipment}', [MobileRiderDashboardController::class, 'update']);
            Route::get('/deliveries/{shipment}/chat', [MobileDeliveryChatController::class, 'riderShow']);
            Route::post('/deliveries/{shipment}/chat', [MobileDeliveryChatController::class, 'riderSend'])->middleware('throttle:20,1');
            Route::get('/history', [MobileRiderDashboardController::class, 'history']);
            Route::get('/earnings', [MobileRiderDashboardController::class, 'earnings']);

            Route::patch('/account/phone', [MobileRiderAccountController::class, 'updatePhone']);
            Route::patch('/account/gender', [MobileRiderAccountController::class, 'updateGender']);
            Route::put('/account/address', [MobileRiderAccountController::class, 'updateAddress']);
            Route::put('/account/password', [MobileRiderAccountController::class, 'updatePassword']);

            Route::get('/support', [MobileRiderSupportController::class, 'show']);
            Route::get('/support/unread', [MobileRiderSupportController::class, 'unread']);
            Route::post('/support/messages', [MobileRiderSupportController::class, 'send'])->middleware('throttle:20,1');
        });

        Route::middleware('mobile.buyer')->group(function () {
            Route::get('/wishlist', [MobileShoppingController::class, 'wishlist']);
            Route::post('/wishlist/{product:slug}', [MobileShoppingController::class, 'toggleWishlist']);

            Route::get('/cart', [MobileShoppingController::class, 'cart']);
            Route::post('/cart/remove', [MobileShoppingController::class, 'removeCartItems']);
            Route::post('/cart/{product:slug}', [MobileShoppingController::class, 'addToCart']);
            Route::patch('/cart/items/{cartItem}', [MobileShoppingController::class, 'updateCartItem']);
            Route::delete('/cart/items/{cartItem}', [MobileShoppingController::class, 'removeCartItem']);

            Route::post('/checkout/quote', [MobileShoppingController::class, 'quote']);
            Route::post('/checkout', [MobileShoppingController::class, 'placeOrder'])->middleware('throttle:10,1');

            Route::get('/orders', [MobileOrderController::class, 'index']);
            Route::get('/orders/{order}', [MobileOrderController::class, 'show']);
            Route::post('/orders/{order}/cancel', [MobileOrderController::class, 'cancel']);
            Route::get('/orders/{order}/chat', [MobileDeliveryChatController::class, 'buyerShow']);
            Route::post('/orders/{order}/chat', [MobileDeliveryChatController::class, 'buyerSend'])->middleware('throttle:20,1');

            Route::patch('/account/profile', [MobileAccountController::class, 'updateProfile']);
            Route::put('/account/password', [MobileAccountController::class, 'updatePassword']);

            Route::get('/addresses', [MobileAccountController::class, 'addresses']);
            Route::post('/addresses', [MobileAccountController::class, 'storeAddress']);
            Route::put('/addresses/{address}', [MobileAccountController::class, 'updateAddress']);
            Route::patch('/addresses/{address}/default', [MobileAccountController::class, 'makeDefaultAddress']);
            Route::delete('/addresses/{address}', [MobileAccountController::class, 'destroyAddress']);

            Route::get('/notifications', [MobileAccountController::class, 'notifications']);
            Route::post('/notifications/read', [MobileAccountController::class, 'markNotificationsRead']);
        });
    });
});
