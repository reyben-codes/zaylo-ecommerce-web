<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AdminComplianceController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminDisputeController;
use App\Http\Controllers\AdminFinanceController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\BuyerNotificationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\PublicFileController;
use App\Http\Controllers\ProductSaleController;
use App\Http\Controllers\ComplianceWarningController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\IdentityVerificationController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\LogisticsApplicationController;
use App\Http\Controllers\LogisticsChatController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\LogisticsOperationsController;
use App\Http\Controllers\SellerApplicationController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SortingCenterController;
use App\Http\Controllers\SupportChatController;
use App\Http\Controllers\WaybillController;
use App\Http\Controllers\WorkspaceNotificationController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::get('/', function () {
    $newArrivals = collect();

    if (Schema::hasTable('products')) {
        $query = Product::withReviewSummary()->visibleToBuyers();

        if (Schema::hasColumn('products', 'stock')) {
            $query->where('stock', '>', 0)
                ->with(['images', 'additionalCategories'])
                ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)]);
        } else {
            $query->whereHas('variants', fn ($builder) => $builder->where('is_active', true)->where('stock', '>', 0))
                ->with(['images', 'defaultVariant', 'category', 'additionalCategories'])
                ->withCount(['variants as active_variants_count' => fn ($builder) => $builder->where('is_active', true)]);
        }

        $newArrivals = $query->latest()->limit(10)->get();
    }

    return view('index', compact('newArrivals'));
})->name('home');
// Uploaded photos saved as "/storage/..." links: redirected to Cloudflare R2, or sent from storage/app/public.
Route::get('/storage/{path}', PublicFileController::class)->where('path', '.*')->name('storage.public');
// Shoppers on Android land here instead of the web store (App\Http\Middleware\RequireAndroidApp).
Route::view('/get-the-app', 'app-download', ['downloadUrl' => config('marketplace.android_app.download_url')])->name('app.download');
Route::get('/terms', [PolicyController::class, 'show'])->name('terms');
Route::get('/policies/{slug}', [PolicyController::class, 'show'])->name('policies.show');
Route::get('/products', [BuyerController::class, 'products'])->name('products.index');
Route::get('/products/{product}', [BuyerController::class, 'showProduct'])->name('products.show');
Route::get('/shops/{shop}', [ShopController::class, 'show'])->whereNumber('shop')->name('shops.show');

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
    Route::get('/rider/login', [AuthController::class, 'showRiderLogin'])->name('rider.login');
    Route::post('/rider/login', [AuthController::class, 'riderLogin'])->middleware('throttle:login')->name('rider.login.submit');
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
    Route::post('/chat/shops/{shop}', [ShopController::class, 'chat'])->whereNumber('shop')->middleware('throttle:20,1')->name('chat.shop');
    Route::post('/shops/{shop}/follow', [ShopController::class, 'follow'])->whereNumber('shop')->middleware('throttle:60,1')->name('shops.follow');
    Route::get('/following', [ShopController::class, 'following'])->name('following');
});

// Pending and rejected sellers can still reach their application, so this group skips the active check.
Route::middleware(['auth', 'verified', 'role:seller', 'nocache'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/application', [SellerApplicationController::class, 'show'])->name('application');
    Route::post('/application', [SellerApplicationController::class, 'update'])->middleware('throttle:10,1')->name('application.update');
});

Route::middleware(['auth', 'active', 'verified', 'role:seller', 'nocache'])->prefix('seller')->name('seller.')->group(function () {
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->name('dashboard');
    Route::get('/notifications', [WorkspaceNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/open', [WorkspaceNotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [WorkspaceNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/products', [SellerController::class, 'products'])->name('products');
    Route::post('/products', [SellerController::class, 'storeProduct'])->name('products.store');
    Route::get('/products/create', [SellerController::class, 'createProduct'])->name('products.create');
    Route::get('/products/{product}/edit', [SellerController::class, 'editProduct'])->name('products.edit');
    Route::patch('/products/{product}/stock', [SellerController::class, 'updateStock'])->name('products.stock');
    Route::put('/products/{product}/sale', [ProductSaleController::class, 'update'])->name('products.sale');
    Route::put('/products/{product}', [SellerController::class, 'updateProduct'])->name('products.update');
    Route::patch('/products/{product}/archive', [SellerController::class, 'archiveProduct'])->name('products.archive');
    Route::patch('/products/{product}/restore', [SellerController::class, 'restoreProduct'])->name('products.restore');
    Route::delete('/products/{product}', [SellerController::class, 'deleteProduct'])->name('products.destroy');
    Route::get('/orders', [SellerController::class, 'orders'])->name('orders');
    Route::patch('/orders/{order}/status', [SellerController::class, 'updateOrderStatus'])->name('orders.status');
    Route::get('/orders/{order}/waybill', [WaybillController::class, 'seller'])->middleware('throttle:60,1')->name('orders.waybill');
    Route::get('/orders/{order}/waybill/print', [WaybillController::class, 'sellerPrint'])->middleware('throttle:60,1')->name('orders.waybill.print');
    Route::get('/reports/pdf', [SellerController::class, 'reportPdf'])->middleware('throttle:30,1')->name('reports.pdf');
    Route::get('/inventory', [SellerController::class, 'inventory'])->name('inventory');
    Route::get('/handover', [SellerController::class, 'handover'])->name('handover');
    Route::get('/reports', [SellerController::class, 'reports'])->name('reports');
    Route::get('/activity', [ActivityLogController::class, 'seller'])->name('activity');
    Route::get('/account', [SellerController::class, 'account'])->name('account');
    Route::patch('/account/profile', [SellerController::class, 'updateProfile'])->name('account.profile');
    Route::patch('/account/store', [SellerController::class, 'updateStore'])->name('account.store');
    Route::put('/account/password', [SellerController::class, 'updatePassword'])->name('account.password');
    Route::get('/chat', [ChatController::class, 'index'])->name('chat');
    Route::get('/logistics-messages', [LogisticsChatController::class, 'seller'])->name('logistics-messages');
    Route::post('/warnings/{warning}/acknowledge', [ComplianceWarningController::class, 'acknowledge'])->name('warnings.acknowledge');
    Route::post('/logistics-messages', [LogisticsChatController::class, 'startFromSeller'])->middleware('throttle:20,1')->name('logistics-messages.start');
});

Route::middleware(['auth', 'active', 'verified', 'role:buyer,seller', 'nocache'])->prefix('messages')->name('messages.')->group(function () {
    Route::get('/inbox', [ChatController::class, 'inbox'])->middleware('throttle:120,1')->name('inbox');
    // Polled by the floating chat bubble to show typing dots while the chat is closed.
    Route::get('/typing', [ChatController::class, 'typingStatus'])->middleware('throttle:60,1')->name('typing-status');
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
    Route::get('/deliveries/{shipment}/waybill', [WaybillController::class, 'rider'])->middleware('throttle:60,1')->name('deliveries.waybill');
    Route::get('/deliveries/{shipment}/waybill/print', [WaybillController::class, 'riderPrint'])->middleware('throttle:60,1')->name('deliveries.waybill.print');
    Route::get('/earnings', [CourierController::class, 'earnings'])->name('earnings');
    Route::get('/history', [CourierController::class, 'history'])->name('history');
    Route::get('/activity', [ActivityLogController::class, 'courier'])->name('activity');
    Route::get('/account', [CourierController::class, 'account'])->name('account');
    Route::get('/chat', [LogisticsChatController::class, 'rider'])->name('chat');
});

Route::middleware(['auth', 'active', 'verified', 'role:sorting_center', 'nocache'])->prefix('sorting-center')->name('sorting-center.')->group(function () {
    Route::get('/dashboard', [SortingCenterController::class, 'dashboard'])->name('dashboard');
    Route::patch('/shipments/{shipment}/status', [SortingCenterController::class, 'updateStatus'])->name('shipments.status');
});

Route::middleware(['auth', 'active', 'verified', 'role:admin', 'nocache'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/notifications', [WorkspaceNotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/open', [WorkspaceNotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [WorkspaceNotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/status', [AdminController::class, 'updateUserStatus'])->name('users.status');
    Route::get('/registrations', [AdminController::class, 'registrations'])->name('registrations');
    Route::get('/applications/{user}', [AdminController::class, 'sellerApplication'])->name('seller-applications.show');
    Route::get('/applications/{user}/documents/{document}', [AdminController::class, 'sellerApplicationDocument'])->name('seller-applications.document');
    Route::get('/logistics', [AdminController::class, 'logistics'])->name('logistics');
    Route::get('/logistics/{provider}', [AdminController::class, 'logisticsApplication'])->name('logistics.show');
    Route::get('/logistics/{provider}/documents/{document}', [AdminController::class, 'logisticsDocument'])->name('logistics.document');
    Route::get('/identity', [AdminController::class, 'identity'])->name('identity');
    Route::get('/identity/{verification}/document', [AdminController::class, 'identityDocument'])->name('identity.document');
    Route::patch('/identity/{verification}', [AdminController::class, 'reviewIdentity'])->name('identity.review');
    Route::get('/account', [AdminController::class, 'account'])->name('account');
    Route::patch('/account/profile', [AdminController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AdminController::class, 'updatePassword'])->name('account.password');
    Route::get('/users/{user}', [AdminController::class, 'showUser'])->name('users.show');
    Route::get('/activity', [ActivityLogController::class, 'admin'])->name('activity');

    Route::get('/disputes', [AdminDisputeController::class, 'index'])->name('disputes');
    Route::get('/disputes/{dispute}', [AdminDisputeController::class, 'show'])->name('disputes.show');
    Route::get('/parcels/{shipment}/waybill', [WaybillController::class, 'admin'])->middleware('throttle:60,1')->name('parcels.waybill');
    Route::get('/parcels/{shipment}/waybill/print', [WaybillController::class, 'adminPrint'])->middleware('throttle:60,1')->name('parcels.waybill.print');
    Route::patch('/disputes/{dispute}', [AdminDisputeController::class, 'update'])->name('disputes.update');
    Route::post('/disputes/{dispute}/messages', [AdminDisputeController::class, 'reply'])->middleware('throttle:30,1')->name('disputes.reply');

    Route::get('/compliance', [AdminComplianceController::class, 'index'])->name('compliance');
    Route::post('/compliance/products/{product}/warnings', [AdminComplianceController::class, 'warn'])->middleware('throttle:30,1')->name('compliance.warn');
    Route::patch('/compliance/products/{product}/visibility', [AdminComplianceController::class, 'toggle'])->name('compliance.toggle');
    Route::patch('/compliance/products/{product}/review', [AdminComplianceController::class, 'review'])->middleware('throttle:60,1')->name('compliance.review');

    Route::get('/commissions', [AdminFinanceController::class, 'commissions'])->name('commissions');
    Route::post('/commissions/record', [AdminFinanceController::class, 'recordCommissions'])->name('commissions.record');
    Route::post('/commissions/settle', [AdminFinanceController::class, 'settleCommissions'])->name('commissions.settle');
    Route::get('/reports', [AdminFinanceController::class, 'reports'])->name('reports');
    Route::get('/reports/download', [AdminFinanceController::class, 'downloadReport'])->middleware('throttle:30,1')->name('reports.download');
    Route::get('/reports/{report}.pdf', [AdminFinanceController::class, 'reportPdf'])->whereIn('report', ['sales', 'commission'])->name('reports.pdf');

    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
    Route::post('/settings/announcements', [AdminSettingsController::class, 'storeAnnouncement'])->name('announcements.store');
    Route::patch('/settings/announcements/{announcement}', [AdminSettingsController::class, 'updateAnnouncement'])->name('announcements.update');
    Route::delete('/settings/announcements/{announcement}', [AdminSettingsController::class, 'destroyAnnouncement'])->name('announcements.destroy');
    Route::get('/settings/policies/{slug}', [AdminSettingsController::class, 'editPolicy'])->name('policies.edit');
    Route::put('/settings/policies/{slug}', [AdminSettingsController::class, 'updatePolicy'])->name('policies.update');

    Route::get('/chat', [SupportChatController::class, 'admin'])->name('chat');
    Route::post('/chat', [SupportChatController::class, 'startFromAdmin'])->middleware('throttle:30,1')->name('chat.start');
});

// Complaints: the buyer files one about an order; the buyer, seller and courier follow the case.
Route::middleware(['auth', 'active', 'verified', 'role:buyer', 'nocache'])->group(function () {
    Route::get('/buyer/orders/{order}/complaint', [DisputeController::class, 'create'])->name('buyer.disputes.create');
    Route::post('/buyer/orders/{order}/complaint', [DisputeController::class, 'store'])->middleware('throttle:10,1')->name('buyer.disputes.store');
});
Route::middleware(['auth', 'active', 'verified', 'role:buyer,seller,rider,admin', 'nocache'])->prefix('complaints')->name('disputes.')->group(function () {
    Route::get('/', [DisputeController::class, 'index'])->name('index');
    Route::get('/{dispute}', [DisputeController::class, 'show'])->name('show');
    Route::post('/{dispute}/messages', [DisputeController::class, 'reply'])->middleware('throttle:30,1')->name('reply');
    Route::get('/{dispute}/evidence/{index}', [DisputeController::class, 'file'])->whereNumber('index')->name('evidence');
    Route::get('/messages/{message}/attachment', [DisputeController::class, 'attachment'])->name('attachment');
});

// Profile photo: every signed-in account can change it from its account page.
Route::middleware(['auth', 'nocache'])->group(function () {
    Route::post('/account/photo', [ProfilePhotoController::class, 'update'])->middleware('throttle:10,1')->name('account.photo.update');
    Route::delete('/account/photo', [ProfilePhotoController::class, 'destroy'])->name('account.photo.destroy');
});

// Help & support: every account can message the ZAYLO admin team.
Route::middleware(['auth', 'active', 'verified', 'nocache'])->group(function () {
    Route::get('/support', [SupportChatController::class, 'user'])->middleware('role:buyer,seller,rider,logistics')->name('support');
    Route::get('/support/{thread}/messages', [SupportChatController::class, 'messages'])->middleware('throttle:120,1')->name('support.messages');
    Route::post('/support/{thread}', [SupportChatController::class, 'send'])->middleware('throttle:30,1')->name('support.send');
});

// Pending and rejected logistics partners can still reach their application, so this group skips the active check.
Route::middleware(['auth', 'verified', 'role:logistics', 'nocache'])->prefix('logistics')->name('logistics.')->group(function () {
    Route::get('/application', [LogisticsApplicationController::class, 'show'])->name('application');
    Route::post('/application', [LogisticsApplicationController::class, 'update'])->middleware('throttle:10,1')->name('application.update');
});

Route::middleware(['auth', 'active', 'verified', 'role:logistics', 'nocache'])->prefix('logistics')->name('logistics.')->group(function () {
    Route::get('/dashboard', [LogisticsController::class, 'dashboard'])->name('dashboard');
    Route::get('/riders', [LogisticsController::class, 'riders'])->name('riders');
    Route::get('/riders/{rider}/documents/{document}', [LogisticsController::class, 'riderDocument'])->name('riders.document');
    Route::patch('/riders/{rider}', [LogisticsController::class, 'updateRider'])->middleware('throttle:60,1')->name('riders.update');
    Route::patch('/riders/{rider}/availability', [LogisticsController::class, 'riderAvailability'])->name('riders.availability');

    // Sorting center: the logistics partner moves parcels from seller pickup to delivery.
    Route::get('/pickups', [LogisticsOperationsController::class, 'pickups'])->name('pickups');
    Route::get('/incoming', [LogisticsOperationsController::class, 'incoming'])->name('incoming');
    Route::get('/scan', [LogisticsOperationsController::class, 'scan'])->name('scan');
    Route::get('/assignments', [LogisticsOperationsController::class, 'assignments'])->name('assignments');
    Route::get('/deliveries', [LogisticsOperationsController::class, 'deliveries'])->name('deliveries');
    Route::get('/sorting', [LogisticsOperationsController::class, 'sorting'])->name('sorting');
    Route::get('/monitoring', [LogisticsOperationsController::class, 'monitoring'])->name('monitoring');
    Route::get('/reports', [LogisticsOperationsController::class, 'reports'])->name('reports');
    Route::get('/reports/export', [LogisticsOperationsController::class, 'exportReport'])->name('reports.export');
    Route::get('/activity', [ActivityLogController::class, 'logistics'])->name('activity');
    Route::get('/parcels/{shipment}/waybill', [WaybillController::class, 'logistics'])->middleware('throttle:120,1')->name('parcels.waybill');
    Route::get('/parcels/{shipment}/waybill/print', [WaybillController::class, 'logisticsPrint'])->middleware('throttle:120,1')->name('parcels.waybill.print');
    Route::patch('/parcels/{shipment}/pickup', [LogisticsOperationsController::class, 'pickup'])->middleware('throttle:120,1')->name('parcels.pickup');
    Route::patch('/parcels/{shipment}/sort', [LogisticsOperationsController::class, 'sort'])->middleware('throttle:120,1')->name('parcels.sort');
    Route::patch('/parcels/{shipment}/status', [LogisticsOperationsController::class, 'advance'])->middleware('throttle:120,1')->name('parcels.advance');
    Route::patch('/returns/{dispute}/scan', [LogisticsOperationsController::class, 'updateReturnScan'])->middleware('throttle:120,1')->name('returns.scan');
    Route::post('/parcels/{shipment}/rider', [LogisticsOperationsController::class, 'assign'])->middleware('throttle:120,1')->name('parcels.assign');
    Route::post('/areas/rider', [LogisticsOperationsController::class, 'assignArea'])->middleware('throttle:60,1')->name('areas.assign');
    Route::get('/messages', [LogisticsChatController::class, 'logistics'])->name('messages');
    Route::post('/messages', [LogisticsChatController::class, 'startFromLogistics'])->middleware('throttle:20,1')->name('messages.start');
    Route::get('/account', [LogisticsController::class, 'account'])->name('account');
    Route::patch('/account/company', [LogisticsController::class, 'updateCompany'])->name('account.company');
    Route::patch('/account/profile', [LogisticsController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [LogisticsController::class, 'updatePassword'])->name('account.password');
});

// Logistics chat, shared by the logistics partner, its sellers and its riders (each thread checks who may open it).
Route::middleware(['auth', 'active', 'verified', 'role:logistics,seller,rider', 'nocache'])->prefix('logistics-chat')->name('logistics-chat.')->group(function () {
    Route::get('/{thread}/messages', [LogisticsChatController::class, 'messages'])->middleware('throttle:120,1')->name('messages');
    Route::post('/{thread}', [LogisticsChatController::class, 'send'])->middleware('throttle:30,1')->name('send');
});
