
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Sales Reports</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --cream: #faf7f2;
            --white: #ffffff;
            --brown: #1a1714;
            --text: #51463f;
            --muted: #6b5f54;
            --light-brown: #b28b6f;
            --border: #eee7df;
            --soft: #f5f0ea;
            --green: #2d7d46;
        }

        body {
            background: var(--cream);
            color: var(--brown);
            font-family: "Inter", sans-serif;
        }

        button,
        input {
            font-family: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* ================= HEADER ================= */

        .navbar {
            height: 64px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: relative;
            z-index: 100;
        }

        .nav-spacer {
            width: 260px;
        }

        .logo {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        }

        .logo img {
            width: 78px;
            height: auto;
            display: block;
        }

        .nav-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .search-box {
            width: 220px;
            height: 38px;
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 0 12px;
        }

        .search-box i {
            color: #9b8878;
        }

        .search-box input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            color: var(--brown);
            font-size: 12px;
        }

        .nav-icon {
            position: relative;
            border: none;
            background: transparent;
            color: var(--text);
            font-size: 17px;
            cursor: pointer;
            text-decoration: none;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -9px;
            min-width: 16px;
            height: 16px;
            padding: 0 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-brown);
            color: white;
            border-radius: 50%;
            font-size: 9px;
        }

        .nav-divider {
            width: 1px;
            height: 28px;
            background: #e5ddd5;
        }

        .logout-btn {
            border: none;
            background: transparent;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
        }

        .logout-btn:hover {
            color: var(--light-brown);
        }

        /* ================= LAYOUT ================= */

        .dashboard-wrapper {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        /* ================= SIDEBAR ================= */

        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background: var(--white);
            border-right: 1px solid var(--border);
            padding: 28px 18px;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
            list-style: none;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 18px;
            border-radius: 10px;
            color: #76685e;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s ease;
        }

        .menu-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .menu-item:hover {
            background: #faf3ec;
            color: var(--light-brown);
        }

        .menu-item.active {
            background: var(--light-brown);
            color: white;
            box-shadow: 0 4px 12px rgba(178, 139, 111, 0.2);
        }

        .menu-item.active:hover {
            background: #a17b61;
            color: white;
        }

        .menu-badge {
            margin-left: auto;
            background: var(--light-brown);
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 10px;
        }

        .sidebar-divider {
            height: 1px;
            background: var(--border);
            margin: 14px 0;
        }

        /* ================= MAIN CONTENT ================= */

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 32px 40px;
            background: var(--cream);
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 { color: var(--brown); }

        .page-header h1 {
            font-family: "Playfair Display", serif;
            font-size: 30px;
            font-weight: 600;
            color: var(--brown);
        }

        .subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 14px;
        }

        /* ================= DATE FILTER ================= */

        .date-filter {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: var(--white);
            border: 1px solid var(--border);
            padding: 13px 16px;
            margin-bottom: 24px;
        }

        .date-field {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
        }

        .date-field label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .date-field input {
            height: 38px;
            padding: 8px 10px;
            border: 1px solid var(--border);
            outline: none;
            color: #1e1e1e;
            background: white;
            font-size: 12px;
        }

        .date-field input:focus {
            border-color: var(--light-brown);
        }

        .btn-apply, .btn-export {
            height: 38px;
            padding: 0 20px;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .btn-apply { background: var(--brown); }
        .btn-export { background: #2d7d46; }

        .btn-apply:hover {
            background: #b28b6f;
        }

        .btn-export:hover { background: #1e5a34; }

        /* ================= STATS ================= */

        .report-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .report-stat {
            background: var(--white);
            border: 1px solid #ece4db;
            padding: 18px;
        }

        .stat-label {
            color: var(--muted);
            margin-bottom: 8px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .stat-number {
            color: var(--brown);
            font-size: 24px;
            font-weight: 800;
            word-break: break-word;
        }

        .stat-change {
            display: block;
            margin-top: 8px;
            color: var(--green);
            font-size: 10px;
        }

        /* ================= CHARTS ================= */

        .chart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .chart-box {
            background: var(--white);
            border: 1px solid #ece4db;
            padding: 20px;
            min-width: 0;
        }

        .chart-box h3,
        .top-products h3 {
            margin-bottom: 20px;
            color: var(--brown);
            font-size: 13px;
            font-weight: 800;
        }

        .chart-placeholder {
            height: 210px;
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            gap: 12px;
            padding: 28px 20px 25px;
            background: var(--soft);
            overflow-x: auto;
            overflow-y: hidden;
        }

        .chart-bar {
            position: relative;
            width: 8%;
            min-width: 12px;
            min-height: 4px;
            background: #1a1714;
            border-radius: 2px 2px 0 0;
            transition: 0.2s;
        }

        .category-chart { height: 210px; display: flex; background: var(--soft); align-items: stretch; justify-content: space-around; flex-direction: column; gap: 10px; padding: 15px 0; }
        .category-row { display: grid; grid-template-columns: 90px 1fr 54px; align-items: center; gap: 10px; padding: 0 15px; }
        .category-name { color: var(--muted); font-size: 10px; font-weight: 600; }
        .category-track { height: 13px; background: #e4dcd3; overflow: hidden; }
        .category-fill { height: 100%; background: #1a1714; }
        .category-amount { color: var(--brown); font-size: 9px; font-weight: 700; text-align: right; }

        .chart-bar:hover {
            background: var(--light-brown) !important;
        }

        .bar-value {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            color: var(--brown);
            font-size: 9px;
            white-space: nowrap;
        }

        .bar-label {
            position: absolute;
            bottom: -22px;
            left: 50%;
            transform: translateX(-50%);
            color: var(--muted);
            font-size: 9px;
            white-space: nowrap;
        }

        /* ================= TABLE ================= */

        .top-products {
            background: var(--white);
            border: 1px solid #ece4db;
            padding: 0;
            overflow-x: auto;
        }

        .top-products table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
        }

        .top-products th {
            padding: 14px 16px;
            background: var(--soft);
            border-bottom: 1px solid #ece4db;
            color: var(--muted);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-align: left;
            text-transform: uppercase;
        }

        .top-products td {
            padding: 15px 16px;
            border-bottom: 1px solid var(--soft);
            color: var(--text);
            font-size: 11px;
        }

        .top-products tbody tr:hover td {
            background: var(--cream);
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-image {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            overflow: hidden;
            background: var(--soft);
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-name {
            color: var(--brown);
            font-weight: 500;
        }

        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: var(--soft);
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
        }

        .rank-badge.gold {
            background: #d4af37;
            color: white;
        }

        .rank-badge.silver {
            background: #c0c0c0;
            color: white;
        }

        .rank-badge.bronze {
            background: #cd7f32;
            color: white;
        }

        /* ================= NOTIFICATION ================= */

        .zaylo-notification {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            padding: 14px 22px;
            background: var(--brown);
            color: white;
            font-size: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1100px) {
            .report-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .chart-container {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 28px;
            }
        }

        @media (max-width: 820px) {
            .navbar {
                padding: 0 18px;
            }

            .nav-spacer {
                display: none;
            }

            .search-box {
                width: 160px;
            }

            .dashboard-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 10px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-menu {
                flex-direction: row;
                overflow-x: auto;
            }

            .menu-item {
                padding: 11px 14px;
                white-space: nowrap;
            }

            .menu-badge,
            .sidebar-divider {
                display: none;
            }
        }

        @media (max-width: 600px) {
            .search-box {
                width: 35px;
                border: none;
                background: transparent;
                padding: 0;
            }

            .search-box input {
                display: none;
            }

            .nav-right {
                gap: 12px;
            }

            .logout-btn {
                font-size: 0;
            }

            .logout-btn i {
                font-size: 16px;
            }

            .main-content {
                padding: 20px 14px;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .report-stats {
                gap: 10px;
            }

            .report-stat {
                padding: 16px;
            }

            .stat-number {
                font-size: 20px;
            }

            .date-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .date-filter input,
            .date-field,
            .btn-apply,
            .btn-export {
                width: 100%;
            }

            .chart-box,
            .top-products {
                padding: 16px;
            }

            .chart-placeholder {
                gap: 8px;
                padding-left: 8px;
                padding-right: 8px;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/seller-theme.css') }}">
</head>

<body class="seller-dashboard-sidebar">

    <!-- ================= HEADER ================= -->

    <header class="navbar">
        <div class="nav-spacer"></div>

        <a href="{{ route('seller.dashboard') }}" class="logo">
            <img src="{{ asset('images/logo.png') }}" alt="ZAYLO Logo">
        </a>

        <div class="nav-right">

            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="globalSearch" placeholder="Search products...">
            </div>

            <button class="nav-icon" type="button" aria-label="Notifications">
                <i class="far fa-bell"></i>
                <span class="notification-badge">5</span>
            </button>

            <button class="nav-icon" type="button" aria-label="Shopping bag">
                <i class="fas fa-shopping-bag"></i>
                <span class="notification-badge">2</span>
            </button>

            <a href="{{ route('seller.account') }}" class="nav-icon" aria-label="Account">
                <i class="far fa-user"></i>
            </a>

            <div class="nav-divider"></div>

            <form action="{{ route('login') }}" method="GET">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </button>
            </form>

        </div>
    </header>

    <!-- ================= DASHBOARD LAYOUT ================= -->

    <div class="dashboard-wrapper">

        <!-- ================= SIDEBAR ================= -->

        <aside class="sidebar">
            <ul class="sidebar-menu">

                <li>
                    <a href="{{ route('seller.dashboard') }}" class="menu-item">
                        <i class="fas fa-th-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('seller.orders') }}" class="menu-item">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Orders</span>
                        <span class="menu-badge">3</span>
                    </a>
                </li>

                <!-- HANDOVER MENU -->
                <li>
                    <a href="{{ route('seller.handover') }}" class="menu-item">
                        <i class="fas fa-truck"></i>
                        <span>Handover</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('seller.inventory') }}" class="menu-item">
                        <i class="fas fa-boxes"></i>
                        <span>Inventory</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('seller.products') }}" class="menu-item">
                        <i class="fas fa-box-open"></i>
                        <span>Products</span>
                    </a>
                </li>

                <!-- ACTIVE REPORTS MENU -->
                <li>
                    <a href="{{ route('seller.reports') }}" class="menu-item active">
                        <i class="fas fa-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('seller.chat') }}" class="menu-item">
                        <i class="fas fa-comments"></i>
                        <span>Chat</span>
                        <span class="menu-badge">2</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('seller.account') }}" class="menu-item">
                        <i class="fas fa-user-circle"></i>
                        <span>Account</span>
                    </a>
                </li>

                <div class="sidebar-divider"></div>

                <li>
                    <a href="{{ route('login') }}" class="menu-item">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>

            </ul>
        </aside>

        <!-- ================= MAIN CONTENT ================= -->

        <main class="main-content">

            <div class="page-header">
                <div>
                    <h1>Sales Reports</h1>
                    <p class="subtitle">Track sales and product performance for your store</p>
                </div>
            </div>

            <!-- DATE FILTER -->

            <div class="date-filter">

                <label for="dateFrom">From</label>
                <input type="date" id="dateFrom" value="2026-09-01">

                <div class="date-field">
                    <label for="dateTo">To</label>
                    <input type="date" id="dateTo" value="2026-09-30">
                </div>

                <button class="btn-apply" type="button" onclick="generateReport()">
                    Generate Report
                </button>
                <button class="btn-export" type="button" onclick="exportPdfReport()">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>

            </div>

            <!-- REPORT STATS -->

            <div class="report-stats">

                <div class="report-stat">
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-number" id="totalSales">&#8369;101,750</div>
                    <span class="stat-change">For the selected period</span>
                </div>

                <div class="report-stat">
                    <div class="stat-label">Orders</div>
                    <div class="stat-number" id="totalOrders">24</div>
                    <span class="stat-change">Orders in the selected period</span>
                </div>

                <div class="report-stat">
                    <div class="stat-label">Top Product Revenue</div>
                    <div class="stat-number" id="avgOrder">&#8369;34,000</div>
                    <span class="stat-change">Wool Blend Blazer</span>
                </div>

                <div class="report-stat">
                    <div class="stat-label">Units Sold</div>
                    <div class="stat-number" id="totalCommission">26</div>
                    <span class="stat-change">Across 5 products</span>
                </div>

            </div>

            <!-- CHARTS -->

            <div class="chart-container">

                <div class="chart-box">
                    <h3>Daily Product Sales</h3>
                    <div class="chart-placeholder" id="salesChart"></div>
                </div>

                <div class="chart-box">
                    <h3>Sales by Product</h3>
                    <div class="category-chart" id="categoryChart"></div>
                </div>

            </div>

            <!-- TOP PRODUCTS -->

            <div class="top-products">

                <h3>Product Sales</h3>

                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Units Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>

                    <tbody id="topProductsBody"></tbody>
                </table>

            </div>

        </main>

    </div>

    <!-- ================= JAVASCRIPT ================= -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        const salesData = {
            dailySales: [
                { day: "Mon", amount: 5200 },
                { day: "Tue", amount: 3800 },
                { day: "Wed", amount: 7200 },
                { day: "Thu", amount: 4500 },
                { day: "Fri", amount: 8900 },
                { day: "Sat", amount: 10500 },
                { day: "Sun", amount: 6200 }
            ],

            categorySales: [
                { category: "Wool Blend Blazer", amount: 34000 },
                { category: "Leather Tote Bag", amount: 22500 },
                { category: "Classic Sneakers", amount: 14450 },
                { category: "Minimalist Watch", amount: 15600 },
                { category: "Cashmere Sweater", amount: 15200 }
            ],

            topProducts: [
                {
                    name: "Wool Blend Blazer",
                    category: "Clothing",
                    units: 8,
                    revenue: 34000,
                    image: "https://images.unsplash.com/photo-1539008835657-9e8e9680c956?w=100&h=100&fit=crop"
                },

                {
                    name: "Leather Tote Bag",
                    category: "Bags",
                    units: 6,
                    revenue: 22500,
                    image: "https://images.unsplash.com/photo-1560343090-f0409e92791a?w=100&h=100&fit=crop"
                },

                {
                    name: "Classic Sneakers",
                    category: "Shoes",
                    units: 5,
                    revenue: 14450,
                    image: "https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=100&h=100&fit=crop"
                },

                {
                    name: "Minimalist Watch",
                    category: "Watches",
                    units: 3,
                    revenue: 15600,
                    image: "https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=100&h=100&fit=crop"
                },

                {
                    name: "Cashmere Sweater",
                    category: "Clothing",
                    units: 4,
                    revenue: 15200,
                    image: "https://images.unsplash.com/photo-1576566588028-4147f3842f27?w=100&h=100&fit=crop"
                }
            ]
        };

        const fallbackImage =
            "https://via.placeholder.com/100x100?text=ZAYLO";

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function renderSalesChart() {
            const container = document.getElementById("salesChart");

            const maxAmount = Math.max(
                ...salesData.dailySales.map(item => item.amount)
            );

            container.innerHTML = salesData.dailySales.map(item => {
                const height = (item.amount / maxAmount) * 160;

                return `
                    <div
                        class="chart-bar"
                        style="height: ${height}px;"
                        title="${item.day}: ₱${item.amount.toLocaleString()}"
                    >
                        <span class="bar-value">
                            ₱${(item.amount / 1000).toFixed(1)}k
                        </span>

                        <span class="bar-label">
                            ${escapeHtml(item.day)}
                        </span>
                    </div>
                `;
            }).join("");
        }

        function renderCategoryChart() {
            const container = document.getElementById("categoryChart");
            const maxAmount = Math.max(...salesData.categorySales.map(item => item.amount));
            container.innerHTML = salesData.categorySales.map(item => {
                const width = (item.amount / maxAmount) * 100;
                return `
                    <div class="category-row" title="${escapeHtml(item.category)}">
                        <span class="category-name">${escapeHtml(item.category)}</span>
                        <div class="category-track"><div class="category-fill" style="width:${width}%"></div></div>
                        <span class="category-amount">&#8369;${(item.amount / 1000).toFixed(1)}k</span>
                    </div>
                `;
            }).join("");
        }

        function renderTopProducts() {
            const tbody = document.getElementById("topProductsBody");

            const rankClasses = [
                "gold",
                "silver",
                "bronze",
                "",
                ""
            ];

            tbody.innerHTML = salesData.topProducts.map((product, index) => {
                const rank = index + 1;
                const rankClass = rankClasses[index] || "";

                return `
                    <tr>
                        <td>
                            <span class="rank-badge ${rankClass}">
                                ${rank}
                            </span>
                        </td>

                        <td>
                            <div class="product-cell">
                                <div class="product-image">
                                    <img
                                        src="${escapeHtml(product.image)}"
                                        alt="${escapeHtml(product.name)}"
                                        loading="lazy"
                                        onerror="this.onerror=null; this.src='${fallbackImage}';"
                                    >
                                </div>

                                <span class="product-name">
                                    ${escapeHtml(product.name)}
                                </span>
                            </div>
                        </td>

                        <td>${escapeHtml(product.category)}</td>

                        <td>${product.units}</td>

                        <td>
                            ₱${product.revenue.toLocaleString("en-PH", {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            })}
                        </td>
                    </tr>
                `;
            }).join("");
        }

        function generateReport() {
            const from = document.getElementById("dateFrom").value;
            const to = document.getElementById("dateTo").value;

            if (!from || !to) {
                showNotification("Please select both date ranges.");
                return;
            }

            if (from > to) {
                showNotification(
                    "The start date cannot be later than the end date."
                );
                return;
            }

            showNotification(`Report generated: ${from} to ${to}`);
        }

        function exportPdfReport() {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                showNotification("PDF export is unavailable. Please try again.");
                return;
            }

            const from = document.getElementById("dateFrom").value;
            const to = document.getElementById("dateTo").value;
            if (!from || !to || from > to) {
                showNotification("Select a valid report date range before downloading.");
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: "portrait", unit: "pt", format: "a4" });
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const margin = 36;
            const contentWidth = pageWidth - margin * 2;
            const money = amount => `PHP ${Number(amount).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const products = [...salesData.topProducts].sort((a, b) => b.revenue - a.revenue);
            const grossSales = products.reduce((sum, product) => sum + product.revenue, 0);
            const unitsSold = products.reduce((sum, product) => sum + product.units, 0);
            const commission = grossSales * 0.10;
            const averageSale = unitsSold ? grossSales / unitsSold : 0;
            const formatDate = value => new Date(`${value}T00:00:00`).toLocaleDateString("en-PH", { day: "numeric", month: "short", year: "numeric" });

            doc.setFillColor(245, 240, 234);
            doc.rect(0, 0, pageWidth, 44, "F");
            doc.setFont("helvetica", "bold");
            doc.setFontSize(8);
            doc.setTextColor(105, 91, 78);
            doc.text("SELLER REPORT", margin, 27);

            const logo = document.querySelector(".logo img");
            if (logo && logo.complete && logo.naturalWidth > 0) {
                try { doc.addImage(logo, "PNG", pageWidth / 2 - 31, 12, 62, 21); }
                catch (_) { doc.setFontSize(20); doc.setTextColor(22, 21, 19); doc.text("ZAYLO", pageWidth / 2, 29, { align: "center" }); }
            } else {
                doc.setFontSize(20);
                doc.setTextColor(22, 21, 19);
                doc.text("ZAYLO", pageWidth / 2, 29, { align: "center" });
            }

            doc.setFont("times", "bold");
            doc.setFontSize(21);
            doc.setTextColor(28, 27, 25);
            doc.text("Sales Summary Report", margin, 72);
            doc.setFont("helvetica", "normal");
            doc.setFontSize(8);
            doc.setTextColor(105, 91, 78);
            doc.text(`Period: ${formatDate(from)} to ${formatDate(to)}`, margin, 91);
            const generatedAt = new Date().toLocaleString("en-PH", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
            doc.text(`Generated: ${generatedAt}`, pageWidth - margin, 91, { align: "right" });

            const cards = [
                ["GROSS SALES", money(grossSales), "Selected period"],
                ["UNITS SOLD", String(unitsSold), "Across your products"],
                ["AVERAGE SALE", money(averageSale), "Per unit sold"],
                ["COMMISSION RATE 10%", money(commission), "Platform share"]
            ];
            const cardGap = 9;
            const cardY = 106;
            const cardHeight = 76;
            const cardWidth = (contentWidth - cardGap * 3) / 4;
            cards.forEach((card, index) => {
                const x = margin + index * (cardWidth + cardGap);
                doc.setFillColor(248, 244, 239);
                doc.setDrawColor(231, 222, 213);
                doc.rect(x, cardY, cardWidth, cardHeight, "FD");
                doc.setFont("helvetica", "bold");
                doc.setFontSize(6);
                doc.setTextColor(105, 91, 78);
                doc.text(card[0], x + 8, cardY + 16);
                doc.setFontSize(8);
                doc.setTextColor(28, 27, 25);
                doc.text(card[1], x + 8, cardY + 38, { maxWidth: cardWidth - 16 });
                doc.setFont("helvetica", "normal");
                doc.setFontSize(6);
                doc.setTextColor(145, 133, 121);
                doc.text(card[2], x + 8, cardY + 57);
            });

            let y = 211;
            doc.setFont("times", "bold");
            doc.setFontSize(12);
            doc.setTextColor(28, 27, 25);
            doc.text("Sales by product", margin, y);
            doc.setFont("helvetica", "normal");
            doc.setFontSize(7);
            doc.setTextColor(105, 91, 78);
            doc.text("Gross sales by product, highest first", margin, y + 12);
            const chartProducts = products.slice(0, 5);
            const chartY = y + 22;
            const chartHeight = Math.max(86, chartProducts.length * 21 + 18);
            doc.setDrawColor(231, 222, 213);
            doc.rect(margin, chartY, contentWidth, chartHeight);
            const maxRevenue = Math.max(...chartProducts.map(product => product.revenue), 1);
            const labelWidth = 118;
            const barX = margin + labelWidth + 8;
            const barWidth = contentWidth - labelWidth - 65;
            chartProducts.forEach((product, index) => {
                const rowY = chartY + 17 + index * 21;
                doc.setFont("helvetica", "normal");
                doc.setFontSize(7);
                doc.setTextColor(62, 55, 49);
                doc.text(doc.splitTextToSize(product.name, labelWidth - 8)[0] || "", margin + 8, rowY + 6);
                doc.setFillColor(241, 236, 230);
                doc.rect(barX, rowY, barWidth, 9, "F");
                doc.setFillColor(83, 112, 89);
                doc.rect(barX, rowY, Math.max(2, barWidth * product.revenue / maxRevenue), 9, "F");
                doc.setFont("helvetica", "bold");
                doc.setFontSize(7);
                doc.setTextColor(35, 32, 29);
                doc.text(money(product.revenue), pageWidth - margin - 7, rowY + 7, { align: "right" });
            });

            y = chartY + chartHeight + 25;
            doc.setFont("times", "bold");
            doc.setFontSize(12);
            doc.setTextColor(28, 27, 25);
            doc.text("Product sales", margin, y);
            doc.setFont("helvetica", "normal");
            doc.setFontSize(7);
            doc.setTextColor(105, 91, 78);
            doc.text("Sales summary. Philippine pesos (PHP).", margin, y + 11);
            y += 17;

            const drawTableHeader = () => {
                doc.setFillColor(233, 228, 216);
                doc.rect(margin, y, contentWidth, 20, "F");
                doc.setFont("helvetica", "bold");
                doc.setFontSize(7);
                doc.setTextColor(81, 70, 63);
                doc.text("PRODUCT", margin + 8, y + 13);
                doc.text("UNITS SOLD", margin + 275, y + 13);
                doc.text("GROSS SALES", margin + 365, y + 13);
                doc.text("COMMISSION", pageWidth - margin - 8, y + 13, { align: "right" });
                y += 20;
            };
            drawTableHeader();
            products.forEach((product, index) => {
                if (y + 22 > pageHeight - 56) {
                    doc.addPage();
                    y = 42;
                    doc.setFont("times", "bold");
                    doc.setFontSize(15);
                    doc.setTextColor(28, 27, 25);
                    doc.text("Product sales (continued)", margin, y);
                    y += 12;
                    drawTableHeader();
                }
                if (index % 2) {
                    doc.setFillColor(250, 248, 245);
                    doc.rect(margin, y, contentWidth, 22, "F");
                }
                doc.setFont("helvetica", "normal");
                doc.setFontSize(7);
                doc.setTextColor(35, 32, 29);
                doc.text(doc.splitTextToSize(product.name, 240)[0] || "", margin + 8, y + 14);
                doc.text(String(product.units), margin + 290, y + 14);
                doc.text(money(product.revenue), margin + 365, y + 14);
                doc.text(money(product.revenue * 0.10), pageWidth - margin - 8, y + 14, { align: "right" });
                doc.setDrawColor(235, 229, 222);
                doc.line(margin, y + 22, pageWidth - margin, y + 22);
                y += 22;
            });

            const pageCount = doc.internal.getNumberOfPages();
            for (let page = 1; page <= pageCount; page++) {
                doc.setPage(page);
                doc.setDrawColor(220, 211, 201);
                doc.line(margin, pageHeight - 43, pageWidth - margin, pageHeight - 43);
                doc.setFont("helvetica", "normal");
                doc.setFontSize(6);
                doc.setTextColor(120, 108, 97);
                doc.text("Commission is calculated at 10%. Figures reflect sample data until connected to live order records.", margin, pageHeight - 29, { maxWidth: contentWidth - 10 });
                doc.text("Generated by: Seller", margin, pageHeight - 12);
                doc.text(`Seller Sales Summary  |  Page ${page} of ${pageCount}`, pageWidth - margin, pageHeight - 12, { align: "right" });
            }
            doc.save(`zaylo-seller-sales-${from}-to-${to}.pdf`);
            showNotification("Seller sales PDF downloaded successfully.");
        }

        function showNotification(message) {
            const existing = document.querySelector(
                ".zaylo-notification"
            );

            if (existing) {
                existing.remove();
            }

            const notification = document.createElement("div");

            notification.className = "zaylo-notification";
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }

        document.getElementById("globalSearch").addEventListener(
            "input",
            function () {
                const searchTerm = this.value.toLowerCase().trim();

                const rows = document.querySelectorAll(
                    "#topProductsBody tr"
                );

                rows.forEach(row => {
                    row.style.display = row.textContent
                        .toLowerCase()
                        .includes(searchTerm)
                        ? ""
                        : "none";
                });
            }
        );

        document.addEventListener("DOMContentLoaded", () => {
            renderSalesChart();
            renderCategoryChart();
            renderTopProducts();
        });
    </script>

</body>
</html>
