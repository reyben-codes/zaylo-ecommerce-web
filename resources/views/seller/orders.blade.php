
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZAYLO | Orders</title>

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

        :root {
            --cream: #faf7f2;
            --white: #ffffff;
            --brown: #b28b6f;
            --dark: #1a1714;
            --text: #38312b;
            --muted: #6b5f54;
            --border: #ece4db;
            --soft-brown: #f5f0ea;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--text);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input {
            font-family: inherit;
        }

        /* ================================
           HEADER
        ================================= */

        .navbar {
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--white);
            padding: 0 32px;
            border-top: 2px solid #8cb58a;
            border-bottom: 1px solid var(--border);
            position: relative;
            z-index: 1000;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .menu-toggle {
            display: none;
            border: none;
            background: transparent;
            color: var(--dark);
            font-size: 22px;
            cursor: pointer;
            padding: 5px;
        }

        .logo {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
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
            color: var(--dark);
            transition: 0.2s ease;
        }

        .icon-group a:hover {
            color: var(--brown);
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
            background: var(--brown);
            color: white;
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
            color: var(--dark);
        }

        .logout-link:hover {
            color: var(--brown);
        }

        /* ================================
           LAYOUT
        ================================= */

        .dashboard-wrapper {
            display: flex;
            min-height: calc(100vh - 72px);
            background: var(--cream);
        }

        /* ================================
           SIDEBAR
        ================================= */

        .sidebar {
            width: 240px;
            min-width: 240px;
            min-height: calc(100vh - 72px);
            background: var(--white);
            border-right: 1px solid var(--border);
            padding: 24px 0;
            transition: transform 0.3s ease;
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
            color: var(--muted);
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 0.02em;
            transition: 0.2s ease;
            border-radius: 4px;
        }

        .sidebar-menu a:hover {
            background: var(--soft-brown);
            color: var(--dark);
        }

        .sidebar-menu a.active {
            background: var(--dark);
            color: var(--white);
        }

        .sidebar-menu a i {
            width: 18px;
            font-size: 13px;
            text-align: center;
        }

        .sidebar-menu a .badge {
            margin-left: auto;
            background: var(--brown);
            color: var(--white);
            font-size: 9px;
            padding: 3px 7px;
            border-radius: 10px;
        }

        .sidebar-divider {
            height: 1px;
            background: var(--border);
            margin: 14px 16px;
            border: none;
        }

        /* ================================
           SIDEBAR OVERLAY
        ================================= */

        .sidebar-overlay {
            display: none;
        }

        /* ================================
           MAIN CONTENT
        ================================= */

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 32px 40px;
            background: var(--cream);
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 29px;
            font-weight: 600;
            color: var(--dark);
        }

        .page-header .subtitle {
            font-size: 13px;
            color: var(--muted);
            margin-top: 5px;
        }

        /* ================================
           STATISTICS
        ================================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            padding: 20px 24px;
            transition: 0.2s ease;
        }

        .stat-card:hover {
            border-color: var(--brown);
        }

        .stat-icon {
            font-size: 20px;
            color: var(--brown);
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .stat-card .number {
            font-size: 29px;
            font-weight: 600;
            color: var(--dark);
        }

        /* ================================
           ORDERS SECTION
        ================================= */

        .orders-section {
            background: var(--white);
            border: 1px solid var(--border);
            padding: 24px 28px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 22px;
        }

        .section-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 21px;
            font-weight: 600;
            color: var(--dark);
        }

        .section-header p {
            color: var(--muted);
            font-size: 12px;
            margin-top: 5px;
        }

        /* ================================
           FILTERS
        ================================= */

        .filter-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 22px;
        }

        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .filter-button {
            border: 1px solid #e5dcd2;
            background: transparent;
            border-radius: 25px;
            padding: 9px 16px;
            color: #4b4037;
            font-size: 11px;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .filter-button:hover {
            background: var(--soft-brown);
        }

        .filter-button.active {
            background: var(--dark);
            border-color: var(--dark);
            color: var(--white);
        }

        .orders-search {
            width: 230px;
            min-width: 180px;
            height: 38px;
            display: flex;
            align-items: center;
            gap: 9px;
            border: 1px solid var(--border);
            border-radius: 25px;
            padding: 0 14px;
            background: var(--white);
        }

        .orders-search i {
            font-size: 11px;
            color: var(--muted);
        }

        .orders-search input {
            width: 100%;
            border: none;
            outline: none;
            font-size: 11px;
        }

        /* ================================
           TABLE
        ================================= */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
        }

        .orders-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .orders-table thead {
            background: #faf8f5;
        }

        .orders-table th {
            text-align: left;
            padding: 15px 17px;
            border-bottom: 1px solid var(--border);
            color: #806b58;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .orders-table td {
            padding: 17px;
            border-bottom: 1px solid #f0eae4;
            color: #39332e;
            font-size: 11px;
            white-space: nowrap;
        }

        .orders-table tbody tr:last-child td {
            border-bottom: none;
        }

        .orders-table tbody tr:hover {
            background: #fdfbf9;
        }

        .order-number {
            font-weight: 600;
            color: var(--dark);
        }

        .total-price {
            font-weight: 600;
            color: var(--dark);
        }

        /* ================================
           STATUS
        ================================= */

        .status {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status.to-ship {
            background: #fff0d5;
            color: #966019;
        }

        .status.in-transit {
            background: #eee8e2;
            color: #6f6258;
        }

        .status.out-delivery {
            background: #f0e3d8;
            color: #966b4e;
        }

        .status.delivered {
            background: #d6e4d0;
            color: #2d7d46;
        }

        .status.cancelled {
            background: #fde1df;
            color: #c34540;
        }

        .view-details {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: 1px solid #d5c5b6;
            border-radius: 20px;
            padding: 8px 12px;
            color: #493d33;
            font-size: 10px;
            transition: 0.2s ease;
        }

        .view-details:hover {
            background: var(--soft-brown);
            border-color: var(--brown);
        }

        /* ================================
           RESPONSIVE
        ================================= */

        @media (max-width: 1150px) {
            .main-content {
                padding: 28px 25px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .orders-search {
                width: 100%;
                max-width: 320px;
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
                position: fixed;
                top: 72px;
                left: 0;
                bottom: 0;
                width: 260px;
                min-width: 260px;
                min-height: auto;
                z-index: 1100;
                overflow-y: auto;
                transform: translateX(-100%);
                box-shadow: 5px 0 20px rgba(0, 0, 0, 0.08);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                position: fixed;
                top: 72px;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(26, 23, 20, 0.35);
                z-index: 1050;
            }

            .sidebar-overlay.show {
                display: block;
            }

            .dashboard-wrapper {
                display: block;
                min-height: calc(100vh - 72px);
            }

            .main-content {
                width: 100%;
                padding: 25px 20px;
            }

            .menu-toggle.active {
                color: var(--brown);
            }
        }

        @media (max-width: 560px) {
            .main-content {
                padding: 22px 14px;
            }

            .page-header h1 {
                font-size: 26px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 16px 13px;
            }

            .stat-card .number {
                font-size: 23px;
            }

            .stat-card h3 {
                font-size: 9px;
            }

            .orders-section {
                padding: 20px 15px;
            }

            .section-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .filter-buttons {
                gap: 6px;
            }

            .filter-button {
                padding: 8px 12px;
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

        <div class="header-left">

            <button
                class="menu-toggle"
                id="menu-toggle"
                aria-label="Open sidebar"
                aria-expanded="false">

                <i class="fas fa-bars" id="menu-open-icon"></i>
                <i class="fas fa-times" id="menu-close-icon"
                    style="display: none;"></i>

            </button>

        </div>

        <!-- CENTERED ZAYLO LOGO -->

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
                    placeholder="Search">

            </div>

            <div class="icon-group">

                <a href="#" aria-label="Notifications">

                    <i class="far fa-bell"></i>
                    <span class="badge-count">5</span>

                </a>

                <a href="#" aria-label="Shopping cart">

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
         SIDEBAR OVERLAY
    ======================================== -->

    <div
        class="sidebar-overlay"
        id="sidebar-overlay">
    </div>

    <!-- ========================================
         MAIN LAYOUT
    ======================================== -->

    <div class="dashboard-wrapper">

        <!-- SIDEBAR -->

        @include('partials.seller-sidebar')

        <!-- ========================================
             ORDERS CONTENT
        ======================================== -->

        <main class="main-content">

            <div class="page-header">

                <h1>Orders</h1>

                <p class="subtitle">
                    Manage and track your customer orders.
                </p>

            </div>

            <!-- STATISTICS -->

            <section class="stats-grid">

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>

                    <h3>Total Orders</h3>

                    <div class="number">
                        120
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>

                    <h3>Pending Orders</h3>

                    <div class="number">
                        24
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fas fa-truck"></i>
                    </div>

                    <h3>Shipped Orders</h3>

                    <div class="number">
                        36
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>

                    <h3>Completed Orders</h3>

                    <div class="number">
                        60
                    </div>

                </div>

            </section>

            <!-- ORDERS SECTION -->

            <section class="orders-section">

                <div class="section-header">

                    <div>

                        <h2>All Orders</h2>

                        <p>
                            Review and manage your recent orders.
                        </p>

                    </div>

                </div>

                <!-- FILTERS -->

                <div class="filter-row">

                    <div class="filter-buttons">

                        <button class="filter-button active">
                            All
                        </button>

                        <button class="filter-button">
                            To Ship
                        </button>

                        <button class="filter-button">
                            In Transit
                        </button>

                        <button class="filter-button">
                            Delivered
                        </button>

                        <button class="filter-button">
                            Cancelled
                        </button>

                    </div>

                    <div class="orders-search">

                        <i class="fas fa-search"></i>

                        <input
                            type="text"
                            placeholder="Search orders...">

                    </div>

                </div>

                <!-- ORDERS TABLE -->

                <div class="table-wrapper">

                    <table class="orders-table">

                        <thead>

                            <tr>

                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Total</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td class="order-number">
                                    #ZYL-2024-001
                                </td>

                                <td>
                                    Juan Dela Cruz
                                </td>

                                <td>
                                    Wool Blend Blazer + 1 more
                                </td>

                                <td class="total-price">
                                    ₱4,250.00
                                </td>

                                <td>
                                    Dec 15, 2024
                                </td>

                                <td>

                                    <span class="status to-ship">
                                        To Ship
                                    </span>

                                </td>

                                <td>

                                    <a href="#" class="view-details">

                                        <i class="far fa-eye"></i>
                                        View Details

                                    </a>

                                </td>

                            </tr>

                            <tr>

                                <td class="order-number">
                                    #ZYL-2024-002
                                </td>

                                <td>
                                    Maria Reyes
                                </td>

                                <td>
                                    Leather Tote Bag
                                </td>

                                <td class="total-price">
                                    ₱1,280.00
                                </td>

                                <td>
                                    Dec 14, 2024
                                </td>

                                <td>

                                    <span class="status in-transit">
                                        In Transit
                                    </span>

                                </td>

                                <td>

                                    <a href="#" class="view-details">

                                        <i class="far fa-eye"></i>
                                        View Details

                                    </a>

                                </td>

                            </tr>

                            <tr>

                                <td class="order-number">
                                    #ZYL-2024-003
                                </td>

                                <td>
                                    Pedro Santos
                                </td>

                                <td>
                                    Sneakers + 1 more
                                </td>

                                <td class="total-price">
                                    ₱3,750.00
                                </td>

                                <td>
                                    Dec 12, 2024
                                </td>

                                <td>

                                    <span class="status out-delivery">
                                        Out for Delivery
                                    </span>

                                </td>

                                <td>

                                    <a href="#" class="view-details">

                                        <i class="far fa-eye"></i>
                                        View Details

                                    </a>

                                </td>

                            </tr>

                            <tr>

                                <td class="order-number">
                                    #ZYL-2024-004
                                </td>

                                <td>
                                    Ana Lopez
                                </td>

                                <td>
                                    Cashmere Sweater
                                </td>

                                <td class="total-price">
                                    ₱890.00
                                </td>

                                <td>
                                    Dec 10, 2024
                                </td>

                                <td>

                                    <span class="status delivered">
                                        Delivered
                                    </span>

                                </td>

                                <td>

                                    <a href="#" class="view-details">

                                        <i class="far fa-eye"></i>
                                        View Details

                                    </a>

                                </td>

                            </tr>

                            <tr>

                                <td class="order-number">
                                    #ZYL-2024-005
                                </td>

                                <td>
                                    Carlos Garcia
                                </td>

                                <td>
                                    Crossbody Bag + 1 more
                                </td>

                                <td class="total-price">
                                    ₱7,000.00
                                </td>

                                <td>
                                    Dec 8, 2024
                                </td>

                                <td>

                                    <span class="status cancelled">
                                        Cancelled
                                    </span>

                                </td>

                                <td>

                                    <a href="#" class="view-details">

                                        <i class="far fa-eye"></i>
                                        View Details

                                    </a>

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

    <!-- ========================================
         SIDEBAR OPEN/CLOSE SCRIPT
    ======================================== -->

    <script>

        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebar-overlay');

        const menuOpenIcon = document.getElementById('menu-open-icon');
        const menuCloseIcon = document.getElementById('menu-close-icon');

        function openSidebar() {

            sidebar.classList.add('open');
            sidebarOverlay.classList.add('show');

            menuToggle.classList.add('active');
            menuToggle.setAttribute('aria-expanded', 'true');

            menuOpenIcon.style.display = 'none';
            menuCloseIcon.style.display = 'inline-block';

        }

        function closeSidebar() {

            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');

            menuToggle.classList.remove('active');
            menuToggle.setAttribute('aria-expanded', 'false');

            menuOpenIcon.style.display = 'inline-block';
            menuCloseIcon.style.display = 'none';

        }

        menuToggle.addEventListener('click', function () {

            if (sidebar.classList.contains('open')) {

                closeSidebar();

            } else {

                openSidebar();

            }

        });

        sidebarOverlay.addEventListener('click', function () {

            closeSidebar();

        });

        // Close sidebar after selecting a mobile navigation link

        const sidebarLinks = sidebar.querySelectorAll('a');

        sidebarLinks.forEach(function (link) {

            link.addEventListener('click', function () {

                if (window.innerWidth <= 820) {

                    closeSidebar();

                }

            });

        });

        // Reset sidebar when resizing to desktop

        window.addEventListener('resize', function () {

            if (window.innerWidth > 820) {

                closeSidebar();

            }

        });

        // Filter buttons

        const filterButtons = document.querySelectorAll('.filter-button');

        filterButtons.forEach(function (button) {

            button.addEventListener('click', function () {

                filterButtons.forEach(function (item) {

                    item.classList.remove('active');

                });

                button.classList.add('active');

            });

        });

    </script>

</body>

</html>
