<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\SellerController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    $newArrivals = Schema::hasTable('products')
        ? Product::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->withCount(['variants as active_variants_count' => fn ($query) => $query->where('is_active', true)])
            ->latest()
            ->limit(8)
            ->get()
        : collect();

    return view('index', compact('newArrivals'));
})->name('home');
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
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/email/verify', [AuthController::class, 'verificationNotice'])->name('verification.notice');
    Route::post('/email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:6,1')->name('verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware('throttle:6,1')->name('verification.send');
});

Route::middleware(['auth', 'active', 'verified', 'role:buyer', 'nocache'])->prefix('buyer')->name('buyer.')->group(function () {
    Route::get('/dashboard', [BuyerController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', fn () => redirect()->route('products.index'))->name('products');
    Route::get('/orders', [BuyerController::class, 'orders'])->name('orders');
    Route::post('/orders/{order}/cancel', [BuyerController::class, 'cancelOrder'])->name('orders.cancel');
    Route::get('/cart', [BuyerController::class, 'cart'])->name('cart');
    Route::post('/cart/{product}', [BuyerController::class, 'addToCart'])->name('cart.add');
    Route::patch('/cart/items/{cartItem}', [BuyerController::class, 'updateCart'])->name('cart.update');
    Route::delete('/cart/items/{cartItem}', [BuyerController::class, 'removeCartItem'])->name('cart.remove');
    Route::post('/checkout', [BuyerController::class, 'checkout'])->name('checkout');
    Route::get('/wishlist', [BuyerController::class, 'wishlist'])->name('wishlist');
    Route::post('/wishlist/{product}', [BuyerController::class, 'toggleWishlist'])->name('wishlist.toggle');
    Route::get('/account', [BuyerController::class, 'account'])->name('account');
    Route::patch('/account/profile', [BuyerController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [BuyerController::class, 'updatePassword'])->name('account.password');
    Route::get('/chat', [BuyerController::class, 'chat'])->name('chat');
});

Route::middleware(['auth', 'active', 'verified', 'role:seller', 'nocache'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/products', [SellerController::class, 'products'])->name('products');
    Route::post('/products', [SellerController::class, 'storeProduct'])->name('products.store');
    Route::put('/products/{product}', [SellerController::class, 'updateProduct'])->name('products.update');
    Route::delete('/products/{product}', [SellerController::class, 'deleteProduct'])->name('products.destroy');
    Route::get('/orders', [SellerController::class, 'orders'])->name('orders');
    Route::patch('/orders/{order}/status', [SellerController::class, 'updateOrderStatus'])->name('orders.status');
    Route::get('/inventory', [SellerController::class, 'inventory'])->name('inventory');
    Route::get('/handover', [SellerController::class, 'handover'])->name('handover');
    Route::get('/reports', [SellerController::class, 'reports'])->name('reports');
    Route::get('/account', [SellerController::class, 'account'])->name('account');
    Route::get('/chat', [SellerController::class, 'chat'])->name('chat');
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
    Route::get('/disputes', [AdminController::class, 'disputes'])->name('disputes');
    Route::get('/compliance', [AdminController::class, 'compliance'])->name('compliance');
    Route::get('/commissions', [AdminController::class, 'commissions'])->name('commissions');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::get('/account', [AdminController::class, 'account'])->name('account');
    Route::get('/chat', [AdminController::class, 'chat'])->name('chat');
});
