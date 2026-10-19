<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\MobileAccountController;
use App\Http\Controllers\MobileAuthenticationController;
use App\Http\Controllers\MobileChatController;
use App\Http\Controllers\MobileComplaintController;
use App\Http\Controllers\MobileDeliveryChatController;
use App\Http\Controllers\MobileOrderController;
use App\Http\Controllers\MobileRiderAccountController;
use App\Http\Controllers\MobileRiderAuthenticationController;
use App\Http\Controllers\MobileRiderDashboardController;
use App\Http\Controllers\MobileShoppingController;
use App\Http\Controllers\MobileStorefrontController;
use App\Http\Controllers\MobileSupportController;
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
    Route::get('/products/{product:slug}/reviews', [MobileStorefrontController::class, 'reviews']);
    Route::get('/policies', [MobileStorefrontController::class, 'policies']);
    Route::get('/policies/{slug}', [MobileStorefrontController::class, 'policy']);

    // Pre-upload check of rider sign-up fields; shares the 60/min group limit.
    Route::post('/rider/register/check', [MobileRiderAuthenticationController::class, 'checkRegistration']);
    Route::get('/rider/logistics-partners', [MobileRiderAuthenticationController::class, 'logisticsPartners']);

    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/login', [MobileAuthenticationController::class, 'login']);
        Route::post('/google', [MobileAuthenticationController::class, 'google']);
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
            Route::post('/deliveries/scan', [MobileRiderDashboardController::class, 'scan'])->middleware('throttle:30,1');
            Route::patch('/deliveries/{shipment}', [MobileRiderDashboardController::class, 'update']);
            Route::get('/deliveries/{shipment}/chat', [MobileDeliveryChatController::class, 'riderShow']);
            Route::post('/deliveries/{shipment}/chat', [MobileDeliveryChatController::class, 'riderSend'])->middleware('throttle:20,1');
            Route::get('/history', [MobileRiderDashboardController::class, 'history']);
            Route::get('/earnings', [MobileRiderDashboardController::class, 'earnings']);

            Route::patch('/account/phone', [MobileRiderAccountController::class, 'updatePhone']);
            Route::patch('/account/gender', [MobileRiderAccountController::class, 'updateGender']);
            Route::put('/account/address', [MobileRiderAccountController::class, 'updateAddress']);
            Route::put('/account/password', [MobileRiderAccountController::class, 'updatePassword']);

            Route::get('/support', [MobileSupportController::class, 'show']);
            Route::get('/support/unread', [MobileSupportController::class, 'unread']);
            Route::post('/support/messages', [MobileSupportController::class, 'send'])->middleware('throttle:20,1');
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

            Route::get('/account/identity', [MobileAccountController::class, 'identity']);
            Route::post('/account/identity', [MobileAccountController::class, 'submitIdentity'])->middleware('throttle:10,1');

            Route::post('/order-items/{orderItem}/review', [MobileOrderController::class, 'review'])->middleware('throttle:10,1');
            Route::post('/orders/{order}/complaint', [MobileComplaintController::class, 'store'])->middleware('throttle:10,1');

            // Chat with sellers; the thread endpoints are the website's own JSON chat API.
            Route::get('/messages', [MobileChatController::class, 'inbox']);
            Route::get('/messages/unread', [MobileChatController::class, 'unread']);
            Route::post('/products/{product:slug}/chat', [MobileChatController::class, 'start'])->middleware('throttle:20,1');
            Route::prefix('messages/{conversation}')->whereNumber('conversation')->group(function () {
                Route::get('/', [ChatController::class, 'messages']);
                Route::post('/', [ChatController::class, 'send'])->middleware('throttle:30,1');
                Route::post('/typing', [ChatController::class, 'typing']);
                Route::post('/read', [ChatController::class, 'read']);
            });

            Route::get('/support', [MobileSupportController::class, 'show']);
            Route::get('/support/unread', [MobileSupportController::class, 'unread']);
            Route::post('/support/messages', [MobileSupportController::class, 'send'])->middleware('throttle:20,1');
        });
    });
});

/** Complaint cases for the buyer and rider apps; each case checks that the user is part of it. */
$complaintRoutes = function (): void {
    Route::prefix('complaints')->name('complaints.')->group(function () {
        Route::get('/', [MobileComplaintController::class, 'index'])->name('index');
        Route::get('/categories', [MobileComplaintController::class, 'categories'])->name('categories');
        Route::get('/messages/{message}/attachment', [MobileComplaintController::class, 'attachment'])->name('attachment');
        Route::get('/{dispute}', [MobileComplaintController::class, 'show'])->name('show');
        Route::post('/{dispute}/messages', [MobileComplaintController::class, 'reply'])->middleware('throttle:20,1')->name('reply');
        Route::get('/{dispute}/evidence/{index}', [MobileComplaintController::class, 'evidence'])->whereNumber('index')->name('evidence');
    });
};

// Buyers follow the complaints they filed; riders follow those about deliveries they handled.
Route::prefix('mobile')->middleware(['throttle:60,1', 'auth:sanctum'])->group(function () use ($complaintRoutes) {
    Route::middleware('mobile.buyer')->name('mobile.')->group($complaintRoutes);
    Route::middleware('mobile.rider')->prefix('rider')->name('mobile.rider.')->group($complaintRoutes);
});
