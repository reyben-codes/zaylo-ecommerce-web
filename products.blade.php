
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>ZAYLO | Product Management</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --cream: #faf7f2;
            --white: #ffffff;
            --brown: #b28b6f;
            --dark-brown: #6b5140;
            --dark: #1a1714;
            --gray: #77716b;
            --light-gray: #eee8e1;
            --green: #4f8065;
            --red: #b85c5c;
            --shadow: 0 8px 30px rgba(80, 55, 35, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: var(--cream);
            color: var(--dark);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input,
        select,
        textarea {
            font-family: inherit;
        }

        button {
            cursor: pointer;
        }

        .navbar {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            padding: 0 32px;
            background: var(--white);
            border-bottom: 1px solid var(--light-gray);
            z-index: 100;
        }

        .nav-spacer {
            width: 250px;
        }

        .logo {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }

        .logo img {
            display: block;
            width: 78px;
            height: auto;
            object-fit: contain;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 21px;
            margin-left: auto;
        }

        .nav-icon {
            position: relative;
            color: var(--dark);
            font-size: 17px;
            transition: color 0.2s ease;
        }

        .nav-icon:hover {
            color: var(--brown);
        }

        .nav-badge {
            position: absolute;
            top: -9px;
            right: -10px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 50px;
            background: var(--brown);
            color: var(--white);
            font-size: 9px;
            font-weight: 700;
        }

        .nav-divider {
            width: 1px;
            height: 25px;
            background: var(--light-gray);
        }

        .logout-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray);
            font-size: 12px;
            font-weight: 600;
        }

        .logout-link:hover {
            color: var(--dark);
        }

        .layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            padding: 30px 16px;
            background: var(--white);
            border-right: 1px solid var(--light-gray);
        }

        .sidebar-title {
            margin: 0 15px 18px;
            color: #aaa19a;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 13px 15px;
            border-radius: 10px;
            color: #7d756e;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .menu-item i {
            width: 18px;
            text-align: center;
            font-size: 15px;
        }

        .menu-item:hover {
            background: #f8f2ec;
            color: var(--dark-brown);
        }

        .menu-item.active {
            background: #f3e9df;
            color: var(--dark-brown);
            font-weight: 700;
        }

        .sidebar-divider {
            border: 0;
            border-top: 1px solid var(--light-gray);
            margin: 15px 0;
        }

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 38px 42px;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
        }

        .eyebrow {
            margin-bottom: 8px;
            color: var(--brown);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.8px;
            text-transform: uppercase;
        }

        .page-title {
            margin-bottom: 8px;
            font-family: "Playfair Display", serif;
            font-size: 34px;
            font-weight: 600;
            line-height: 1.2;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 13px;
        }

        .primary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 18px;
            border: none;
            border-radius: 8px;
            background: var(--dark);
            color: var(--white);
            font-size: 12px;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .primary-button:hover {
            background: var(--dark-brown);
            transform: translateY(-1px);
        }

        .search-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 24px;
        }

        .search-box {
            position: relative;
            width: 100%;
            max-width: 360px;
        }

        .search-box i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #aaa19a;
            font-size: 13px;
        }

        .search-box input {
            width: 100%;
            padding: 13px 15px 13px 40px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            outline: none;
            background: var(--white);
            color: var(--dark);
            font-size: 12px;
        }

        .search-box input:focus {
            border-color: var(--brown);
        }

        .product-count {
            color: var(--gray);
            font-size: 12px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .product-card {
            overflow: hidden;
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            background: var(--white);
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(80, 55, 35, 0.1);
        }

        .product-image-wrapper {
            position: relative;
            height: 245px;
            overflow: hidden;
            background: #f3eee8;
        }

        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .product-card:hover .product-image {
            transform: scale(1.04);
        }

        .status-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 6px 9px;
            border-radius: 50px;
            background: var(--white);
            color: var(--green);
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-badge.inactive {
            color: var(--red);
        }

        .product-content {
            padding: 18px;
        }

        .product-category {
            margin-bottom: 6px;
            color: var(--brown);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .product-name {
            min-height: 42px;
            margin-bottom: 12px;
            font-family: "Playfair Display", serif;
            font-size: 18px;
            font-weight: 600;
            line-height: 1.25;
        }

        .product-description {
            min-height: 36px;
            margin-bottom: 14px;
            color: var(--gray);
            font-size: 11px;
            line-height: 1.6;
        }

        .product-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 16px;
        }

        .product-price {
            font-size: 16px;
            font-weight: 700;
        }

        .product-stock {
            color: var(--gray);
            font-size: 11px;
        }

        .product-stock.low,
        .product-stock.out {
            color: var(--red);
            font-weight: 700;
        }

        .product-actions {
            display: flex;
            gap: 8px;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            flex: 1;
            padding: 10px 8px;
            border: 1px solid var(--light-gray);
            border-radius: 7px;
            background: var(--white);
            color: var(--dark);
            font-size: 10px;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .action-button:hover {
            border-color: var(--brown);
            background: #faf5ef;
        }

        .action-button.delete:hover {
            border-color: var(--red);
            color: var(--red);
        }

        .empty-state {
            grid-column: 1 / -1;
            padding: 70px 20px;
            border: 1px dashed #d9cec3;
            border-radius: 12px;
            text-align: center;
            color: var(--gray);
        }

        .empty-state i {
            margin-bottom: 15px;
            color: var(--brown);
            font-size: 32px;
        }

        .empty-state h3 {
            margin-bottom: 8px;
            color: var(--dark);
            font-family: "Playfair Display", serif;
            font-size: 22px;
        }

        .empty-state p {
            font-size: 12px;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(26, 23, 20, 0.55);
            z-index: 999;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            width: 100%;
            max-width: 570px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            border-radius: 14px;
            background: var(--white);
            box-shadow: 0 20px 70px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 25px;
        }

        .modal-title {
            font-family: "Playfair Display", serif;
            font-size: 25px;
            font-weight: 600;
        }

        .close-modal {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 50%;
            background: #f5f0ea;
            color: var(--dark);
        }

        .close-modal:hover {
            background: #eaded2;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 17px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: var(--dark);
            font-size: 11px;
            font-weight: 700;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--light-gray);
            border-radius: 7px;
            outline: none;
            background: var(--white);
            color: var(--dark);
            font-size: 12px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--brown);
        }

        .form-group textarea {
            min-height: 90px;
            resize: vertical;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }

        .secondary-button {
            padding: 12px 18px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            background: var(--white);
            color: var(--dark);
            font-size: 12px;
            font-weight: 700;
        }

        .secondary-button:hover {
            background: #faf5ef;
        }

        .notification {
            position: fixed;
            right: 25px;
            bottom: 25px;
            display: none;
            align-items: center;
            gap: 10px;
            max-width: 350px;
            padding: 15px 18px;
            border-radius: 9px;
            background: var(--dark);
            color: var(--white);
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.15);
            font-size: 12px;
            z-index: 1100;
        }

        .notification.show {
            display: flex;
        }

        @media (max-width: 1350px) {
            .products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 1050px) {
            .sidebar {
                width: 220px;
            }

            .main-content {
                padding: 30px;
            }

            .navbar {
                padding: 0 22px;
            }

            .nav-spacer {
                width: 200px;
            }
        }

        @media (max-width: 820px) {
            .layout {
                display: block;
            }

            .sidebar {
                width: 100%;
                padding: 12px;
                border-right: none;
                border-bottom: 1px solid var(--light-gray);
            }

            .sidebar-title {
                display: none;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 6px;
            }

            .menu-item {
                justify-content: center;
                padding: 11px 5px;
                font-size: 10px;
                text-align: center;
            }

            .menu-item i {
                display: none;
            }

            .main-content {
                padding: 28px 22px;
            }

            .products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .nav-spacer {
                width: 150px;
            }

            .nav-actions {
                gap: 15px;
            }

            .logout-link span {
                display: none;
            }
        }

        @media (max-width: 560px) {
            .navbar {
                height: 60px;
                padding: 0 15px;
            }

            .logo img {
                width: 64px;
            }

            .nav-spacer {
                width: 85px;
            }

            .nav-actions {
                gap: 12px;
            }

            .nav-divider,
            .logout-link {
                display: none;
            }

            .main-content {
                padding: 25px 15px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .page-title {
                font-size: 29px;
            }

            .primary-button {
                width: 100%;
            }

            .search-section {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-box {
                max-width: none;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .product-image-wrapper {
                height: 280px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .modal {
                padding: 22px;
            }

            .modal-footer {
                flex-direction: column-reverse;
            }

            .modal-footer button {
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/seller-theme.css') }}">
</head>

<body class="seller-dashboard-sidebar">

    <!-- HEADER -->
    <header class="navbar">
        <div class="nav-spacer"></div>

        <a href="{{ route('seller.dashboard') }}"
           class="logo"
           aria-label="ZAYLO Dashboard">

            <img src="{{ asset('images/logo.png') }}" alt="ZAYLO Logo">
        </a>

        <div class="nav-actions">
            <a href="#" class="nav-icon" aria-label="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </a>

            <a href="#" class="nav-icon" aria-label="Notifications">
                <i class="fa-regular fa-bell"></i>
                <span class="nav-badge">5</span>
            </a>

            <a href="#" class="nav-icon" aria-label="Shopping bag">
                <i class="fa-solid fa-bag-shopping"></i>
                <span class="nav-badge">2</span>
            </a>

            <a href="{{ route('seller.account') }}"
               class="nav-icon"
               aria-label="Account">

                <i class="fa-regular fa-user"></i>
            </a>

            <div class="nav-divider"></div>

            <a href="{{ route('login') }}" class="logout-link">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>

    <div class="layout">

        <!-- SIDEBAR MENU -->
        <aside class="sidebar">
            <p class="sidebar-title">Seller Center</p>

            <nav class="sidebar-menu">

                <a href="{{ route('seller.dashboard') }}" class="menu-item">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('seller.orders') }}" class="menu-item">
                    <i class="fa-solid fa-box"></i>
                    <span>Orders</span>
                </a>

                <!-- HANDOVER MENU -->
                <a href="{{ route('seller.handover') }}" class="menu-item">
                    <i class="fa-solid fa-truck"></i>
                    <span>Handover</span>
                </a>

                <a href="{{ route('seller.inventory') }}" class="menu-item">
                    <i class="fa-solid fa-warehouse"></i>
                    <span>Inventory</span>
                </a>

                <!-- ACTIVE PAGE -->
                <a href="{{ route('seller.products') }}" class="menu-item active">
                    <i class="fa-solid fa-cube"></i>
                    <span>Products</span>
                </a>

                <a href="{{ route('seller.reports') }}" class="menu-item">
                    <i class="fa-solid fa-file-invoice"></i>
                    <span>Reports</span>
                </a>

                <a href="{{ route('seller.chat') }}" class="menu-item">
                    <i class="fa-regular fa-comments"></i>
                    <span>Chat</span>
                </a>

                <a href="{{ route('seller.account') }}" class="menu-item">
                    <i class="fa-regular fa-user"></i>
                    <span>Account</span>
                </a>

            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <div class="page-header">
                <div>
                    <p class="eyebrow">Seller Center</p>

                    <h1 class="page-title">
                        Product Management
                    </h1>

                    <p class="page-subtitle">
                        Add, edit, and manage your products.
                    </p>
                </div>

                <button type="button"
                        class="primary-button"
                        onclick="openAddProductModal()">

                    <i class="fa-solid fa-plus"></i>
                    Add New Product
                </button>
            </div>

            <div class="search-section">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>

                    <input type="search"
                           id="globalSearch"
                           placeholder="Search products..."
                           aria-label="Search products">
                </div>

                <p class="product-count" id="productCount">
                    8 Products
                </p>
            </div>

            <section class="products-grid" id="productsGrid">
                <!-- Products rendered by JavaScript -->
            </section>

        </main>
    </div>

    <!-- ADD / EDIT PRODUCT MODAL -->
    <div class="modal-overlay"
         id="productModal"
         role="dialog"
         aria-modal="true"
         aria-labelledby="modalTitle">

        <div class="modal">

            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">
                    Add New Product
                </h2>

                <button type="button"
                        class="close-modal"
                        onclick="closeProductModal()"
                        aria-label="Close modal">

                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="productForm" onsubmit="saveProduct(event)">

                <div class="form-grid">

                    <div class="form-group full">
                        <label for="productName">
                            Product Name *
                        </label>

                        <input type="text"
                               id="productName"
                               required
                               placeholder="Enter product name">
                    </div>

                    <div class="form-group">
                        <label for="productCategory">
                            Category *
                        </label>

                        <select id="productCategory" required>
                            <option value="">Select category</option>
                            <option value="clothing">Clothing</option>
                            <option value="bags">Bags</option>
                            <option value="shoes">Shoes</option>
                            <option value="watches">Watches</option>
                            <option value="accessories">Accessories</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="productPrice">
                            Price (₱) *
                        </label>

                        <input type="number"
                               id="productPrice"
                               min="0"
                               step="0.01"
                               required
                               placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="productStock">
                            Stock *
                        </label>

                        <input type="number"
                               id="productStock"
                               min="0"
                               required
                               placeholder="0">
                    </div>

                    <div class="form-group">
                        <label for="productStatus">
                            Status *
                        </label>

                        <select id="productStatus" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group full">
                        <label for="productImage">
                            Image URL
                        </label>

                        <input type="url"
                               id="productImage"
                               placeholder="https://example.com/image.jpg">
                    </div>

                    <div class="form-group full">
                        <label for="productDescription">
                            Description
                        </label>

                        <textarea id="productDescription"
                                  placeholder="Enter product description"></textarea>
                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="secondary-button"
                            onclick="closeProductModal()">

                        Cancel
                    </button>

                    <button type="submit" class="primary-button">
                        <i class="fa-solid fa-check"></i>
                        Save Product
                    </button>

                </div>
            </form>
        </div>
    </div>

    <!-- NOTIFICATION -->
    <div class="notification" id="notification">
        <i class="fa-solid fa-circle-check"></i>
        <span id="notificationMessage"></span>
    </div>

    <script>
        const fallbackImage =
            "https://via.placeholder.com/600x800/f5f0ea/6b5f54?text=No+Image";

        let editingProductId = null;

        let products = [
            {
                id: 1,
                name: "Wool Blend Blazer",
                category: "clothing",
                price: 4250,
                stock: 12,
                status: "active",
                description: "A premium blazer with a clean and timeless silhouette.",
                image: "https://images.unsplash.com/photo-1539008835657-9e8e9680c956?auto=format&fit=crop&w=800&q=80"
            },
            {
                id: 2,
                name: "Leather Tote Bag",
                category: "bags",
                price: 3750,
                stock: 8,
                status: "active",
                description: "A stylish everyday tote with a spacious interior.",
                image: "https://images.unsplash.com/photo-1560343090-f0409e92791a?auto=format&fit=crop&w=800&q=80"
            },
            {
                id: 3,
                name: "Classic Sneakers",
                category: "shoes",
                price: 2890,
                stock: 15,
                status: "active",
                description: "Comfortable sneakers designed for everyday use.",
                image: "https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=800&q=80"
            },
            {
                id: 4,
                name: "Minimalist Watch",
                category: "watches",
                price: 5200,
                stock: 3,
                status: "inactive",
                description: "A minimalist timepiece with an elegant modern design.",
                image: "https://images.unsplash.com/photo-1524592094714-0f0654e20314?auto=format&fit=crop&w=800&q=80"
            },
            {
                id: 5,
                name: "Cashmere Sweater",
                category: "clothing",
                price: 3800,
                stock: 0,
                status: "active",
                description: "A soft and comfortable sweater for a premium look.",
                image: "https://images.unsplash.com/photo-1576566588028-4147f3842f27?auto=format&fit=crop&w=800&q=80"
            },
            {
                id: 6,
                name: "Crossbody Bag",
                category: "bags",
                price: 2200,
                stock: 6,
                status: "active",
                description: "A compact crossbody bag for daily essentials.",
                image: "https://images.unsplash.com/photo-1548036328-c9fa89d128fa?auto=format&fit=crop&w=800&q=80"
            },
            {
                id: 7,
                name: "Leather Boots",
                category: "shoes",
                price: 4800,
                stock: 2,
                status: "active",
                description: "Durable leather boots with a classic finish.",
                image: "https://images.unsplash.com/photo-1549298916-b41d501d3772?auto=format&fit=crop&w=800&q=80"
            },
            {
                id: 8,
                name: "Silk Scarf",
                category: "accessories",
                price: 1200,
                stock: 20,
                status: "active",
                description: "A lightweight silk scarf that completes any outfit.",
                image: "https://images.unsplash.com/photo-1601924994987-69e26d50dc26?auto=format&fit=crop&w=800&q=80"
            }
        ];

        const categoryNames = {
            clothing: "Clothing",
            bags: "Bags",
            shoes: "Shoes",
            watches: "Watches",
            accessories: "Accessories"
        };

        function escapeHtml(value) {
            return String(value ?? "")
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function formatPrice(price) {
            return Number(price).toLocaleString("en-PH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getStockClass(stock) {
            if (stock === 0) return "out";
            if (stock <= 5) return "low";
            return "";
        }

        function getStockText(stock) {
            if (stock === 0) return "Out of stock";
            return `${stock} in stock`;
        }

        function renderProducts(searchTerm = "") {
            const productsGrid = document.getElementById("productsGrid");
            const productCount = document.getElementById("productCount");

            const search = searchTerm.toLowerCase().trim();

            const filteredProducts = products.filter(product => {
                return (
                    product.name.toLowerCase().includes(search) ||
                    product.category.toLowerCase().includes(search) ||
                    product.status.toLowerCase().includes(search)
                );
            });

            productCount.textContent =
                `${filteredProducts.length} Product${filteredProducts.length !== 1 ? "s" : ""}`;

            if (filteredProducts.length === 0) {
                productsGrid.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        <h3>No Products Found</h3>
                        <p>Try searching for another product.</p>
                    </div>
                `;

                return;
            }

            productsGrid.innerHTML = filteredProducts.map(product => {
                const statusText =
                    product.status === "active" ? "Active" : "Inactive";

                const stockClass = getStockClass(product.stock);
                const stockText = getStockText(product.stock);

                return `
                    <article class="product-card">

                        <div class="product-image-wrapper">
                            <img
                                class="product-image"
                                src="${escapeHtml(product.image || fallbackImage)}"
                                alt="${escapeHtml(product.name)}"
                                onerror="this.src='${fallbackImage}'"
                            >

                            <span class="status-badge ${product.status === "inactive" ? "inactive" : ""}">
                                ${statusText}
                            </span>
                        </div>

                        <div class="product-content">

                            <p class="product-category">
                                ${escapeHtml(categoryNames[product.category] || product.category)}
                            </p>

                            <h2 class="product-name">
                                ${escapeHtml(product.name)}
                            </h2>

                            <p class="product-description">
                                ${escapeHtml(product.description || "No description available.")}
                            </p>

                            <div class="product-meta">
                                <span class="product-price">
                                    ₱${formatPrice(product.price)}
                                </span>

                                <span class="product-stock ${stockClass}">
                                    ${stockText}
                                </span>
                            </div>

                            <div class="product-actions">

                                <button
                                    type="button"
                                    class="action-button"
                                    onclick="editProduct(${product.id})">

                                    <i class="fa-solid fa-pen"></i>
                                    Edit
                                </button>

                                <button
                                    type="button"
                                    class="action-button"
                                    onclick="toggleProductStatus(${product.id})">

                                    <i class="fa-solid fa-power-off"></i>
                                    ${product.status === "active" ? "Disable" : "Enable"}
                                </button>

                                <button
                                    type="button"
                                    class="action-button delete"
                                    onclick="deleteProduct(${product.id})">

                                    <i class="fa-solid fa-trash"></i>
                                    Delete
                                </button>

                            </div>
                        </div>
                    </article>
                `;
            }).join("");
        }

        function openAddProductModal() {
            editingProductId = null;

            document.getElementById("modalTitle").textContent =
                "Add New Product";

            document.getElementById("productForm").reset();

            document.getElementById("productStatus").value = "active";

            document.getElementById("productModal")
                .classList.add("show");

            document.getElementById("productName").focus();
        }

        function editProduct(productId) {
            const product = products.find(item => item.id === productId);

            if (!product) return;

            editingProductId = productId;

            document.getElementById("modalTitle").textContent =
                "Edit Product";

            document.getElementById("productName").value = product.name;
            document.getElementById("productCategory").value = product.category;
            document.getElementById("productPrice").value = product.price;
            document.getElementById("productStock").value = product.stock;
            document.getElementById("productStatus").value = product.status;
            document.getElementById("productImage").value = product.image;
            document.getElementById("productDescription").value =
                product.description || "";

            document.getElementById("productModal")
                .classList.add("show");

            document.getElementById("productName").focus();
        }

        function closeProductModal() {
            document.getElementById("productModal")
                .classList.remove("show");

            editingProductId = null;
        }

        function saveProduct(event) {
            event.preventDefault();

            const name = document.getElementById("productName").value.trim();
            const category = document.getElementById("productCategory").value;
            const price = Number(document.getElementById("productPrice").value);
            const stock = Number(document.getElementById("productStock").value);
            const status = document.getElementById("productStatus").value;
            const image = document.getElementById("productImage").value.trim();

            const description =
                document.getElementById("productDescription").value.trim();

            if (!name || !category || price < 0 || stock < 0) {
                showNotification("Please complete the required fields.");
                return;
            }

            const productData = {
                name,
                category,
                price,
                stock,
                status,
                image: image || fallbackImage,
                description
            };

            if (editingProductId !== null) {
                const productIndex = products.findIndex(
                    product => product.id === editingProductId
                );

                if (productIndex !== -1) {
                    products[productIndex] = {
                        ...products[productIndex],
                        ...productData
                    };

                    showNotification("Product updated successfully.");
                }
            } else {
                const newProduct = {
                    id: Date.now(),
                    ...productData
                };

                products.unshift(newProduct);

                showNotification("Product added successfully.");
            }

            renderProducts(
                document.getElementById("globalSearch").value
            );

            closeProductModal();
        }

        function toggleProductStatus(productId) {
            const product = products.find(item => item.id === productId);

            if (!product) return;

            product.status =
                product.status === "active" ? "inactive" : "active";

            renderProducts(
                document.getElementById("globalSearch").value
            );

            showNotification(
                `Product ${product.status === "active" ? "enabled" : "disabled"} successfully.`
            );
        }

        function deleteProduct(productId) {
            const product = products.find(item => item.id === productId);

            if (!product) return;

            const confirmed = confirm(
                `Are you sure you want to delete "${product.name}"?`
            );

            if (!confirmed) return;

            products = products.filter(
                product => product.id !== productId
            );

            renderProducts(
                document.getElementById("globalSearch").value
            );

            showNotification("Product deleted successfully.");
        }

        function showNotification(message) {
            const notification = document.getElementById("notification");
            const notificationMessage =
                document.getElementById("notificationMessage");

            notificationMessage.textContent = message;

            notification.classList.add("show");

            setTimeout(() => {
                notification.classList.remove("show");
            }, 3000);
        }

        document.addEventListener("DOMContentLoaded", () => {
            renderProducts();

            document
                .getElementById("globalSearch")
                .addEventListener("input", event => {
                    renderProducts(event.target.value);
                });

            document
                .getElementById("productModal")
                .addEventListener("click", event => {
                    if (event.target.id === "productModal") {
                        closeProductModal();
                    }
                });

            document.addEventListener("keydown", event => {
                if (event.key === "Escape") {
                    closeProductModal();
                }
            });
        });
    </script>

</body>
</html>
