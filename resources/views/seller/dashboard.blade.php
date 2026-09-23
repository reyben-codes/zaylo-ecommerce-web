
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO | Seller Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #faf7f2;
            font-family: 'Inter', sans-serif;
            color: #1e1e1e;
            line-height: 1.4;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input {
            font-family: inherit;
        }

        /* ========================================
           HEADER
        ======================================== */

        .navbar {
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            padding: 0 32px;
            border-top: 2px solid #8cb58a;
            border-bottom: 1px solid #ece4db;
            position: relative;
            z-index: 1000;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-toggle {
            display: none;
            border: none;
            background: transparent;
            color: #1e1e1e;
            font-size: 22px;
            cursor: pointer;
        }

        .logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-image {
            width: 78px;
            height: auto;
            max-height: 55px;
            object-fit: contain;
            display: block;
            transition: 0.2s ease;
        }

        .logo:hover .logo-image {
            transform: scale(1.05);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-left: auto;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            background: #f4f1ec;
            border: 1px solid #e5dfd8;
            border-radius: 30px;
            padding: 4px 14px;
        }

        .search-wrapper:focus-within {
            border-color: #b28b6f;
        }

        .search-icon {
            font-size: 12px;
            color: #4a4037;
        }

        .search-wrapper input {
            width: 90px;
            border: none;
            outline: none;
            background: transparent;
            padding: 6px 0 6px 7px;
            font-size: 11px;
            color: #1e1e1e;
        }

        .search-wrapper input::placeholder {
            color: #a89b8c;
        }

        .icon-group {
            display: flex;
            align-items: center;
            gap: 17px;
        }

        .icon-group a {
            position: relative;
            color: #1e1e1e;
            transition: 0.2s ease;
        }

        .icon-group a:hover {
            color: #a87957;
        }

        .icon-group i {
            font-size: 19px;
        }

        .badge-count {
            position: absolute;
            top: -9px;
            right: -9px;
            min-width: 18px;
            height: 18px;
            padding: 2px 5px;
            background: #b28b6f;
            color: #ffffff;
            border-radius: 50%;
            font-size: 9px;
            text-align: center;
            font-weight: 600;
        }

        .logout-link {
            border-left: 1px solid #d8d0c8;
            padding-left: 16px;
            font-size: 11px;
            font-weight: 500;
            color: #1e1e1e;
        }

        .logout-link:hover {
            color: #a87957;
        }

        /* ========================================
           MOBILE MENU
        ======================================== */

        .mobile-menu {
            display: none;
            background: #ffffff;
            border-bottom: 1px solid #ece4db;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 999;
        }

        .mobile-menu.show {
            display: block;
        }

        .mobile-menu a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 15px;
            color: #6b5f54;
            font-size: 13px;
            border-radius: 5px;
            transition: 0.2s ease;
        }

        .mobile-menu a:hover,
        .mobile-menu a.active {
            background: #f5f0ea;
            color: #1e1e1e;
        }

        .mobile-menu a i {
            width: 18px;
            text-align: center;
        }

        .mobile-menu .mobile-badge {
            margin-left: auto;
            background: #b28b6f;
            color: #ffffff;
            font-size: 9px;
            padding: 3px 7px;
            border-radius: 10px;
        }

        /* ========================================
           DASHBOARD WRAPPER
        ======================================== */

        .dashboard-wrapper {
            display: flex;
            min-height: calc(100vh - 72px);
            background: #faf7f2;
        }

        /* ========================================
           SIDEBAR
        ======================================== */

        .sidebar {
            width: 240px;
            flex-shrink: 0;
            min-height: calc(100vh - 72px);
            background: #ffffff;
            border-right: 1px solid #ece4db;
            padding: 24px 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
        }

        .sidebar-menu li {
            margin-bottom: 2px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: #6b5f54;
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 0.02em;
            border-radius: 4px;
            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: #f5f0ea;
            color: #1e1e1e;
        }

        .sidebar-menu a.active {
            background: #1a1714;
            color: #ffffff;
        }

        .sidebar-menu a i {
            width: 18px;
            font-size: 13px;
            text-align: center;
        }

        .sidebar-menu a .badge {
            margin-left: auto;
            background: #b28b6f;
            color: #ffffff;
            font-size: 9px;
            padding: 3px 7px;
            border-radius: 10px;
        }

        .sidebar-divider {
            height: 1px;
            background: #ece4db;
            margin: 14px 16px;
        }

        /* ========================================
           MAIN CONTENT
        ======================================== */

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 32px 40px;
            background: #faf7f2;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 29px;
            font-weight: 600;
            color: #1a1714;
        }

        .page-header .subtitle {
            font-size: 13px;
            color: #6b5f54;
            margin-top: 5px;
        }

        .page-header .store-name {
            font-size: 13px;
            color: #b28b6f;
            margin-top: 5px;
        }

        /* ========================================
           STATISTICS
        ======================================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #ece4db;
            padding: 20px 24px;
            transition: 0.2s ease;
        }

        .stat-card:hover {
            border-color: #b28b6f;
        }

        .stat-card .stat-icon {
            font-size: 20px;
            color: #b28b6f;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b5f54;
            margin-bottom: 5px;
        }

        .stat-card .number {
            font-size: 29px;
            font-weight: 600;
            color: #1a1714;
        }

        .stat-card .trend {
            display: block;
            margin-top: 5px;
            font-size: 10px;
            color: #6b5f54;
        }

        .stat-card .trend.up {
            color: #2d7d46;
        }

        /* ========================================
           RECENT ORDERS
        ======================================== */

        .recent-orders {
            background: #ffffff;
            border: 1px solid #ece4db;
            padding: 24px 28px;
            margin-bottom: 32px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 17px;
        }

        .section-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 600;
            color: #1a1714;
        }

        .section-header a {
            font-size: 11px;
            color: #6b5f54;
        }

        .section-header a:hover {
            color: #b28b6f;
        }

        .order-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 14px 0;
            border-bottom: 1px solid #f5f0ea;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .order-id {
            font-size: 12px;
            font-weight: 600;
            color: #1a1714;
        }

        .order-customer {
            font-size: 10px;
            color: #6b5f54;
        }

        .order-status {
            padding: 5px 11px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #f5f0ea;
            color: #6b5f54;
        }

        .status-shipping {
            background: #e8dfd6;
            color: #1a1714;
        }

        .status-delivery {
            background: #d4c5b8;
            color: #1a1714;
        }

        .status-delivered {
            background: #d6e4d0;
            color: #2d7d46;
        }

        .order-amount {
            font-size: 12px;
            font-weight: 600;
            color: #1a1714;
        }

        /* ========================================
           QUICK ACTIONS
        ======================================== */

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .quick-action {
            background: #ffffff;
            border: 1px solid #ece4db;
            padding: 25px 20px;
            text-align: center;
            transition: 0.2s ease;
        }

        .quick-action:hover {
            border-color: #b28b6f;
            transform: translateY(-2px);
        }

        .quick-action i {
            display: block;
            font-size: 28px;
            color: #b28b6f;
            margin-bottom: 12px;
        }

        .quick-action span {
            display: block;
            font-size: 13px;
            font-weight: 500;
        }

        .quick-action .action-desc {
            margin-top: 5px;
            font-size: 10px;
            color: #6b5f54;
        }

        /* ========================================
           RESPONSIVE
        ======================================== */

        @media (max-width: 1100px) {
            .main-content {
                padding: 28px 25px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 820px) {
            .navbar {
                height: 72px;
                padding: 0 20px;
            }

            .menu-toggle {
                display: block;
            }

            .logo-image {
                width: 68px;
            }

            .search-wrapper {
                padding: 5px;
                border: none;
                background: transparent;
            }

            .search-wrapper input {
                display: none;
            }

            .nav-actions {
                gap: 14px;
            }

            .logout-link {
                display: none;
            }

            .sidebar {
                display: none;
            }

            .dashboard-wrapper {
                min-height: calc(100vh - 72px);
            }

            .main-content {
                padding: 25px 20px;
            }

            .order-item {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 560px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card .number {
                font-size: 23px;
            }

            .stat-card h3 {
                font-size: 9px;
            }

            .quick-actions {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .quick-action {
                padding: 18px 10px;
            }

            .quick-action i {
                font-size: 23px;
            }

            .quick-action span {
                font-size: 11px;
            }

            .recent-orders {
                padding: 20px 16px;
            }

            .main-content {
                padding: 22px 14px;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .order-item {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
</head>

<body class="seller-workspace">

    <!-- ========================================
         HEADER
    ======================================== -->

    <header class="navbar">

        <!-- MOBILE MENU BUTTON -->
        <div class="header-left">

            <button
                class="menu-toggle"
                id="menu-toggle"
                aria-label="Open Menu"
                aria-expanded="false"
                type="button">

                <i class="fas fa-bars" id="menu-open-icon"></i>
                <i class="fas fa-times" id="menu-close-icon" style="display: none;"></i>

            </button>

        </div>

        <!-- CONNECTED ZAYLO LOGO -->
        <a href="{{ route('seller.dashboard') }}" class="logo">

            <img
                src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}"
                alt="ZAYLO Logo"
                class="logo-image">

        </a>

        <!-- HEADER ACTIONS -->
        <div class="nav-actions">

            <div class="search-wrapper">

                <span class="search-icon">
                    <i class="fas fa-search"></i>
                </span>

                <input
                    type="text"
                    id="header-search"
                    placeholder="Search"
                    aria-label="Search">

            </div>

            <div class="icon-group">

                <a href="#" aria-label="Notifications">
                    <i class="far fa-bell"></i>
                    <span class="badge-count">5</span>
                </a>

                <a href="{{ url('/cart') }}" aria-label="Shopping Cart">
                    <i class="fas fa-shopping-bag"></i>
                    <span class="badge-count">2</span>
                </a>

                <a
                    href="{{ route('seller.account') }}"
                    aria-label="Account">

                    <i class="far fa-user"></i>

                </a>

                <a
                    href="{{ route('login') }}"
                    class="logout-link">

                    Logout

                </a>

            </div>

        </div>

    </header>

    <!-- ========================================
         MOBILE MENU
    ======================================== -->

    <div class="mobile-menu" id="mobile-menu">

        <a
            href="{{ route('seller.dashboard') }}"
            class="{{ request()->routeIs('seller.dashboard') ? 'active' : '' }}">

            <i class="fas fa-home"></i>
            <span>Dashboard</span>

        </a>

        <a
            href="{{ route('seller.orders') }}"
            class="{{ request()->routeIs('seller.orders') ? 'active' : '' }}">

            <i class="fas fa-box"></i>
            <span>Orders</span>
            <span class="mobile-badge">3</span>

        </a>

        <a
            href="{{ route('seller.handover') }}"
            class="{{ request()->routeIs('seller.handover') ? 'active' : '' }}">

            <i class="fas fa-truck"></i>
            <span>Handover</span>

        </a>

        <a
            href="{{ route('seller.inventory') }}"
            class="{{ request()->routeIs('seller.inventory') ? 'active' : '' }}">

            <i class="fas fa-warehouse"></i>
            <span>Inventory</span>

        </a>

        <a
            href="{{ route('seller.products') }}"
            class="{{ request()->routeIs('seller.products') ? 'active' : '' }}">

            <i class="fas fa-shopping-bag"></i>
            <span>Products</span>

        </a>

        <a
            href="{{ route('seller.reports') }}"
            class="{{ request()->routeIs('seller.reports') ? 'active' : '' }}">

            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>

        </a>

        <a
            href="{{ route('seller.chat') }}"
            class="{{ request()->routeIs('seller.chat') ? 'active' : '' }}">

            <i class="fas fa-comment"></i>
            <span>Messages</span>
            <span class="mobile-badge">2</span>

        </a>

        <a
            href="{{ route('seller.account') }}"
            class="{{ request()->routeIs('seller.account') ? 'active' : '' }}">

            <i class="fas fa-user"></i>
            <span>Account</span>

        </a>

        <a href="{{ route('login') }}">

            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>

        </a>

    </div>

    <!-- ========================================
         DASHBOARD
    ======================================== -->

    <div class="dashboard-wrapper">

        <!-- SIDEBAR -->
        @include('partials.seller-sidebar')

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <!-- PAGE HEADER -->
            <div class="page-header">

                <h1>Seller Dashboard</h1>

                <p class="subtitle">
                    Welcome back,
                    <strong id="sellerName">{{ $user->name }}</strong>!
                </p>

                <p class="store-name" id="storeName">
                    <i class="fas fa-location-dot"></i>
                    {{ $storeName }}
                </p>

            </div>

            <!-- STATISTICS -->
            <div class="stats-grid">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>

                    <h3>Total Orders</h3>

                    <div class="number">24</div>

                    <span class="trend up">
                        ↑ 5 this month
                    </span>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fas fa-peso-sign"></i>
                    </div>

                    <h3>Revenue</h3>

                    <div class="number">₱48,250</div>

                    <span class="trend up">
                        ↑ 12% this month
                    </span>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>

                    <h3>Products</h3>

                    <div class="number">48</div>

                    <span class="trend">
                        12 in stock
                    </span>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>

                    <h3>Rating</h3>

                    <div class="number">4.8</div>

                    <span class="trend up">
                        ★ 128 reviews
                    </span>

                </div>

            </div>

            <!-- RECENT ORDERS -->
            <div class="recent-orders">

                <div class="section-header">

                    <h2>Recent Orders</h2>

                    <a href="{{ route('seller.orders') }}">
                        View All →
                    </a>

                </div>

                <div class="order-item">

                    <div class="order-info">

                        <span class="order-id">
                            #ZYL-2024-001
                        </span>

                        <span class="order-customer">
                            Juan Dela Cruz · Dec 15, 2024
                        </span>

                    </div>

                    <span class="order-status status-pending">
                        To Ship
                    </span>

                    <span class="order-amount">
                        ₱4,250.00
                    </span>

                </div>

                <div class="order-item">

                    <div class="order-info">

                        <span class="order-id">
                            #ZYL-2024-002
                        </span>

                        <span class="order-customer">
                            Maria Reyes · Dec 14, 2024
                        </span>

                    </div>

                    <span class="order-status status-shipping">
                        In Transit
                    </span>

                    <span class="order-amount">
                        ₱1,280.00
                    </span>

                </div>

                <div class="order-item">

                    <div class="order-info">

                        <span class="order-id">
                            #ZYL-2024-003
                        </span>

                        <span class="order-customer">
                            Pedro Santos · Dec 12, 2024
                        </span>

                    </div>

                    <span class="order-status status-delivery">
                        Out for Delivery
                    </span>

                    <span class="order-amount">
                        ₱3,750.00
                    </span>

                </div>

                <div class="order-item">

                    <div class="order-info">

                        <span class="order-id">
                            #ZYL-2024-004
                        </span>

                        <span class="order-customer">
                            Ana Lopez · Dec 10, 2024
                        </span>

                    </div>

                    <span class="order-status status-delivered">
                        Delivered
                    </span>

                    <span class="order-amount">
                        ₱890.00
                    </span>

                </div>

            </div>

            <!-- QUICK ACTIONS -->
            <div class="quick-actions">

                <a
                    href="{{ route('seller.orders') }}"
                    class="quick-action">

                    <i class="fas fa-box"></i>

                    <span>Manage Orders</span>

                    <span class="action-desc">
                        View and process orders
                    </span>

                </a>

                <a
                    href="{{ route('seller.products') }}"
                    class="quick-action">

                    <i class="fas fa-plus-circle"></i>

                    <span>Add Product</span>

                    <span class="action-desc">
                        List new items
                    </span>

                </a>

                <a
                    href="{{ route('seller.inventory') }}"
                    class="quick-action">

                    <i class="fas fa-warehouse"></i>

                    <span>Inventory</span>

                    <span class="action-desc">
                        Manage stock levels
                    </span>

                </a>

            </div>

        </main>

    </div>

    <!-- ========================================
         JAVASCRIPT
    ======================================== -->

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // MOBILE MENU OPEN / CLOSE
            const menuToggle = document.getElementById('menu-toggle');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuOpenIcon = document.getElementById('menu-open-icon');
            const menuCloseIcon = document.getElementById('menu-close-icon');

            if (menuToggle && mobileMenu) {

                menuToggle.addEventListener('click', function () {

                    const isOpen = mobileMenu.classList.toggle('show');

                    menuToggle.setAttribute(
                        'aria-expanded',
                        String(isOpen)
                    );

                    if (isOpen) {

                        menuOpenIcon.style.display = 'none';
                        menuCloseIcon.style.display = 'inline-block';

                    } else {

                        menuOpenIcon.style.display = 'inline-block';
                        menuCloseIcon.style.display = 'none';

                    }

                });

            }

        });
    </script>

</body>

</html>
