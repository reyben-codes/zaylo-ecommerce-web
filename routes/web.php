<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\AdminController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get('/', fn() => view('index'))->name('home');

/*
|--------------------------------------------------------------------------
| Auth Routes (Guest only)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login',    [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',   [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register',[AuthController::class, 'register']);
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

/*
|--------------------------------------------------------------------------
| Buyer Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:buyer'])->prefix('buyer')->name('buyer.')->group(function () {
    Route::get('/dashboard', [BuyerController::class, 'dashboard'])->name('dashboard');
    Route::get('/products',  [BuyerController::class, 'products'])->name('products');
    Route::get('/orders',    [BuyerController::class, 'orders'])->name('orders');
    Route::get('/cart',      [BuyerController::class, 'cart'])->name('cart');
    Route::get('/wishlist',  [BuyerController::class, 'wishlist'])->name('wishlist');
    Route::get('/account',   [BuyerController::class, 'account'])->name('account');
    Route::get('/chat',      [BuyerController::class, 'chat'])->name('chat');
});

/*
|--------------------------------------------------------------------------
| Seller Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/products',  [SellerController::class, 'products'])->name('products');
    Route::get('/orders',    [SellerController::class, 'orders'])->name('orders');
    Route::get('/inventory', [SellerController::class, 'inventory'])->name('inventory');
    Route::get('/handover',  [SellerController::class, 'handover'])->name('handover');
    Route::get('/reports',   [SellerController::class, 'reports'])->name('reports');
    Route::get('/account',   [SellerController::class, 'account'])->name('account');
    Route::get('/chat',      [SellerController::class, 'chat'])->name('chat');
});

/*
|--------------------------------------------------------------------------
| Courier Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:courier'])->prefix('courier')->name('courier.')->group(function () {
    Route::get('/dashboard',  [CourierController::class, 'dashboard'])->name('dashboard');
    Route::get('/deliveries', [CourierController::class, 'deliveries'])->name('deliveries');
    Route::get('/earnings',   [CourierController::class, 'earnings'])->name('earnings');
    Route::get('/history',    [CourierController::class, 'history'])->name('history');
    Route::get('/account',    [CourierController::class, 'account'])->name('account');
    Route::get('/chat',       [CourierController::class, 'chat'])->name('chat');
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard',     [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users',         [AdminController::class, 'users'])->name('users');
    Route::get('/registrations', [AdminController::class, 'registrations'])->name('registrations');
    Route::get('/disputes',      [AdminController::class, 'disputes'])->name('disputes');
    Route::get('/compliance',    [AdminController::class, 'compliance'])->name('compliance');
    Route::get('/commissions',   [AdminController::class, 'commissions'])->name('commissions');
    Route::get('/reports',       [AdminController::class, 'reports'])->name('reports');
    Route::get('/settings',      [AdminController::class, 'settings'])->name('settings');
    Route::get('/account',       [AdminController::class, 'account'])->name('account');
    Route::get('/chat',          [AdminController::class, 'chat'])->name('chat');
});
