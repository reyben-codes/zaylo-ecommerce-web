<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GcashPaymentController;
use App\Http\Controllers\MobileAccountController;
use App\Http\Controllers\MobileAppVersionController;
use App\Http\Controllers\MobileAuthenticationController;
use App\Http\Controllers\MobileChatController;
use App\Http\Controllers\MobileComplaintController;
use App\Http\Controllers\MobileDeliveryChatController;
use App\Http\Controllers\MobileOrderController;
use App\Http\Controllers\MobileProfilePhotoController;
use App\Http\Controllers\MobileRiderAccountController;
use App\Http\Controllers\MobileRiderAttendanceController;
use App\Http\Controllers\MobileRiderAuthenticationController;
use App\Http\Controllers\MobileRiderDashboardController;
use App\Http\Controllers\MobileRiderDeliveryController;
use App\Http\Controllers\MobileRiderPickupController;
use App\Http\Controllers\MobileRiderSupportController;
use App\Http\Controllers\MobileRiderWalletController;
use App\Http\Controllers\MobileShopController;
use App\Http\Controllers\MobileShoppingController;
use App\Http\Controllers\MobileStorefrontController;
use App\Http\Controllers\ShopController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile app API (/api/mobile/...)
|--------------------------------------------------------------------------
|
| The ZAYLO buyer and rider app. These endpoints use the same models, cart, checkout and address services and
| parcel workflow as the website. Signed-in requests send "Authorization: Bearer {token}" from sign-in
| (the "mobile" guard). Philippine location lookups use the website's public /api/locations routes.
|
*/
// Complaint cases, shared by the buyer and rider apps (route names end up as mobile.complaints.* and
// mobile.rider.complaints.*).
$complaintRoutes = function () {
    Route::prefix('complaints')->name('complaints.')->group(function () {
        Route::get('/', [MobileComplaintController::class, 'index'])->name('index');
        Route::get('/categories', [MobileComplaintController::class, 'categories'])->name('categories');
        Route::get('/messages/{message}/attachment', [MobileComplaintController::class, 'attachment'])->name('attachment');
        Route::get('/{dispute}', [MobileComplaintController::class, 'show'])->name('show');
        Route::post('/{dispute}/messages', [MobileComplaintController::class, 'reply'])->middleware('throttle:20,1,mobile-complaint-reply')->name('reply');
        Route::get('/{dispute}/evidence/{index}', [MobileComplaintController::class, 'evidence'])->whereNumber('index')->name('evidence');
    });
};

Route::prefix('mobile')->name('mobile.')->middleware('throttle:60,1')->group(function () use ($complaintRoutes) {
    Route::get('/app-version', [MobileAppVersionController::class, 'show'])->name('app-version');
    Route::get('/home', [MobileStorefrontController::class, 'home'])->name('home');
    Route::get('/products', [MobileStorefrontController::class, 'products'])->name('products');
    Route::get('/products/{product:slug}', [MobileStorefrontController::class, 'show'])->name('products.show');
    Route::get('/products/{product:slug}/reviews', [MobileStorefrontController::class, 'reviews'])->name('products.reviews');
    Route::get('/shops', [MobileShopController::class, 'index'])->name('shops');
    Route::get('/shops/{shop}', [MobileShopController::class, 'show'])->whereNumber('shop')->name('shops.show');
    Route::get('/shops/{shop}/products', [MobileShopController::class, 'products'])->whereNumber('shop')->name('shops.products');
    Route::get('/policies', [MobileStorefrontController::class, 'policies'])->name('policies');
    Route::get('/policies/{slug}', [MobileStorefrontController::class, 'policy'])->name('policies.show');
    // The help bot's questions and answers (?role=buyer or rider).
    Route::get('/faq', [FaqController::class, 'mobile'])->name('faq');
    Route::get('/logistics-partners', [MobileRiderAuthenticationController::class, 'partners'])->name('logistics-partners');
    // The rider sign-up form's partners and vehicle types (newer app builds use this path).
    Route::get('/rider/logistics-partners', [MobileRiderAuthenticationController::class, 'partners'])->name('rider.logistics-partners');

    // Pre-upload check of rider sign-up fields; shares the 60/min group limit.
    Route::post('/rider/register/check', [MobileRiderAuthenticationController::class, 'checkRegistration'])->name('rider.register.check');

    Route::middleware('throttle:6,1,mobile-auth')->group(function () {
        Route::post('/login', [MobileAuthenticationController::class, 'login'])->name('login');
        Route::post('/google', [MobileAuthenticationController::class, 'google'])->name('google');
        Route::post('/rider/login', [MobileRiderAuthenticationController::class, 'login'])->name('rider.login');
        Route::post('/courier/login', [MobileRiderAuthenticationController::class, 'courierLogin'])->name('courier.login');
        Route::post('/rider/google', [MobileRiderAuthenticationController::class, 'google'])->name('rider.google');
        Route::post('/courier/google', [MobileRiderAuthenticationController::class, 'courierGoogle'])->name('courier.google');
        Route::post('/rider/register', [MobileRiderAuthenticationController::class, 'register'])->name('rider.register');
        Route::post('/register', [MobileAuthenticationController::class, 'register'])->name('register');
        Route::post('/verify-email', [MobileAuthenticationController::class, 'verifyEmail'])->name('verify-email');
        Route::post('/resend-verification', [MobileAuthenticationController::class, 'resendVerification'])->name('resend-verification');
        Route::post('/forgot-password', [MobileAuthenticationController::class, 'forgotPassword'])->name('forgot-password');
    });

    Route::middleware('auth:mobile')->group(function () use ($complaintRoutes) {
        Route::get('/me', [MobileAuthenticationController::class, 'me'])->name('me');
        Route::put('/push-device', [\App\Http\Controllers\MobilePushController::class, 'store'])->middleware('throttle:10,1,mobile-push')->name('push-device.store');
        Route::delete('/push-device', [\App\Http\Controllers\MobilePushController::class, 'destroy'])->name('push-device.destroy');
        Route::post('/push-device/test', [\App\Http\Controllers\MobilePushController::class, 'test'])->middleware('throttle:3,1,mobile-push-test')->name('push-device.test');
        // Questions the help bot could not match go to the AI models, within limits shared with the website.
        Route::post('/help/ask', [FaqController::class, 'ask'])->middleware(['throttle:10,1,zay-ask', 'throttle:20,1440,zay-ask-day'])->name('help.ask');
        // Profile photo, for buyers and riders alike.
        Route::post('/account/photo', [MobileProfilePhotoController::class, 'update'])->middleware('throttle:10,1,mobile-photo')->name('account.photo.update');
        Route::delete('/account/photo', [MobileProfilePhotoController::class, 'destroy'])->name('account.photo.destroy');
        Route::post('/logout', [MobileAuthenticationController::class, 'logout'])->name('logout');

        Route::middleware('mobile.rider')->prefix('rider')->name('rider.')->group(function () use ($complaintRoutes) {
            Route::get('/dashboard', [MobileRiderDashboardController::class, 'dashboard'])->name('dashboard');
            Route::get('/logistics-chat', [\App\Http\Controllers\MobileRiderLogisticsChatController::class, 'index'])->name('logistics-chat');
            Route::post('/logistics-chat', [\App\Http\Controllers\MobileRiderLogisticsChatController::class, 'send'])->middleware('throttle:20,1,mobile-logistics-chat')->name('logistics-chat.send');
            Route::get('/logistics-chat/{message}/{kind}', [\App\Http\Controllers\MobileRiderLogisticsChatController::class, 'media'])->whereIn('kind', ['photo', 'video'])->name('logistics-chat.media');
            Route::get('/attendance', [MobileRiderAttendanceController::class, 'index'])->name('attendance');
            // Scanning a parcel label's QR code starts a delivery, and again at the door confirms it.
            Route::post('/deliveries/scan', [MobileRiderDashboardController::class, 'scan'])->middleware('throttle:30,1,mobile-rider-scan')->name('deliveries.scan');
            Route::post('/seller-pickups/scan', [MobileRiderDashboardController::class, 'scanSellerPickup'])->middleware('throttle:30,1,mobile-rider-pickup-scan')->name('seller-pickups.scan');
            Route::post('/returns/scan', [MobileRiderDashboardController::class, 'scanReturn'])->middleware('throttle:30,1,mobile-rider-return-scan')->name('returns.scan');
            // Scanning the logistics hub's attendance QR code times the rider in, and again times them out.
            // The open app reports the rider's location while a parcel is out for delivery.
            Route::post('/location', [MobileRiderDashboardController::class, 'location'])->middleware('throttle:20,1,mobile-rider-location')->name('location');
            Route::post('/attendance/scan', [MobileRiderDashboardController::class, 'scanAttendance'])->middleware('throttle:20,1,mobile-rider-attendance')->name('attendance.scan');
            Route::post('/deliveries/{shipment}/claim', [MobileRiderDashboardController::class, 'claim'])->name('deliveries.claim');
            // The rider answers each assignment from the logistics partner: accept it, or decline it with a reason.
            Route::post('/deliveries/{shipment}/accept', [MobileRiderDashboardController::class, 'accept'])->middleware('throttle:60,1,mobile-rider-answer')->name('deliveries.accept');
            Route::post('/deliveries/{shipment}/decline', [MobileRiderDashboardController::class, 'decline'])->middleware('throttle:60,1,mobile-rider-answer')->name('deliveries.decline');
            Route::get('/deliveries/{shipment}', [MobileRiderDashboardController::class, 'show'])->name('deliveries.show');
            // Proof of delivery: a photo of the parcel at the door, right after the delivery scan.
            Route::post('/deliveries/{shipment}/proof', [MobileRiderDashboardController::class, 'proof'])->middleware('throttle:20,1,mobile-rider-proof')->name('deliveries.proof');
            Route::post('/deliveries/{shipment}/collect-cod', [MobileRiderDashboardController::class, 'collectCod'])->middleware('throttle:30,1,mobile-rider-cod')->name('deliveries.collect-cod');
            Route::patch('/deliveries/{shipment}', [MobileRiderDashboardController::class, 'update'])->name('deliveries.update');
            // Chat with the buyer while the parcel is with the rider.
            Route::get('/deliveries/{shipment}/chat', [MobileDeliveryChatController::class, 'riderShow'])->name('deliveries.chat');
            Route::post('/deliveries/{shipment}/chat', [MobileDeliveryChatController::class, 'riderSend'])->middleware('throttle:20,1,mobile-delivery-chat')->name('deliveries.chat.send');
            Route::get('/deliveries/{shipment}/chat/{message}/{kind}', [MobileDeliveryChatController::class, 'riderMedia'])->whereIn('kind', ['photo', 'video'])->name('deliveries.chat.media');
            Route::get('/history', [MobileRiderDashboardController::class, 'history'])->name('history');
            Route::get('/earnings', [MobileRiderDashboardController::class, 'earnings'])->name('earnings');
            // The rider's wallet: a commission per delivered parcel, withdrawn to GCash.
            Route::get('/wallet', [MobileRiderWalletController::class, 'show'])->name('wallet');
            Route::put('/wallet/gcash', [MobileRiderWalletController::class, 'updateAccount'])->middleware('throttle:20,1,mobile-rider-gcash')->name('wallet.gcash');
            Route::post('/wallet/withdrawals', [MobileRiderWalletController::class, 'withdraw'])->middleware('throttle:10,1,mobile-rider-withdraw')->name('wallet.withdraw');
            Route::get('/reports', [MobileRiderDashboardController::class, 'reports'])->name('reports');

            Route::patch('/account/phone', [MobileRiderAccountController::class, 'updatePhone'])->name('account.phone');
            Route::patch('/account/gender', [MobileRiderAccountController::class, 'updateGender'])->name('account.gender');
            Route::put('/account/address', [MobileRiderAccountController::class, 'updateAddress'])->name('account.address');
            Route::put('/account/password', [MobileRiderAccountController::class, 'updatePassword'])->name('account.password');

            Route::get('/support', [MobileRiderSupportController::class, 'show'])->name('support');
            Route::get('/support/unread', [MobileRiderSupportController::class, 'unread'])->name('support.unread');
            Route::post('/support/messages', [MobileRiderSupportController::class, 'send'])->middleware('throttle:20,1,mobile-support')->name('support.send');
            Route::get('/support/messages/{message}/{kind}', [MobileRiderSupportController::class, 'media'])->whereIn('kind', ['photo', 'video'])->name('support.media');

            // Complaints about deliveries the rider handled.
            $complaintRoutes();
        });

        Route::middleware(['mobile.rider', 'mobile.rider.pickup'])->prefix('rider/pickup')->name('rider.pickup.')->group(function () {
            Route::get('/queue', [MobileRiderPickupController::class, 'queue'])->name('queue');
            Route::post('/scan', [MobileRiderPickupController::class, 'scan'])->middleware('throttle:30,1,mobile-pickup-scan')->name('scan');
            Route::get('/manifest', [MobileRiderPickupController::class, 'manifest'])->name('manifest');
            Route::get('/dropoff', [MobileRiderPickupController::class, 'hubDropoff'])->name('dropoff');
        });

        Route::middleware(['mobile.rider', 'mobile.rider.delivery'])->prefix('rider/delivery')->name('rider.delivery.')->group(function () {
            Route::get('/manifest', [MobileRiderDeliveryController::class, 'manifest'])->name('manifest');
            Route::post('/scan', [MobileRiderDeliveryController::class, 'scan'])->middleware('throttle:30,1,mobile-delivery-scan')->name('scan');
            Route::post('/proof/{shipment}', [MobileRiderDeliveryController::class, 'proof'])->middleware('throttle:20,1,mobile-delivery-proof')->name('proof');
            Route::post('/fail/{shipment}', [MobileRiderDeliveryController::class, 'fail'])->name('fail');
        });

        Route::middleware('mobile.buyer')->group(function () use ($complaintRoutes) {
            Route::get('/wishlist', [MobileShoppingController::class, 'wishlist'])->name('wishlist');
            Route::get('/following', [MobileShopController::class, 'following'])->name('following');
            Route::post('/shops/{shop}/follow', [ShopController::class, 'follow'])->whereNumber('shop')->name('shops.follow');
            Route::post('/shops/{shop}/chat', [MobileChatController::class, 'startAboutShop'])->whereNumber('shop')->middleware('throttle:20,1,mobile-chat-start')->name('shops.chat');
            Route::post('/wishlist/{product:slug}', [MobileShoppingController::class, 'toggleWishlist'])->name('wishlist.toggle');

            Route::get('/cart', [MobileShoppingController::class, 'cart'])->name('cart');
            Route::post('/cart/remove', [MobileShoppingController::class, 'removeCartItems'])->name('cart.remove-selected');
            Route::post('/cart/{product:slug}', [MobileShoppingController::class, 'addToCart'])->name('cart.add');
            Route::patch('/cart/items/{cartItem}', [MobileShoppingController::class, 'updateCartItem'])->name('cart.update');
            Route::delete('/cart/items/{cartItem}', [MobileShoppingController::class, 'removeCartItem'])->name('cart.remove');

            Route::post('/checkout/quote', [MobileShoppingController::class, 'quote'])->name('checkout.quote');
            Route::post('/checkout', [MobileShoppingController::class, 'placeOrder'])->middleware('throttle:10,1,mobile-checkout')->name('checkout');
            Route::post('/vouchers/{code}/claim', [MobileShoppingController::class, 'claimVoucher'])->middleware('throttle:30,1,mobile-voucher-claim')->name('vouchers.claim');

            Route::get('/orders', [MobileOrderController::class, 'index'])->name('orders');
            Route::get('/orders/{order}', [MobileOrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/cancel', [MobileOrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('/orders/{order}/buy-again', [MobileOrderController::class, 'buyAgain'])->middleware('throttle:20,1,mobile-buy-again')->name('orders.buy-again');
            Route::post('/orders/{order}/gcash-reference', [GcashPaymentController::class, 'mobileSubmit'])->middleware('throttle:20,1,mobile-gcash-reference')->name('orders.gcash');
            Route::post('/orders/{order}/received', [MobileOrderController::class, 'received'])->middleware('throttle:20,1,mobile-order-received')->name('orders.received');
            Route::get('/orders/{order}/delivery-photo', [MobileOrderController::class, 'deliveryPhoto'])->name('orders.delivery-photo');
            // The parcel on a map: the buyer's pin, and the rider while it is out for delivery.
            Route::get('/orders/{order}/tracking', [MobileOrderController::class, 'tracking'])->middleware('throttle:30,1,mobile-order-tracking')->name('orders.tracking');
            Route::get('/orders/{order}/chat', [MobileDeliveryChatController::class, 'buyerShow'])->name('orders.chat');
            Route::post('/orders/{order}/chat', [MobileDeliveryChatController::class, 'buyerSend'])->middleware('throttle:20,1,mobile-delivery-chat')->name('orders.chat.send');
            Route::get('/orders/{order}/chat/{message}/{kind}', [MobileDeliveryChatController::class, 'buyerMedia'])->whereIn('kind', ['photo', 'video'])->name('orders.chat.media');
            Route::post('/orders/{order}/complaint', [MobileComplaintController::class, 'store'])->middleware('throttle:10,1,mobile-complaint')->name('orders.complaint');
            Route::post('/order-items/{orderItem}/review', [MobileOrderController::class, 'review'])->middleware('throttle:10,1,mobile-review')->name('order-items.review');

            Route::patch('/account/profile', [MobileAccountController::class, 'updateProfile'])->name('account.profile');
            Route::put('/account/password', [MobileAccountController::class, 'updatePassword'])->name('account.password');
            Route::get('/account/identity', [MobileAccountController::class, 'identity'])->name('account.identity');
            Route::post('/account/identity', [MobileAccountController::class, 'submitIdentity'])->middleware('throttle:10,1,mobile-identity')->name('account.identity.submit');

            Route::get('/addresses', [MobileAccountController::class, 'addresses'])->name('addresses');
            Route::post('/addresses', [MobileAccountController::class, 'storeAddress'])->name('addresses.store');
            Route::put('/addresses/{address}', [MobileAccountController::class, 'updateAddress'])->name('addresses.update');
            Route::patch('/addresses/{address}/default', [MobileAccountController::class, 'makeDefaultAddress'])->name('addresses.default');
            Route::delete('/addresses/{address}', [MobileAccountController::class, 'destroyAddress'])->name('addresses.destroy');

            // Messages with sellers: the website's own chat actions, so the app and the website share every
            // conversation, message, typing signal and read receipt.
            Route::prefix('chat')->name('chat.')->group(function () {
                Route::get('/conversations', [ChatController::class, 'inbox'])->name('inbox');
                Route::get('/unread', [MobileChatController::class, 'unread'])->name('unread');
                Route::get('/typing', [ChatController::class, 'typingStatus'])->name('typing-status');
                Route::post('/products/{product:slug}', [MobileChatController::class, 'startForProduct'])->middleware('throttle:20,1,mobile-chat-start')->name('start.product');
                Route::post('/shops/{shop}', [MobileChatController::class, 'startForShop'])->whereNumber('shop')->middleware('throttle:20,1,mobile-chat-start')->name('start.shop');
                Route::get('/conversations/{conversation}', [MobileChatController::class, 'show'])->whereNumber('conversation')->name('show');
                Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages'])->whereNumber('conversation')->name('messages');
                Route::post('/conversations/{conversation}/messages', [ChatController::class, 'send'])->whereNumber('conversation')->middleware('throttle:30,1,mobile-chat-send')->name('send');
                Route::post('/conversations/{conversation}/typing', [ChatController::class, 'typing'])->whereNumber('conversation')->name('typing');
                Route::post('/conversations/{conversation}/read', [ChatController::class, 'read'])->whereNumber('conversation')->name('read');
            });

            // The app's Messages screens: the same conversations through the website's chat actions.
            Route::get('/messages', [MobileChatController::class, 'inbox'])->name('messages');
            Route::get('/messages/unread', [MobileChatController::class, 'unread'])->name('messages.unread');
            Route::post('/products/{product:slug}/chat', [MobileChatController::class, 'startAboutProduct'])->middleware('throttle:20,1,mobile-chat-start')->name('messages.start');
            Route::prefix('messages/{conversation}')->whereNumber('conversation')->name('messages.')->group(function () {
                Route::get('/', [ChatController::class, 'messages'])->name('show');
                Route::post('/', [ChatController::class, 'send'])->middleware('throttle:30,1,mobile-chat-send')->name('send');
                Route::post('/typing', [ChatController::class, 'typing'])->name('typing');
                Route::post('/read', [ChatController::class, 'read'])->name('read');
                Route::get('/media/{message}/{kind}', [ChatController::class, 'media'])->whereNumber('message')->whereIn('kind', ['photo', 'video'])->name('media');
            });

            // Help & support: the same thread as the website, answered from the Admin Center.
            Route::get('/support', [MobileRiderSupportController::class, 'show'])->name('support');
            Route::get('/support/unread', [MobileRiderSupportController::class, 'unread'])->name('support.unread');
            Route::post('/support/messages', [MobileRiderSupportController::class, 'send'])->middleware('throttle:20,1,mobile-support')->name('support.send');
            Route::get('/support/messages/{message}/{kind}', [MobileRiderSupportController::class, 'media'])->whereIn('kind', ['photo', 'video'])->name('support.media');

            // The buyer's complaints.
            $complaintRoutes();

            Route::get('/notifications', [MobileAccountController::class, 'notifications'])->name('notifications');
            Route::post('/notifications/read', [MobileAccountController::class, 'markNotificationsRead'])->name('notifications.read');
        });
    });
});
