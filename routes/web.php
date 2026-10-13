<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\BuyerNotificationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProductSaleController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\IdentityVerificationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\SellerApplicationController;
use App\Http\Controllers\SellerController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    $newArrivals = collect();

    if (Schema::hasTable('products')) {
        $query = Product::withReviewSummary()->where('is_active', true);

        if (Schema::hasColumn('products', 'stock')) {
            $query->where('stock', '>', 0)
                ->with('images')
                ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)]);
        } else {
            $query->whereHas('variants', fn ($builder) => $builder->where('is_active', true)->where('stock', '>', 0))
                ->with(['images', 'defaultVariant', 'category'])
                ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)]);
        }

        $newArrivals = $query->latest()->limit(10)->get();
    }

    return view('index', compact('newArrivals'));
})->name('home');
Route::view('/terms', 'legal.terms')->name('terms');
Route::get('/products', [BuyerController::class, 'products'])->name('products.index');
Route::get('/products/{product}', [BuyerController::class, 'showProduct'])->name('products.show');

// Read-only public geography, using fixed upstream paths and rate limiting.
Route::prefix('api/locations')->name('locations.')->middleware('throttle:60,1')->group(function () {
    Route::get('/regions', [LocationController::class, 'regions'])->name('regions');
    Route::get('/regions/{regionCode}/provinces', [LocationController::class, 'provinces'])->where('regionCode', '[0-9]{9}')->name('provinces');
    Route::get('/regions/{regionCode}/cities-municipalities', [LocationController::class, 'provinceFreeCities'])->where('regionCode', '[0-9]{9}')->name('region-cities');
    Route::get('/provinces/{provinceCode}/cities-municipalities', [LocationController::class, 'cities'])->where('provinceCode', '[0-9]{9}')->name('cities');
    Route::get('/cities-municipalities/{cityCode}/barangays', [LocationController::class, 'barangays'])->where('cityCode', '[0-9]{9}')->name('barangays');
});

Route::middleware(['auth', 'active', 'verified', 'role:buyer,seller,courier', 'nocache'])
    ->prefix('account/addresses')->name('addresses.')->group(function () {
        Route::get('/', [AddressController::class, 'index'])->name('index');
        Route::get('/create', [AddressController::class, 'create'])->name('create');
        Route::post('/', [AddressController::class, 'store'])->name('store');
        Route::get('/{address}/edit', [AddressController::class, 'edit'])->name('edit');
        Route::put('/{address}', [AddressController::class, 'update'])->name('update');
        Route::patch('/{address}/default', [AddressController::class, 'makeDefault'])->name('default');
        Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
    });

Route::middleware('guest')->group(function () {
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:10,1')->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:10,1')->name('google.callback');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/seller/login', [AuthController::class, 'showSellerLogin'])->name('seller.login');
    Route::post('/seller/login', [AuthController::class, 'sellerLogin'])->middleware('throttle:login')->name('seller.login.submit');
    Route::get('/logistics/login', [AuthController::class, 'showLogisticsLogin'])->name('logistics.login');
    Route::post('/logistics/login', [AuthController::class, 'logisticsLogin'])->middleware('throttle:login')->name('logistics.login.submit');
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:login')->name('admin.login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Email codes: for a sign-up still waiting for its code (no account yet) or an older unverified account.
Route::get('/email/verify', [AuthController::class, 'verificationNotice'])->name('verification.notice');
Route::post('/email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:6,1')->name('verification.verify');
Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1')->name('verification.send');
Route::post('/register/cancel', [AuthController::class, 'cancelRegistration'])->name('registration.cancel');

Route::middleware(['auth', 'active', 'verified', 'role:buyer', 'nocache'])->prefix('buyer')->name('buyer.')->group(function () {
    Route::get('/dashboard', [BuyerController::class, 'dashboard'])->name('dashboard');
    Route::get('/notifications', [BuyerNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/open', [BuyerNotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [BuyerNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/products', fn () => redirect()->route('products.index'))->name('products');
    Route::get('/orders', [BuyerController::class, 'orders'])->name('orders');
    Route::get('/order-items/{orderItem}/review', [\App\Http\Controllers\ProductReviewController::class, 'create'])->name('reviews.create');
    Route::post('/order-items/{orderItem}/review', [\App\Http\Controllers\ProductReviewController::class, 'store'])->middleware('throttle:10,1')->name('reviews.store');
    Route::post('/orders/{order}/cancel', [BuyerController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/cart', [BuyerController::class, 'cart'])->name('cart');
    Route::post('/cart/selection', [BuyerController::class, 'cartSelection'])->name('cart.selection');
    Route::post('/cart/{product}', [BuyerController::class, 'addToCart'])->name('cart.add');
    Route::patch('/cart/items', [BuyerController::class, 'updateCartItems'])->name('cart.update-all');
    Route::patch('/cart/items/{cartItem}', [BuyerController::class, 'updateCart'])->name('cart.update');
    Route::delete('/cart/items/{cartItem}', [BuyerController::class, 'removeCartItem'])->name('cart.remove');
    Route::get('/checkout', [BuyerController::class, 'showCheckout'])->name('checkout.show');
    Route::post('/checkout', [BuyerController::class, 'checkout'])->name('checkout');
    Route::get('/wishlist', [BuyerController::class, 'wishlist'])->name('wishlist');
    Route::post('/wishlist/{product}', [BuyerController::class, 'toggleWishlist'])->name('wishlist.toggle');
    Route::get('/account', [BuyerController::class, 'account'])->name('account');
    Route::patch('/account/profile', [BuyerController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [BuyerController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/identity', [IdentityVerificationController::class, 'store'])->middleware('throttle:10,1')->name('identity.store');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::post('/chat/products/{product}', [ChatController::class, 'start'])->middleware('throttle:20,1')->name('chat.start');
});

// Pending and rejected sellers can still reach their application, so this group skips the active check.
Route::middleware(['auth', 'verified', 'role:seller', 'nocache'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/application', [SellerApplicationController::class, 'show'])->name('application');
    Route::post('/application', [SellerApplicationController::class, 'update'])->middleware('throttle:10,1')->name('application.update');
});

Route::middleware(['auth', 'active', 'verified', 'role:seller', 'nocache'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', [SellerController::class, 'products'])->name('products');
    Route::post('/products', [SellerController::class, 'storeProduct'])->name('products.store');
    Route::get('/products/create', [SellerController::class, 'createProduct'])->name('products.create');
    Route::get('/products/{product}/edit', [SellerController::class, 'editProduct'])->name('products.edit');
    Route::patch('/products/{product}/stock', [SellerController::class, 'updateStock'])->name('products.stock');
    Route::put('/products/{product}/sale', [ProductSaleController::class, 'update'])->name('products.sale');
    Route::put('/products/{product}', [SellerController::class, 'updateProduct'])->name('products.update');
    Route::delete('/products/{product}', [SellerController::class, 'deleteProduct'])->name('products.destroy');
    Route::get('/orders', [SellerController::class, 'orders'])->name('orders');
    Route::patch('/orders/{order}/status', [SellerController::class, 'updateOrderStatus'])->name('orders.status');
    Route::get('/inventory', [SellerController::class, 'inventory'])->name('inventory');
    Route::get('/handover', [SellerController::class, 'handover'])->name('handover');
    Route::get('/reports', [SellerController::class, 'reports'])->name('reports');
    Route::get('/account', [SellerController::class, 'account'])->name('account');
    Route::patch('/account/profile', [SellerController::class, 'updateProfile'])->name('account.profile');
    Route::patch('/account/store', [SellerController::class, 'updateStore'])->name('account.store');
    Route::put('/account/password', [SellerController::class, 'updatePassword'])->name('account.password');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
});

Route::middleware(['auth', 'active', 'verified', 'role:buyer,seller', 'nocache'])->prefix('messages')->name('messages.')->group(function () {
    Route::get('/inbox', [ChatController::class, 'inbox'])->middleware('throttle:120,1')->name('inbox');
    Route::get('/{conversation}', [ChatController::class, 'messages'])->whereNumber('conversation')->middleware('throttle:120,1')->name('list');
    Route::post('/{conversation}', [ChatController::class, 'send'])->whereNumber('conversation')->middleware('throttle:30,1')->name('send');
    Route::post('/{conversation}/typing', [ChatController::class, 'typing'])->whereNumber('conversation')->middleware('throttle:60,1')->name('typing');
    Route::post('/{conversation}/read', [ChatController::class, 'read'])->whereNumber('conversation')->middleware('throttle:120,1')->name('read');
});

Route::middleware(['auth', 'active', 'verified', 'role:courier', 'nocache'])->prefix('courier')->name('courier.')->group(function () {
    Route::get('/dashboard', [CourierController::class, 'dashboard'])->name('dashboard');
    Route::get('/deliveries', [CourierController::class, 'deliveries'])->name('deliveries');
    Route::post('/deliveries/{shipment}/claim', [CourierController::class, 'claim'])->name('deliveries.claim');
    Route::patch('/deliveries/{shipment}', [CourierController::class, 'updateDelivery'])->name('deliveries.update');
    Route::get('/earnings', [CourierController::class, 'earnings'])->name('earnings');
    Route::get('/history', [CourierController::class, 'history'])->name('history');
    Route::get('/account', [CourierController::class, 'account'])->name('account');
    Route::get('/chat', [CourierController::class, 'chat'])->name('chat');
});

Route::middleware(['auth', 'active', 'verified', 'role:admin', 'nocache'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('users.status');
    Route::get('/registrations', [AdminController::class, 'registrations'])->name('registrations');
    Route::get('/applications/{user}', [AdminController::class, 'sellerApplication'])->name('seller-applications.show');
    Route::get('/applications/{user}/documents/{document}', [AdminController::class, 'sellerApplicationDocument'])->name('seller-applications.document');
    Route::get('/logistics', [AdminController::class, 'logistics'])->name('logistics');
    Route::get('/identity', [AdminController::class, 'identity'])->name('identity');
    Route::get('/identity/{verification}/document', [AdminController::class, 'identityDocument'])->name('identity.document');
    Route::patch('/identity/{verification}', [AdminController::class, 'reviewIdentity'])->name('identity.review');
    Route::get('/account', [AdminController::class, 'account'])->name('account');
    Route::patch('/account/profile', [AdminController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AdminController::class, 'updatePassword'])->name('account.password');
    // Not built yet: these show a placeholder rather than the earlier sample-data mockups.
    foreach (['disputes', 'compliance', 'commissions', 'reports', 'settings', 'chat'] as $section) {
        Route::get("/{$section}", [AdminController::class, 'comingSoon'])->name($section);
    }
});

Route::middleware(['auth', 'active', 'verified', 'role:logistics', 'nocache'])->prefix('logistics')->name('logistics.')->group(function () {
    Route::get('/dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
    Route::get('/riders', [LogisticsController::class, 'riders'])->name('riders');
    Route::get('/riders/{rider}/documents/{document}', [LogisticsController::class, 'riderDocument'])->name('riders.document');
    Route::patch('/riders/{rider}', [LogisticsController::class, 'updateRider'])->middleware('throttle:60,1')->name('riders.update');
    Route::get('/account', [LogisticsController::class, 'account'])->name('account');
    Route::patch('/account/company', [LogisticsController::class, 'updateCompany'])->name('account.company');
    Route::patch('/account/profile', [LogisticsController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [LogisticsController::class, 'updatePassword'])->name('account.password');
});
