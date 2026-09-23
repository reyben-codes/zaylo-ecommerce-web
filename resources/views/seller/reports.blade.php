
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
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 14px;
            background: var(--white);
            border: 1px solid #ece4db;
            padding: 20px;
            margin-bottom: 24px;
        }

        .date-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .date-field label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .date-field input {
            padding: 10px 12px;
            border: 1px solid #e5ddd5;
            outline: none;
            color: var(--brown);
            background: white;
            font-size: 12px;
        }

        .date-field input:focus {
            border-color: var(--light-brown);
        }

        .btn-apply {
            padding: 11px 24px;
            border: none;
            background: var(--brown);
            color: white;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .btn-apply:hover {
            background: var(--light-brown);
        }

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
            padding: 22px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 11px;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .stat-number {
            margin-top: 7px;
            color: var(--brown);
            font-size: 27px;
            font-weight: 600;
            word-break: break-word;
        }

        .stat-change {
            display: block;
            margin-top: 6px;
            color: var(--green);
            font-size: 11px;
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
            padding: 24px;
            min-width: 0;
        }

        .chart-box h3,
        .top-products h3 {
            margin-bottom: 20px;
            color: var(--brown);
            font-size: 14px;
            font-weight: 600;
        }

        .chart-placeholder {
            height: 220px;
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            gap: 12px;
            padding: 20px 12px 25px;
            background: var(--soft);
            overflow-x: auto;
            overflow-y: hidden;
        }

        .chart-bar {
            position: relative;
            width: 30px;
            min-width: 20px;
            min-height: 4px;
            background: var(--brown);
            border-radius: 3px 3px 0 0;
            transition: 0.2s;
        }

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
            padding: 24px;
            overflow-x: auto;
        }

        .top-products table {
            width: 100%;
            min-width: 550px;
            border-collapse: collapse;
        }

        .top-products th {
            padding: 12px;
            border-bottom: 1px solid #ece4db;
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-align: left;
            text-transform: uppercase;
        }

        .top-products td {
            padding: 12px;
            border-bottom: 1px solid var(--soft);
            color: var(--text);
            font-size: 12px;
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

            .date-field input,
            .btn-apply {
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
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
</head>

<body class="seller-workspace">

    <!-- ================= HEADER ================= -->

    <header class="navbar">
        <div class="nav-spacer"></div>

        <a href="{{ route('seller.dashboard') }}" class="logo">
            <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO Logo">
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

        @include('partials.seller-sidebar')

        <!-- ================= MAIN CONTENT ================= -->

        <main class="main-content">

            <div class="page-header">
                <h1>Sales Reports</h1>

                <p class="subtitle">
                    Track your sales performance and financial insights
                </p>
            </div>

            <!-- DATE FILTER -->

            <div class="date-filter">

                <div class="date-field">
                    <label for="dateFrom">From</label>
                    <input type="date" id="dateFrom" value="2024-12-01">
                </div>

                <div class="date-field">
                    <label for="dateTo">To</label>
                    <input type="date" id="dateTo" value="2024-12-31">
                </div>

                <button class="btn-apply" type="button" onclick="generateReport()">
                    Generate Report
                </button>

            </div>

            <!-- REPORT STATS -->

            <div class="report-stats">

                <div class="report-stat">
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-number" id="totalSales">₱48,250</div>
                    <span class="stat-change">↑ 12% from last month</span>
                </div>

                <div class="report-stat">
                    <div class="stat-label">Orders</div>
                    <div class="stat-number" id="totalOrders">24</div>
                    <span class="stat-change">↑ 5 from last month</span>
                </div>

                <div class="report-stat">
                    <div class="stat-label">Average Order Value</div>
                    <div class="stat-number" id="avgOrder">₱2,010</div>
                    <span class="stat-change">↑ 8% from last month</span>
                </div>

                <div class="report-stat">
                    <div class="stat-label">Commission (10%)</div>
                    <div class="stat-number" id="totalCommission">₱4,825</div>
                    <span class="stat-change">↑ 12% from last month</span>
                </div>

            </div>

            <!-- CHARTS -->

            <div class="chart-container">

                <div class="chart-box">
                    <h3>Daily Sales (Last 7 Days)</h3>
                    <div class="chart-placeholder" id="salesChart"></div>
                </div>

                <div class="chart-box">
                    <h3>Category Performance</h3>
                    <div class="chart-placeholder" id="categoryChart"></div>
                </div>

            </div>

            <!-- TOP PRODUCTS -->

            <div class="top-products">

                <h3>Top Performing Products</h3>

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
                { category: "Clothing", amount: 18500 },
                { category: "Bags", amount: 14200 },
                { category: "Shoes", amount: 9800 },
                { category: "Watches", amount: 5200 },
                { category: "Accessories", amount: 4550 }
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

            const maxAmount = Math.max(
                ...salesData.categorySales.map(item => item.amount)
            );

            const colors = [
                "#1a1714",
                "#b28b6f",
                "#6b5f54",
                "#8a7a6b",
                "#d4af37"
            ];

            container.innerHTML = salesData.categorySales.map((item, index) => {
                const height = (item.amount / maxAmount) * 160;

                return `
                    <div
                        class="chart-bar"
                        style="height: ${height}px; background: ${colors[index]};"
                        title="${escapeHtml(item.category)}: ₱${item.amount.toLocaleString()}"
                    >
                        <span class="bar-value">
                            ₱${(item.amount / 1000).toFixed(1)}k
                        </span>

                        <span class="bar-label">
                            ${escapeHtml(item.category)}
                        </span>
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

            const sales = 48250 + Math.floor(Math.random() * 5000);
            const orders = 24 + Math.floor(Math.random() * 6);
            const averageOrder = Math.floor(sales / orders);
            const commission = Math.floor(sales * 0.10);

            document.getElementById("totalSales").textContent =
                "₱" + sales.toLocaleString();

            document.getElementById("totalOrders").textContent = orders;

            document.getElementById("avgOrder").textContent =
                "₱" + averageOrder.toLocaleString();

            document.getElementById("totalCommission").textContent =
                "₱" + commission.toLocaleString();

            showNotification(`Report generated: ${from} to ${to}`);
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
