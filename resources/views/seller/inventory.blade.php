
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO | Inventory</title>

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
        input,
        select {
            font-family: inherit;
        }

        /* ================= HEADER ================= */

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

        /* ================= MOBILE MENU ================= */

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

        /* ================= LAYOUT ================= */

        .dashboard-wrapper {
            display: flex;
            min-height: calc(100vh - 72px);
            background: #faf7f2;
        }

        /* ================= SIDEBAR ================= */

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
            margin: 14px 0;
        }

        /* ================= MAIN CONTENT ================= */

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 32px 40px;
            background: #faf7f2;
        }

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 29px;
            font-weight: 600;
            color: #1a1714;
        }

        .subtitle {
            font-size: 13px;
            color: #6b5f54;
            margin-top: 5px;
        }

        .add-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            background: #1a1714;
            color: #ffffff;
            border: none;
            font-size: 11px;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .add-button:hover {
            background: #b28b6f;
        }

        /* ================= SUMMARY CARDS ================= */

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .summary-card {
            background: #ffffff;
            border: 1px solid #ece4db;
            padding: 20px 24px;
            transition: 0.2s ease;
        }

        .summary-card:hover {
            border-color: #b28b6f;
        }

        .summary-icon {
            color: #b28b6f;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .summary-card h3 {
            font-size: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b5f54;
            margin-bottom: 5px;
        }

        .summary-number {
            font-size: 27px;
            font-weight: 600;
            color: #1a1714;
        }

        .summary-note {
            display: block;
            margin-top: 5px;
            font-size: 10px;
            color: #6b5f54;
        }

        .low-stock {
            color: #a87957;
        }

        .out-stock {
            color: #a33b3b;
        }

        /* ================= INVENTORY PANEL ================= */

        .inventory-panel {
            background: #ffffff;
            border: 1px solid #ece4db;
            padding: 24px 28px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 600;
            color: #1a1714;
        }

        .filter-area {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }

        .filter-area input,
        .filter-area select {
            min-height: 38px;
            padding: 8px 12px;
            background: #faf7f2;
            border: 1px solid #e5dfd8;
            outline: none;
            color: #1e1e1e;
            font-size: 11px;
        }

        .filter-area input {
            flex: 1;
            min-width: 180px;
        }

        .filter-area select {
            min-width: 150px;
        }

        .filter-area input:focus,
        .filter-area select:focus {
            border-color: #b28b6f;
        }

        /* ================= TABLE ================= */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .inventory-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        .inventory-table th {
            background: #f5f0ea;
            color: #6b5f54;
            font-size: 10px;
            font-weight: 600;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 13px 12px;
        }

        .inventory-table td {
            border-bottom: 1px solid #f0eae3;
            padding: 15px 12px;
            font-size: 11px;
            color: #4a4037;
            vertical-align: middle;
        }

        .inventory-table tr:hover {
            background: #fdfbf8;
        }

        .product-name {
            font-weight: 600;
            color: #1a1714;
        }

        .product-category {
            font-size: 10px;
            color: #8d7d70;
            margin-top: 3px;
        }

        .stock-number {
            font-weight: 600;
            color: #1a1714;
        }

        .stock-number.low {
            color: #a87957;
        }

        .stock-number.empty {
            color: #a33b3b;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d6e4d0;
            color: #2d7d46;
        }

        .status-low {
            background: #f5e6d7;
            color: #a87957;
        }

        .status-out {
            background: #f4dede;
            color: #a33b3b;
        }

        .action-buttons {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .action-button {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5dfd8;
            background: #ffffff;
            color: #6b5f54;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .action-button:hover {
            background: #1a1714;
            color: #ffffff;
            border-color: #1a1714;
        }

        .action-button.delete:hover {
            background: #a33b3b;
            border-color: #a33b3b;
        }

        .empty-message {
            display: none;
            text-align: center;
            padding: 35px;
            color: #8d7d70;
            font-size: 12px;
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1100px) {
            .main-content {
                padding: 28px 25px;
            }

            .summary-grid {
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

            .page-header {
                align-items: flex-start;
            }

            .inventory-panel {
                padding: 22px 18px;
            }
        }

        @media (max-width: 560px) {
            .main-content {
                padding: 22px 14px;
            }

            .page-header {
                flex-direction: column;
                margin-bottom: 25px;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .add-button {
                width: 100%;
                justify-content: center;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .summary-card {
                padding: 15px;
            }

            .summary-number {
                font-size: 23px;
            }

            .summary-card h3 {
                font-size: 9px;
            }

            .inventory-panel {
                padding: 20px 14px;
            }

            .section-header {
                align-items: flex-start;
            }

            .section-header h2 {
                font-size: 18px;
            }

            .filter-area {
                flex-direction: column;
            }

            .filter-area input,
            .filter-area select {
                width: 100%;
                min-width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
</head>

<body class="seller-workspace">

    <!-- ================= HEADER ================= -->

    <header class="navbar">

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

        <a href="{{ route('seller.dashboard') }}" class="logo">

            <img
                src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}"
                alt="ZAYLO Logo"
                class="logo-image">

        </a>

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

    <!-- ================= MOBILE MENU ================= -->

    <div class="mobile-menu" id="mobile-menu">

        <a href="{{ route('seller.dashboard') }}">

            <i class="fas fa-home"></i>
            Dashboard

        </a>

        <a href="{{ route('seller.orders') }}">

            <i class="fas fa-box"></i>
            Orders

        </a>

        <a href="{{ route('seller.handover') }}">

            <i class="fas fa-truck"></i>
            Handover

        </a>

        <a
            href="{{ route('seller.inventory') }}"
            class="active">

            <i class="fas fa-warehouse"></i>
            Inventory

        </a>

        <a href="{{ route('seller.products') }}">

            <i class="fas fa-shopping-bag"></i>
            Products

        </a>

        <a href="{{ route('seller.reports') }}">

            <i class="fas fa-chart-bar"></i>
            Reports

        </a>

        <a href="{{ route('seller.chat') }}">

            <i class="fas fa-comment"></i>
            Messages

        </a>

        <a href="{{ route('seller.account') }}">

            <i class="fas fa-user"></i>
            Account

        </a>

        <a href="{{ route('login') }}">

            <i class="fas fa-sign-out-alt"></i>
            Logout

        </a>

    </div>

    <!-- ================= PAGE LAYOUT ================= -->

    <div class="dashboard-wrapper">

        <!-- ================= SIDEBAR ================= -->

        @include('partials.seller-sidebar')

        <!-- ================= MAIN CONTENT ================= -->

        <main class="main-content">

            <div class="page-header">

                <div>

                    <h1>Inventory</h1>

                    <p class="subtitle">
                        Manage your products and monitor stock levels.
                    </p>

                </div>

                <a
                    href="{{ route('seller.products') }}"
                    class="add-button">

                    <i class="fas fa-plus"></i>
                    Add Product

                </a>

            </div>

            <!-- ================= SUMMARY ================= -->

            <div class="summary-grid">

                <div class="summary-card">

                    <div class="summary-icon">
                        <i class="fas fa-boxes"></i>
                    </div>

                    <h3>Total Products</h3>

                    <div class="summary-number">48</div>

                    <span class="summary-note">
                        Products listed
                    </span>

                </div>

                <div class="summary-card">

                    <div class="summary-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>

                    <h3>In Stock</h3>

                    <div class="summary-number">35</div>

                    <span class="summary-note">
                        Available products
                    </span>

                </div>

                <div class="summary-card">

                    <div class="summary-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>

                    <h3>Low Stock</h3>

                    <div class="summary-number low-stock">8</div>

                    <span class="summary-note low-stock">
                        Need restocking
                    </span>

                </div>

                <div class="summary-card">

                    <div class="summary-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>

                    <h3>Out of Stock</h3>

                    <div class="summary-number out-stock">5</div>

                    <span class="summary-note out-stock">
                        Currently unavailable
                    </span>

                </div>

            </div>

            <!-- ================= INVENTORY ================= -->

            <section class="inventory-panel">

                <div class="section-header">

                    <h2>Product Inventory</h2>

                </div>

                <div class="filter-area">

                    <input
                        type="text"
                        id="inventory-search"
                        placeholder="Search product..."
                        aria-label="Search products">

                    <select
                        id="category-filter"
                        aria-label="Filter category">

                        <option value="all">All Categories</option>
                        <option value="Fashion">Fashion</option>
                        <option value="Beauty">Beauty</option>
                        <option value="Home">Home</option>
                        <option value="Electronics">Electronics</option>

                    </select>

                    <select
                        id="status-filter"
                        aria-label="Filter status">

                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="low">Low Stock</option>
                        <option value="out">Out of Stock</option>

                    </select>

                </div>

                <div class="table-wrapper">

                    <table class="inventory-table">

                        <thead>

                            <tr>

                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>

                            </tr>

                        </thead>

                        <tbody id="inventory-body">

                            @php

                                $products = [

                                    [
                                        'name' => 'Classic Oversized Shirt',
                                        'category' => 'Fashion',
                                        'price' => '₱450.00',
                                        'stock' => 35,
                                        'status' => 'active'
                                    ],

                                    [
                                        'name' => 'Premium Tote Bag',
                                        'category' => 'Fashion',
                                        'price' => '₱650.00',
                                        'stock' => 8,
                                        'status' => 'low'
                                    ],

                                    [
                                        'name' => 'Everyday Sneakers',
                                        'category' => 'Fashion',
                                        'price' => '₱1,250.00',
                                        'stock' => 20,
                                        'status' => 'active'
                                    ],

                                    [
                                        'name' => 'Hydrating Face Serum',
                                        'category' => 'Beauty',
                                        'price' => '₱599.00',
                                        'stock' => 4,
                                        'status' => 'low'
                                    ],

                                    [
                                        'name' => 'Daily Sunscreen',
                                        'category' => 'Beauty',
                                        'price' => '₱399.00',
                                        'stock' => 25,
                                        'status' => 'active'
                                    ],

                                    [
                                        'name' => 'Ceramic Coffee Mug',
                                        'category' => 'Home',
                                        'price' => '₱299.00',
                                        'stock' => 0,
                                        'status' => 'out'
                                    ],

                                    [
                                        'name' => 'Minimalist Desk Lamp',
                                        'category' => 'Home',
                                        'price' => '₱899.00',
                                        'stock' => 12,
                                        'status' => 'active'
                                    ],

                                    [
                                        'name' => 'Wireless Earbuds',
                                        'category' => 'Electronics',
                                        'price' => '₱1,499.00',
                                        'stock' => 3,
                                        'status' => 'low'
                                    ],

                                    [
                                        'name' => 'Portable Power Bank',
                                        'category' => 'Electronics',
                                        'price' => '₱799.00',
                                        'stock' => 18,
                                        'status' => 'active'
                                    ],

                                    [
                                        'name' => 'Smart LED Bulb',
                                        'category' => 'Electronics',
                                        'price' => '₱499.00',
                                        'stock' => 0,
                                        'status' => 'out'
                                    ],

                                    [
                                        'name' => 'Soft Cotton Bedsheet',
                                        'category' => 'Home',
                                        'price' => '₱750.00',
                                        'stock' => 6,
                                        'status' => 'low'
                                    ],

                                    [
                                        'name' => 'Storage Organizer Box',
                                        'category' => 'Home',
                                        'price' => '₱350.00',
                                        'stock' => 22,
                                        'status' => 'active'
                                    ]

                                ];

                            @endphp

                            @foreach ($products as $product)

                                <tr
                                    data-category="{{ $product['category'] }}"
                                    data-status="{{ $product['status'] }}">

                                    <td>

                                        <div class="product-name">
                                            {{ $product['name'] }}
                                        </div>

                                    </td>

                                    <td>
                                        {{ $product['category'] }}
                                    </td>

                                    <td>
                                        {{ $product['price'] }}
                                    </td>

                                    <td>

                                        <span
                                            class="stock-number
                                            {{ $product['status'] === 'low' ? 'low' : '' }}
                                            {{ $product['status'] === 'out' ? 'empty' : '' }}">

                                            {{ $product['stock'] }}

                                        </span>

                                    </td>

                                    <td>

                                        @if ($product['status'] === 'active')

                                            <span class="status status-active">
                                                Active
                                            </span>

                                        @elseif ($product['status'] === 'low')

                                            <span class="status status-low">
                                                Low Stock
                                            </span>

                                        @else

                                            <span class="status status-out">
                                                Out of Stock
                                            </span>

                                        @endif

                                    </td>

                                    <td>

                                        <div class="action-buttons">

                                            <button
                                                type="button"
                                                class="action-button"
                                                title="View Product"
                                                aria-label="View Product"
                                                onclick="viewProduct(@js($product['name']))">

                                                <i class="far fa-eye"></i>

                                            </button>

                                            <button
                                                type="button"
                                                class="action-button"
                                                title="Edit Product"
                                                aria-label="Edit Product"
                                                onclick="editProduct(@js($product['name']))">

                                                <i class="fas fa-pen"></i>

                                            </button>

                                            <button
                                                type="button"
                                                class="action-button delete"
                                                title="Delete Product"
                                                aria-label="Delete Product"
                                                onclick="deleteProduct(@js($product['name']))">

                                                <i class="far fa-trash-alt"></i>

                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            @endforeach

                        </tbody>

                    </table>

                    <div
                        class="empty-message"
                        id="empty-message">

                        No products found.

                    </div>

                </div>

            </section>

        </main>

    </div>

    <!-- ================= JAVASCRIPT ================= -->

    <script>

        // MOBILE MENU OPEN / CLOSE

        const menuToggle = document.getElementById('menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuOpenIcon = document.getElementById('menu-open-icon');
        const menuCloseIcon = document.getElementById('menu-close-icon');

        menuToggle.addEventListener('click', function () {

            const isOpen = mobileMenu.classList.toggle('show');

            menuToggle.setAttribute('aria-expanded', isOpen);

            if (isOpen) {

                menuOpenIcon.style.display = 'none';
                menuCloseIcon.style.display = 'inline-block';

            } else {

                menuOpenIcon.style.display = 'inline-block';
                menuCloseIcon.style.display = 'none';

            }

        });

        // INVENTORY FILTER

        const inventorySearch = document.getElementById('inventory-search');
        const categoryFilter = document.getElementById('category-filter');
        const statusFilter = document.getElementById('status-filter');
        const inventoryRows = document.querySelectorAll('#inventory-body tr');
        const emptyMessage = document.getElementById('empty-message');

        function filterInventory() {

            const searchValue = inventorySearch.value.toLowerCase().trim();
            const categoryValue = categoryFilter.value;
            const statusValue = statusFilter.value;

            let visibleRows = 0;

            inventoryRows.forEach(function (row) {

                const productText = row.textContent.toLowerCase();
                const category = row.dataset.category;
                const status = row.dataset.status;

                const matchesSearch = productText.includes(searchValue);

                const matchesCategory =
                    categoryValue === 'all' ||
                    category === categoryValue;

                const matchesStatus =
                    statusValue === 'all' ||
                    status === statusValue;

                if (
                    matchesSearch &&
                    matchesCategory &&
                    matchesStatus
                ) {

                    row.style.display = '';
                    visibleRows++;

                } else {

                    row.style.display = 'none';

                }

            });

            emptyMessage.style.display =
                visibleRows === 0 ? 'block' : 'none';

        }

        inventorySearch.addEventListener('input', filterInventory);

        categoryFilter.addEventListener('change', filterInventory);

        statusFilter.addEventListener('change', filterInventory);

        // ACTION BUTTONS

        function viewProduct(productName) {

            alert('Viewing product: ' + productName);

        }

        function editProduct(productName) {

            alert('Edit function for: ' + productName);

        }

        function deleteProduct(productName) {

            const confirmed = confirm(
                'Are you sure you want to delete ' + productName + '?'
            );

            if (confirmed) {

                alert('Delete function for: ' + productName);

            }

        }

    </script>

</body>

</html>
