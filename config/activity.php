<?php

/*
 * Activity logs. Each named route listed under "routes" is recorded after it succeeds
 * (no error status, no validation errors or "error" flash), see App\Http\Middleware\RecordActivity.
 * Entry: [category, description, request fields worth keeping]. Never list password or document fields.
 * The controller's "status" flash message is stored with the entry as its result.
 */
return [
    // Entries older than this are removed by the scheduled model:prune.
    'retention_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 365),

    'categories' => [
        'auth' => 'Sign-in',
        'account' => 'Account',
        'orders' => 'Orders',
        'products' => 'Products',
        'disputes' => 'Complaints',
        'deliveries' => 'Deliveries',
        'logistics' => 'Sorting center',
        'riders' => 'Riders',
        'users' => 'User management',
        'marketplace' => 'Marketplace',
        'finance' => 'Finance',
        'settings' => 'Platform settings',
        'documents' => 'Documents & exports',
    ],

    'routes' => [
        // Sign-in and sign-out (recorded only when the account ends up signed in, or was signed in for logout).
        'login' => ['auth', 'Signed in'],
        'seller.login.submit' => ['auth', 'Signed in to Seller Center'],
        'logistics.login.submit' => ['auth', 'Signed in to Logistics Center'],
        'admin.login.submit' => ['auth', 'Signed in to Admin Center'],
        'google.callback' => ['auth', 'Signed in with Google'],
        'verification.verify' => ['auth', 'Verified email address'],
        'logout' => ['auth', 'Signed out'],

        // Own account (every role).
        'buyer.account.profile' => ['account', 'Updated profile'],
        'seller.account.profile' => ['account', 'Updated profile'],
        'logistics.account.profile' => ['account', 'Updated profile'],
        'admin.account.profile' => ['account', 'Updated profile'],
        'buyer.account.password' => ['account', 'Changed password'],
        'seller.account.password' => ['account', 'Changed password'],
        'logistics.account.password' => ['account', 'Changed password'],
        'admin.account.password' => ['account', 'Changed password'],
        'seller.account.store' => ['account', 'Updated store details'],
        'logistics.account.company' => ['account', 'Updated company details'],
        'seller.application.update' => ['account', 'Submitted seller application'],
        'logistics.application.update' => ['account', 'Submitted logistics application'],
        'buyer.identity.store' => ['account', 'Submitted ID for verification'],
        'account.photo.update' => ['account', 'Changed profile photo'],
        'account.photo.destroy' => ['account', 'Removed profile photo'],
        'addresses.store' => ['account', 'Added a saved address'],
        'addresses.update' => ['account', 'Edited a saved address'],
        'addresses.default' => ['account', 'Changed default address'],
        'addresses.destroy' => ['account', 'Deleted a saved address'],

        // Buyer orders and complaints.
        'buyer.checkout' => ['orders', 'Placed an order'],
        'buyer.orders.cancel' => ['orders', 'Cancelled an order'],
        'buyer.reviews.store' => ['orders', 'Reviewed a product'],
        'buyer.disputes.store' => ['disputes', 'Filed a complaint'],
        'disputes.reply' => ['disputes', 'Replied to a complaint'],

        // Seller Center.
        'seller.products.store' => ['products', 'Added a product'],
        'seller.products.update' => ['products', 'Edited a product'],
        'seller.products.stock' => ['products', 'Updated stock', ['stock']],
        'seller.products.sale' => ['products', 'Updated a product sale'],
        'seller.products.archive' => ['products', 'Archived a product'],
        'seller.products.restore' => ['products', 'Restored a product'],
        'seller.products.destroy' => ['products', 'Deleted a product'],
        'seller.orders.status' => ['orders', 'Updated order status', ['status']],
        'seller.warnings.acknowledge' => ['products', 'Acknowledged a compliance warning'],
        'seller.reports.pdf' => ['documents', 'Downloaded sales report'],

        // Riders.
        'courier.deliveries.claim' => ['deliveries', 'Claimed a delivery'],
        'courier.deliveries.update' => ['deliveries', 'Updated a delivery', ['status']],

        // Logistics Center.
        'logistics.riders.update' => ['riders', 'Reviewed a rider', ['action', 'reason']],
        'logistics.riders.availability' => ['riders', 'Changed rider availability', ['available']],
        'logistics.riders.document' => ['documents', 'Viewed a rider document'],
        'logistics.parcels.pickup' => ['logistics', 'Handled a pickup request', ['decision', 'pickup_date', 'reason']],
        'logistics.parcels.sort' => ['logistics', 'Sorted a parcel', ['area']],
        'logistics.parcels.advance' => ['logistics', 'Updated parcel status', ['status']],
        'logistics.parcels.assign' => ['logistics', 'Assigned a rider to a parcel'],
        'logistics.areas.assign' => ['logistics', 'Assigned a rider to an area', ['area']],
        'logistics.reports.export' => ['documents', 'Exported logistics report'],

        // Admin Center.
        'admin.users.status' => ['users', 'Changed account status', ['status', 'reason']],
        'admin.identity.review' => ['users', 'Reviewed a buyer ID', ['decision', 'reason']],
        'admin.identity.document' => ['documents', 'Viewed a buyer ID document'],
        'admin.seller-applications.document' => ['documents', 'Viewed a seller application document'],
        'admin.logistics.document' => ['documents', 'Viewed a logistics application document'],
        'admin.disputes.update' => ['disputes', 'Updated a complaint', ['status']],
        'admin.disputes.reply' => ['disputes', 'Replied to a complaint'],
        'admin.compliance.warn' => ['marketplace', 'Warned a seller', ['reason', 'action']],
        'admin.compliance.toggle' => ['marketplace', 'Changed product visibility'],
        'admin.compliance.review' => ['marketplace', 'Reviewed a product listing', ['decision', 'reason']],
        'admin.commissions.record' => ['finance', 'Recorded commissions'],
        'admin.commissions.settle' => ['finance', 'Settled commissions'],
        'admin.reports.pdf' => ['documents', 'Downloaded a report'],
        'admin.announcements.store' => ['settings', 'Created an announcement'],
        'admin.announcements.update' => ['settings', 'Updated an announcement'],
        'admin.announcements.destroy' => ['settings', 'Deleted an announcement'],
        'admin.policies.update' => ['settings', 'Updated a policy page'],

        // Mobile app (routes/api.php). Sign-in and sign-out are recorded by the mobile controllers themselves.
        'mobile.checkout' => ['orders', 'Placed an order in the app'],
        'mobile.orders.cancel' => ['orders', 'Cancelled an order in the app'],
        'mobile.account.profile' => ['account', 'Updated profile in the app'],
        'mobile.account.password' => ['account', 'Changed password in the app'],
        'mobile.account.photo.update' => ['account', 'Changed profile photo in the app'],
        'mobile.account.photo.destroy' => ['account', 'Removed profile photo in the app'],
        'mobile.addresses.store' => ['account', 'Added a saved address in the app'],
        'mobile.addresses.update' => ['account', 'Edited a saved address in the app'],
        'mobile.addresses.default' => ['account', 'Changed default address in the app'],
        'mobile.addresses.destroy' => ['account', 'Deleted a saved address in the app'],
        'mobile.account.identity.submit' => ['account', 'Submitted ID for verification in the app'],
        'mobile.order-items.review' => ['orders', 'Reviewed a product in the app'],
        'mobile.orders.complaint' => ['disputes', 'Filed a complaint in the app'],
        'mobile.complaints.reply' => ['disputes', 'Replied to a complaint in the app'],
        'mobile.rider.complaints.reply' => ['disputes', 'Replied to a complaint in the app'],
        'mobile.rider.deliveries.update' => ['deliveries', 'Updated a delivery in the app', ['status']],
        'mobile.rider.deliveries.scan' => ['deliveries', 'Scanned a parcel in the app', ['code']],
        'mobile.rider.account.phone' => ['account', 'Changed mobile number in the app'],
        'mobile.rider.account.gender' => ['account', 'Updated gender in the app'],
        'mobile.rider.account.address' => ['account', 'Changed home address in the app'],
        'mobile.rider.account.password' => ['account', 'Changed password in the app'],
    ],
];
