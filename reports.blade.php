
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Reports</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --green: #78a878;
            --green-dark: #527b52;
            --green-light: #edf5ed;
            --black: #1a1714;
            --brown: #6b5f54;
            --accent: #b28b6f;
            --background: #faf7f2;
            --border: #ece4db;
            --white: #ffffff;
            --red: #c0392b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: var(--background);
            font-family: "Inter", "Helvetica Neue", sans-serif;
            color: #1e1e1e;
            line-height: 1.4;
            min-height: 100vh;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font-family: "Inter", sans-serif;
        }

        button {
            cursor: pointer;
        }

        /* ========================================
           STANDARD ZAYLO HEADER
           DO NOT CHANGE THIS DESIGN
        ======================================== */

        .top-line {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 2000;
            width: 100%;
            height: 3px;
            background: var(--green);
        }

        .header {
            position: fixed;
            top: 3px;
            left: 0;
            z-index: 1999;
            width: 100%;
            height: 70px;
            background: #ffffff;
            border-bottom: 1px solid #eeeeeb;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-logo img {
            width: 76px;
            height: auto;
            max-height: 40px;
            object-fit: contain;
        }

        .header-search {
            position: absolute;
            top: 50%;
            right: 78px;
            transform: translateY(-50%);
            width: 360px;
            height: 43px;
            padding: 0 17px;
            display: flex;
            align-items: center;
            gap: 11px;
            background: #f8f7f5;
            border: 1px solid #e2dfd9;
            border-radius: 24px;
        }

        .header-search i {
            color: #252525;
            font-size: 14px;
        }

        .header-search input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            color: #333333;
            font-size: 12px;
        }

        .header-search input::placeholder {
            color: #99938b;
        }

        /* ========================================
           FULL-SCREEN DASHBOARD LAYOUT
        ======================================== */

        .dashboard-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            padding-top: 73px;
            background: var(--background);
        }

        /* ========================================
           SIDEBAR
        ======================================== */

        .sidebar {
            position: fixed;
            top: 73px;
            left: 0;
            z-index: 1000;
            width: 245px;
            height: calc(100vh - 73px);
            padding: 26px 15px;
            overflow-y: auto;
            background: var(--white);
            border-right: 1px solid var(--border);
        }

        .sidebar-title {
            padding: 0 15px;
            margin-bottom: 15px;
            color: #aaa59d;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 15px;
            color: #77736d;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: #f5f7f3;
            color: var(--green-dark);
        }

        .sidebar-menu a.active {
            background: var(--green-light);
            color: var(--green-dark);
            font-weight: 800;
        }

        .sidebar-menu a i {
            width: 17px;
            text-align: center;
            font-size: 14px;
        }

        .sidebar-menu .badge {
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9e9e6;
            color: #74746d;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 800;
        }

        .sidebar-menu a.active .badge {
            background: var(--green);
            color: white;
        }

        .sidebar-divider {
            height: 1px;
            margin: 24px 12px;
            background: var(--border);
        }

        .logout-link {
            color: #b96d6d !important;
        }

        .logout-link:hover {
            background: #fff3f3 !important;
            color: #a94e4e !important;
        }

        /* ========================================
           MAIN CONTENT
        ======================================== */

        .main-content {
            width: calc(100% - 245px);
            min-width: 0;
            margin-left: 245px;
            padding: 38px 42px 55px;
            background: var(--background);
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .page-header h1 {
            color: var(--black);
            font-family: "Playfair Display", serif;
            font-size: 30px;
            font-weight: 600;
            letter-spacing: -0.7px;
        }

        .subtitle {
            margin-top: 6px;
            color: var(--brown);
            font-size: 13px;
        }

        /* ========================================
           DATE FILTER
        ======================================== */

        .date-filter {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            padding: 18px 20px;
            margin-bottom: 24px;
            background: var(--white);
            border: 1px solid var(--border);
        }

        .date-filter label {
            color: var(--brown);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .date-filter input {
            height: 38px;
            padding: 8px 12px;
            background: white;
            border: 1px solid var(--border);
            outline: none;
            color: #1e1e1e;
            font-size: 12px;
        }

        .date-filter input:focus {
            border-color: var(--black);
        }

        .btn-generate,
        .btn-export {
            height: 38px;
            padding: 0 24px;
            border: none;
            color: white;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            transition: 0.2s ease;
        }

        .btn-generate {
            background: var(--black);
        }

        .btn-generate:hover {
            background: var(--accent);
        }

        .btn-export {
            background: #2d7d46;
        }

        .btn-export:hover {
            background: #1e5a34;
        }

        /* ========================================
           REPORT STATISTICS
        ======================================== */

        .report-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .report-stat {
            padding: 20px;
            background: var(--white);
            border: 1px solid var(--border);
        }

        .report-stat .label {
            margin-bottom: 8px;
            color: var(--brown);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .report-stat .number {
            color: var(--black);
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -0.7px;
        }

        .report-stat .change {
            display: block;
            margin-top: 8px;
            font-size: 10px;
        }

        .report-stat .change.up {
            color: #2d7d46;
        }

        .report-stat .change.down {
            color: var(--red);
        }

        /* ========================================
           CHARTS
        ======================================== */

        .chart-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .chart-box {
            padding: 24px;
            background: var(--white);
            border: 1px solid var(--border);
        }

        .chart-box h3 {
            margin-bottom: 20px;
            color: var(--black);
            font-size: 13px;
            font-weight: 800;
        }

        .chart-placeholder {
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            width: 100%;
            height: 210px;
            padding: 28px 20px 25px;
            background: #f5f0ea;
            overflow: hidden;
        }

        .chart-bar {
            position: relative;
            width: 8%;
            min-height: 4px;
            background: var(--black);
            border-radius: 2px 2px 0 0;
            transition: 0.3s ease;
        }

        .chart-bar:hover {
            background: var(--accent) !important;
        }

        .bar-label {
            position: absolute;
            bottom: -23px;
            left: 50%;
            transform: translateX(-50%);
            color: var(--brown);
            font-size: 9px;
            white-space: nowrap;
        }

        .bar-value {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            color: var(--black);
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }

        .category-chart {
            display: flex;
            flex-direction: column;
            justify-content: space-around;
            gap: 10px;
            height: 210px;
            padding: 15px 0;
            background: #f5f0ea;
        }

        .category-row {
            display: grid;
            grid-template-columns: 85px 1fr 50px;
            align-items: center;
            gap: 10px;
            padding: 0 15px;
        }

        .category-name {
            color: var(--brown);
            font-size: 10px;
            font-weight: 600;
        }

        .category-track {
            height: 13px;
            background: #e4dcd3;
            overflow: hidden;
        }

        .category-fill {
            height: 100%;
            background: var(--black);
            transition: width 0.4s ease;
        }

        .category-amount {
            color: var(--black);
            font-size: 9px;
            font-weight: 700;
            text-align: right;
        }

        /* ========================================
           REPORT TABLE
        ======================================== */

        .report-table-wrapper {
            width: 100%;
            background: var(--white);
            border: 1px solid var(--border);
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
            font-size: 12px;
        }

        .report-table th {
            padding: 15px 16px;
            background: #f5f0ea;
            border-bottom: 1px solid var(--border);
            color: var(--brown);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .report-table td {
            padding: 16px;
            border-bottom: 1px solid #f5f0ea;
            color: #514940;
            font-size: 11px;
            white-space: nowrap;
        }

        .report-table tbody tr:hover td {
            background: #faf7f2;
        }

        .report-table tbody tr:last-child td {
            border-bottom: none;
        }

        .report-table td strong {
            color: var(--black);
            font-weight: 800;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 800;
        }

        .status-badge.completed {
            background: #d6e4d0;
            color: #2d7d46;
        }

        .status-badge.pending {
            background: #f5e0c0;
            color: #e67e22;
        }

        .status-badge.cancelled {
            background: #f5e0e0;
            color: #c0392b;
        }

        .empty-row {
            padding: 45px !important;
            color: var(--brown) !important;
            text-align: center;
        }

        .empty-row i {
            display: block;
            margin-bottom: 10px;
            color: var(--accent);
            font-size: 30px;
        }

        /* ========================================
           NOTIFICATION
        ======================================== */

        .zaylo-notification {
            position: fixed;
            right: 25px;
            bottom: 25px;
            z-index: 3000;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 350px;
            padding: 15px 20px;
            background: var(--black);
            color: white;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.18);
            font-size: 12px;
            animation: slideUp 0.3s ease;
        }

        .zaylo-notification i {
            color: #9ed39e;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(25px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ========================================
           RESPONSIVE DESIGN
        ======================================== */

        @media (max-width: 1200px) {
            .main-content {
                padding: 32px 28px 45px;
            }

            .report-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .chart-container {
                grid-template-columns: 1fr;
            }

            .header-search {
                right: 30px;
            }
        }

        @media (max-width: 850px) {
            .sidebar {
                width: 205px;
            }

            .main-content {
                width: calc(100% - 205px);
                margin-left: 205px;
                padding: 28px 20px;
            }

            .header-search {
                width: 280px;
            }
        }

        @media (max-width: 650px) {
            .header {
                height: 62px;
            }

            .header-logo img {
                width: 68px;
            }

            .header-search {
                right: 12px;
                width: 190px;
                height: 38px;
            }

            .header-search input {
                font-size: 10px;
            }

            .dashboard-wrapper {
                display: block;
                padding-top: 65px;
            }

            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                min-height: auto;
                padding: 15px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-title {
                display: none;
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 5px;
            }

            .sidebar-menu li {
                margin-bottom: 0;
            }

            .sidebar-menu a {
                padding: 11px;
                font-size: 11px;
            }

            .sidebar-divider {
                margin: 15px 5px;
            }

            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 25px 15px 35px;
            }

            .page-header {
                align-items: flex-start;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .date-filter {
                flex-direction: column;
                align-items: stretch;
            }

            .date-filter input,
            .btn-generate,
            .btn-export {
                width: 100%;
            }

            .report-stats {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .report-stat {
                padding: 15px;
            }

            .report-stat .number {
                font-size: 20px;
            }

            .chart-box {
                padding: 18px;
            }

            .chart-placeholder {
                height: 170px;
                padding: 28px 10px 25px;
            }

            .category-chart {
                height: auto;
                min-height: 210px;
            }

            .category-row {
                grid-template-columns: 75px 1fr 45px;
                gap: 7px;
                padding: 0 12px;
            }
        }

        @media (max-width: 400px) {
            .header-search {
                width: 155px;
            }

            .report-stats {
                grid-template-columns: 1fr;
            }

            .sidebar-menu {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

    <!-- ========================================
         STANDARD ZAYLO HEADER
    ======================================== -->

    <div class="top-line"></div>

    <header class="header">

        <a href="{{ url('/admin/dashboard') }}" class="header-logo">
            <img src="{{ asset('images/logo.png') }}" alt="ZAYLO Logo">
        </a>

        <div class="header-search">
            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                id="headerSearch"
                placeholder="Search dashboard..."
                autocomplete="off"
                oninput="searchDashboard(this.value)"
            >
        </div>

    </header>

    <!-- ========================================
         DASHBOARD WRAPPER
    ======================================== -->

    <div class="dashboard-wrapper">

        <!-- SIDEBAR -->

        <aside class="sidebar">

            <div class="sidebar-title">Main Menu</div>

            <ul class="sidebar-menu">

                <li>
                    <a href="{{ url('/admin/dashboard') }}">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/registrations') }}">
                        <i class="fa-solid fa-user-plus"></i>
                        <span>Registrations</span>
                        <span class="badge">5</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/users') }}">
                        <i class="fa-solid fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/compliance') }}">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Compliance</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/disputes') }}">
                        <i class="fa-solid fa-scale-balanced"></i>
                        <span>Disputes</span>
                        <span class="badge">2</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/commissions') }}">
                        <i class="fa-solid fa-coins"></i>
                        <span>Commissions</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/reports') }}" class="active">
                        <i class="fa-solid fa-chart-column"></i>
                        <span>Reports</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/settings') }}">
                        <i class="fa-solid fa-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/chat') }}">
                        <i class="fa-regular fa-comments"></i>
                        <span>Messages</span>
                        <span class="badge">3</span>
                    </a>
                </li>

                <li>
                    <a href="{{ url('/admin/account') }}">
                        <i class="fa-regular fa-user"></i>
                        <span>Account</span>
                    </a>
                </li>

                <div class="sidebar-divider"></div>

                <li>
                    <a href="{{ route('login') }}" class="logout-link">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </li>

            </ul>

        </aside>

        <!-- MAIN CONTENT -->

        <main class="main-content">

            <!-- PAGE HEADER -->

            <div class="page-header">

                <div>
                    <h1>Reports</h1>
                    <p class="subtitle">
                        Generate and view platform reports
                    </p>
                </div>

            </div>

            <!-- DATE FILTER -->

            <div class="date-filter">

                <label for="dateFrom">From</label>

                <input
                    type="date"
                    id="dateFrom"
                    value="{{ request('from') ?: '2026-09-01' }}"
                >

                <label for="dateTo">To</label>

                <input
                    type="date"
                    id="dateTo"
                    value="{{ request('to') ?: '2026-09-30' }}"
                >

                <button
                    type="button"
                    class="btn-generate"
                    onclick="generateReport()"
                >
                    Generate Report
                </button>

                <button
                    type="button"
                    class="btn-export"
                    onclick="exportPdfReport()"
                >
                    <i class="fa-solid fa-file-pdf"></i>
                    Download PDF
                </button>

            </div>

            <!-- REPORT STATISTICS -->

            <div class="report-stats">

                <div class="report-stat">

                    <div class="label">Total Sales</div>

                    <div class="number" id="totalSales">
                        ₱0.00
                    </div>

                    <span class="change up">
                        ↑ 12% from last month
                    </span>

                </div>

                <div class="report-stat">

                    <div class="label">Total Orders</div>

                    <div class="number" id="totalOrders">
                        0
                    </div>

                    <span class="change up">
                        ↑ 8% from last month
                    </span>

                </div>

                <div class="report-stat">

                    <div class="label">Total Commission</div>

                    <div class="number" id="totalCommission">
                        ₱0.00
                    </div>

                    <span class="change up">
                        ↑ 10% from last month
                    </span>

                </div>

                <div class="report-stat">

                    <div class="label">Active Sellers</div>

                    <div class="number" id="activeSellers">
                        0
                    </div>

                    <span class="change up">
                        ↑ 5 new this month
                    </span>

                </div>

            </div>

            <!-- CHARTS -->

            <div class="chart-container">

                <div class="chart-box">

                    <h3>Daily Sales</h3>

                    <div
                        class="chart-placeholder"
                        id="salesChart"
                    ></div>

                </div>

                <div class="chart-box">

                    <h3>Commission by Category</h3>

                    <div
                        class="category-chart"
                        id="categoryChart"
                    ></div>

                </div>

            </div>

            <!-- REPORT TABLE -->

            <div class="report-table-wrapper">

                <table class="report-table">

                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Seller</th>
                            <th>Amount</th>
                            <th>Commission</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody id="reportBody"></tbody>

                </table>

            </div>

        </main>

    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        /* ========================================
           REPORT DATA
        ======================================== */

        const reportData = [
            {
                order: "ZYL-2026-001",
                customer: "Juan Dela Cruz",
                seller: "ZAYLO Fashion Hub",
                category: "Clothing",
                amount: 4250,
                commission: 425,
                status: "completed",
                date: "2026-09-15"
            },
            {
                order: "ZYL-2026-002",
                customer: "Maria Reyes",
                seller: "Luxury Bags Co.",
                category: "Bags",
                amount: 3750,
                commission: 375,
                status: "completed",
                date: "2026-09-14"
            },
            {
                order: "ZYL-2026-003",
                customer: "Pedro Santos",
                seller: "Urban Wear PH",
                category: "Clothing",
                amount: 6670,
                commission: 667,
                status: "pending",
                date: "2026-09-13"
            },
            {
                order: "ZYL-2026-004",
                customer: "Ana Lopez",
                seller: "Elegant Accessories",
                category: "Accessories",
                amount: 3800,
                commission: 380,
                status: "completed",
                date: "2026-09-12"
            },
            {
                order: "ZYL-2026-005",
                customer: "Carlos Garcia",
                seller: "Footwear Plus",
                category: "Shoes",
                amount: 5200,
                commission: 520,
                status: "completed",
                date: "2026-09-11"
            },
            {
                order: "ZYL-2026-006",
                customer: "Rosa Santos",
                seller: "Watch Emporium",
                category: "Watches",
                amount: 7000,
                commission: 700,
                status: "pending",
                date: "2026-09-10"
            },
            {
                order: "ZYL-2026-007",
                customer: "Luis Martinez",
                seller: "ZAYLO Fashion Hub",
                category: "Clothing",
                amount: 3100,
                commission: 310,
                status: "completed",
                date: "2026-09-09"
            },
            {
                order: "ZYL-2026-008",
                customer: "Elena Cruz",
                seller: "Luxury Bags Co.",
                category: "Bags",
                amount: 5600,
                commission: 560,
                status: "completed",
                date: "2026-09-08"
            },
            {
                order: "ZYL-2026-009",
                customer: "Mark Reyes",
                seller: "Urban Wear PH",
                category: "Clothing",
                amount: 4200,
                commission: 420,
                status: "cancelled",
                date: "2026-09-07"
            },
            {
                order: "ZYL-2026-010",
                customer: "Sofia Garcia",
                seller: "Elegant Accessories",
                category: "Accessories",
                amount: 2500,
                commission: 250,
                status: "completed",
                date: "2026-09-06"
            }
        ];

        let filteredReport = [...reportData];

        /* ========================================
           HELPERS
        ======================================== */

        function formatCurrency(amount) {
            return new Intl.NumberFormat("en-PH", {
                style: "currency",
                currency: "PHP",
                minimumFractionDigits: 2
            }).format(amount);
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        const statusMap = {
            completed: {
                label: "Completed",
                className: "completed"
            },
            pending: {
                label: "Pending",
                className: "pending"
            },
            cancelled: {
                label: "Cancelled",
                className: "cancelled"
            }
        };

        /* ========================================
           RENDER REPORT TABLE
        ======================================== */

        function renderReportTable() {
            const tbody = document.getElementById("reportBody");

            if (filteredReport.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="empty-row">
                            <i class="fa-solid fa-file-circle-xmark"></i>
                            No data found for the selected period.
                        </td>
                    </tr>
                `;

                return;
            }

            tbody.innerHTML = filteredReport.map((item, index) => {
                const statusInfo =
                    statusMap[item.status] || statusMap.pending;

                return `
                    <tr style="
                        animation: fadeInUp 0.3s ease forwards;
                        animation-delay: ${index * 0.05}s;
                        opacity: 0;
                    ">
                        <td>
                            <strong>${escapeHtml(item.order)}</strong>
                        </td>

                        <td>
                            ${escapeHtml(item.customer)}
                        </td>

                        <td>
                            ${escapeHtml(item.seller)}
                        </td>

                        <td>
                            ${formatCurrency(item.amount)}
                        </td>

                        <td>
                            ${formatCurrency(item.commission)}
                        </td>

                        <td>
                            <span class="status-badge ${statusInfo.className}">
                                ${statusInfo.label}
                            </span>
                        </td>

                        <td>
                            ${formatDate(item.date)}
                        </td>
                    </tr>
                `;
            }).join("");
        }

        function formatDate(dateString) {
            const date = new Date(`${dateString}T00:00:00`);

            return date.toLocaleDateString("en-PH", {
                month: "short",
                day: "numeric",
                year: "numeric"
            });
        }

        /* ========================================
           RENDER SALES CHART
        ======================================== */

        function renderSalesChart() {
            const container = document.getElementById("salesChart");

            const dailySales = [
                { day: "Mon", amount: 5200 },
                { day: "Tue", amount: 3800 },
                { day: "Wed", amount: 7200 },
                { day: "Thu", amount: 4500 },
                { day: "Fri", amount: 8900 },
                { day: "Sat", amount: 10500 },
                { day: "Sun", amount: 6200 }
            ];

            const maxAmount = Math.max(
                ...dailySales.map(item => item.amount)
            );

            container.innerHTML = dailySales.map((item, index) => {
                const height = (item.amount / maxAmount) * 140;

                return `
                    <div
                        class="chart-bar"
                        style="
                            height: ${height}px;
                            animation: fadeInUp 0.4s ease forwards;
                            animation-delay: ${index * 0.05}s;
                        "
                    >
                        <span class="bar-value">
                            ₱${(item.amount / 1000).toFixed(1)}k
                        </span>

                        <span class="bar-label">
                            ${item.day}
                        </span>
                    </div>
                `;
            }).join("");
        }

        /* ========================================
           RENDER CATEGORY CHART
        ======================================== */

        function renderCategoryChart() {
            const container = document.getElementById("categoryChart");

            const categoryData = [
                {
                    category: "Clothing",
                    amount: 18500
                },
                {
                    category: "Bags",
                    amount: 14200
                },
                {
                    category: "Shoes",
                    amount: 9800
                },
                {
                    category: "Watches",
                    amount: 5200
                },
                {
                    category: "Accessories",
                    amount: 4550
                }
            ];

            const maxAmount = Math.max(
                ...categoryData.map(item => item.amount)
            );

            container.innerHTML = categoryData.map(item => {
                const width = (item.amount / maxAmount) * 100;

                return `
                    <div class="category-row">

                        <span class="category-name">
                            ${escapeHtml(item.category)}
                        </span>

                        <div class="category-track">
                            <div
                                class="category-fill"
                                style="width: ${width}%"
                            ></div>
                        </div>

                        <span class="category-amount">
                            ₱${(item.amount / 1000).toFixed(1)}k
                        </span>

                    </div>
                `;
            }).join("");
        }

        /* ========================================
           GENERATE REPORT
        ======================================== */

        function generateReport() {
            const from = document.getElementById("dateFrom").value;
            const to = document.getElementById("dateTo").value;

            if (!from || !to) {
                showNotification(
                    "Please select both date ranges.",
                    "error"
                );

                return;
            }

            if (from > to) {
                showNotification(
                    "The starting date cannot be after the ending date.",
                    "error"
                );

                return;
            }

            filteredReport = reportData.filter(item => {
                return item.date >= from && item.date <= to;
            });

            const totalSales = filteredReport.reduce(
                (sum, item) => sum + item.amount,
                0
            );

            const totalCommission = filteredReport.reduce(
                (sum, item) => sum + item.commission,
                0
            );

            const totalOrders = filteredReport.length;

            const activeSellers = new Set(
                filteredReport
                    .filter(item => item.status !== "cancelled")
                    .map(item => item.seller)
            ).size;

            document.getElementById("totalSales").textContent =
                formatCurrency(totalSales);

            document.getElementById("totalCommission").textContent =
                formatCurrency(totalCommission);

            document.getElementById("totalOrders").textContent =
                totalOrders;

            document.getElementById("activeSellers").textContent =
                activeSellers;

            renderReportTable();
            renderSalesChart();
            renderCategoryChart();

            showNotification(
                "Report generated successfully.",
                "success"
            );
        }

        /* ========================================
           HEADER SEARCH
        ======================================== */

        function exportPdfReport() {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                showNotification("PDF export is unavailable. Please try again.", "error");
                return;
            }
            const from = document.getElementById("dateFrom").value;
            const to = document.getElementById("dateTo").value;
            if (!from || !to || from > to) {
                showNotification("Select a valid report date range before downloading.", "error");
                return;
            }
            const completed = reportData.filter(item => item.date >= from && item.date <= to && item.status === "completed");
            if (!completed.length) {
                showNotification("There are no completed sales to export for this date range.", "error");
                return;
            }

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: "portrait", unit: "pt", format: "a4" });
            const pw = doc.internal.pageSize.getWidth();
            const ph = doc.internal.pageSize.getHeight();
            const m = 36;
            const cw = pw - 2 * m;
            const money = n => `PHP ${Number(n).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            const sales = completed.reduce((sum, item) => sum + item.amount, 0);
            const commission = completed.reduce((sum, item) => sum + item.commission, 0);
            const sellerMap = new Map();
            completed.forEach(item => {
                const row = sellerMap.get(item.seller) || { name: item.seller, orders: 0, sales: 0, commission: 0 };
                row.orders++;
                row.sales += item.amount;
                row.commission += item.commission;
                sellerMap.set(item.seller, row);
            });
            const sellers = [...sellerMap.values()].sort((a, b) => b.sales - a.sales);

            doc.setFillColor(245, 240, 234); doc.rect(0, 0, pw, 44, "F");
            doc.setFont("helvetica", "bold"); doc.setFontSize(8); doc.setTextColor(105, 91, 78);
            doc.text("ADMIN REPORT", m, 27);
            const logo = document.querySelector(".header-logo img");
            if (logo && logo.complete && logo.naturalWidth > 0) {
                doc.addImage(logo, "PNG", pw / 2 - 31, 12, 62, 21);
            } else {
                doc.setFontSize(20); doc.setTextColor(22, 21, 19);
                doc.text("ZAYLO", pw / 2, 29, { align: "center" });
            }
            doc.setFont("times", "bold"); doc.setFontSize(21); doc.setTextColor(28, 27, 25);
            doc.text("Sales Summary Report", m, 72);
            doc.setFont("helvetica", "normal"); doc.setFontSize(8); doc.setTextColor(105, 91, 78);
            doc.text(`Period: ${formatDate(from)} to ${formatDate(to)}`, m, 91);
            const generated = new Date().toLocaleString("en-PH", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
            doc.text(`Generated: ${generated}`, pw - m, 91, { align: "right" });

            const cards = [
                ["GROSS SALES", money(sales), "Selected period"],
                ["COMPLETED ORDERS", String(completed.length), "Selected period"],
                ["AVERAGE ORDER", money(sales / completed.length), "Per completed order"],
                ["COMMISSION RATE 10%", money(commission), "Platform share"]
            ];
            const gap = 9, cardY = 106, cardH = 76, cardW = (cw - gap * 3) / 4;
            cards.forEach((card, i) => {
                const x = m + i * (cardW + gap);
                doc.setFillColor(248, 244, 239); doc.setDrawColor(231, 222, 213);
                doc.rect(x, cardY, cardW, cardH, "FD");
                doc.setFont("helvetica", "bold"); doc.setFontSize(6); doc.setTextColor(105, 91, 78);
                doc.text(card[0], x + 8, cardY + 16);
                doc.setFontSize(8); doc.setTextColor(28, 27, 25);
                doc.text(card[1], x + 8, cardY + 38, { maxWidth: cardW - 16 });
                doc.setFont("helvetica", "normal"); doc.setFontSize(6); doc.setTextColor(145, 133, 121);
                doc.text(card[2], x + 8, cardY + 57);
            });

            let y = 211;
            doc.setFont("times", "bold"); doc.setFontSize(12); doc.setTextColor(28, 27, 25);
            doc.text("Sales by seller", m, y);
            doc.setFont("helvetica", "normal"); doc.setFontSize(7); doc.setTextColor(105, 91, 78);
            doc.text("Gross sales by seller, highest first", m, y + 12);
            const chartSellers = sellers.slice(0, 5);
            const chartY = y + 22, chartH = Math.max(86, chartSellers.length * 21 + 18);
            doc.setDrawColor(231, 222, 213); doc.rect(m, chartY, cw, chartH);
            const maxSales = Math.max(...chartSellers.map(s => s.sales), 1);
            const labelW = 118, barX = m + labelW + 8, barW = cw - labelW - 65;
            chartSellers.forEach((seller, i) => {
                const ry = chartY + 17 + i * 21;
                doc.setFont("helvetica", "normal"); doc.setFontSize(7); doc.setTextColor(62, 55, 49);
                doc.text(doc.splitTextToSize(seller.name, labelW - 8)[0] || "", m + 8, ry + 6);
                doc.setFillColor(241, 236, 230); doc.rect(barX, ry, barW, 9, "F");
                doc.setFillColor(83, 112, 89); doc.rect(barX, ry, Math.max(2, barW * seller.sales / maxSales), 9, "F");
                doc.setFont("helvetica", "bold"); doc.setFontSize(7); doc.setTextColor(35, 32, 29);
                doc.text(money(seller.sales), pw - m - 7, ry + 7, { align: "right" });
            });

            y = chartY + chartH + 25;
            const drawTableHeader = () => {
                doc.setFillColor(233, 228, 216); doc.rect(m, y, cw, 20, "F");
                doc.setFont("helvetica", "bold"); doc.setFontSize(7); doc.setTextColor(81, 70, 63);
                doc.text("SELLER", m + 8, y + 13); doc.text("ORDERS", m + 245, y + 13);
                doc.text("GROSS SALES", m + 310, y + 13);
                doc.text("COMMISSION", pw - m - 8, y + 13, { align: "right" });
                y += 20;
            };
            doc.setFont("times", "bold"); doc.setFontSize(12); doc.setTextColor(28, 27, 25);
            doc.text("Sales by seller", m, y); y += 7;
            doc.setFont("helvetica", "normal"); doc.setFontSize(7); doc.setTextColor(105, 91, 78);
            doc.text("Completed orders. Philippine pesos (PHP).", m, y + 7); y += 12;
            drawTableHeader();
            sellers.forEach((seller, i) => {
                if (y + 22 > ph - 56) {
                    doc.addPage(); y = 42;
                    doc.setFont("times", "bold"); doc.setFontSize(15); doc.setTextColor(28, 27, 25);
                    doc.text("Sales by seller (continued)", m, y); y += 12; drawTableHeader();
                }
                if (i % 2) { doc.setFillColor(250, 248, 245); doc.rect(m, y, cw, 22, "F"); }
                doc.setFont("helvetica", "normal"); doc.setFontSize(7); doc.setTextColor(35, 32, 29);
                doc.text(doc.splitTextToSize(seller.name, 220)[0] || "", m + 8, y + 14);
                doc.text(String(seller.orders), m + 255, y + 14);
                doc.text(money(seller.sales), m + 310, y + 14);
                doc.text(money(seller.commission), pw - m - 8, y + 14, { align: "right" });
                doc.setDrawColor(235, 229, 222); doc.line(m, y + 22, pw - m, y + 22); y += 22;
            });

            const pages = doc.internal.getNumberOfPages();
            for (let page = 1; page <= pages; page++) {
                doc.setPage(page); doc.setDrawColor(220, 211, 201); doc.line(m, ph - 43, pw - m, ph - 43);
                doc.setFont("helvetica", "normal"); doc.setFontSize(6); doc.setTextColor(120, 108, 97);
                doc.text("Commission is calculated at 10% of completed order sales. Figures reflect sample data until connected to live sales and payment records.", m, ph - 29, { maxWidth: cw - 10 });
                doc.text("Generated by: Administrator", m, ph - 12);
                doc.text(`Sales Summary Report  |  Page ${page} of ${pages}`, pw - m, ph - 12, { align: "right" });
            }
            doc.save(`zaylo-sales-summary-${from}-to-${to}.pdf`);
            showNotification("Sales summary PDF downloaded successfully.", "success");
        }

        function searchDashboard(value) {
            const searchValue = value.toLowerCase().trim();

            if (searchValue === "") {
                filteredReport = [...reportData];
            } else {
                filteredReport = reportData.filter(item => {
                    const searchableText = [
                        item.order,
                        item.customer,
                        item.seller,
                        item.category,
                        item.status,
                        item.date
                    ].join(" ").toLowerCase();

                    return searchableText.includes(searchValue);
                });
            }

            renderReportTable();
        }

        /* ========================================
           NOTIFICATION SYSTEM
        ======================================== */

        function showNotification(message, type = "success") {
            const existing =
                document.querySelector(".zaylo-notification");

            if (existing) {
                existing.remove();
            }

            const notification = document.createElement("div");

            notification.className = "zaylo-notification";

            const icon =
                type === "error"
                    ? "fa-circle-exclamation"
                    : "fa-circle-check";

            notification.innerHTML = `
                <i class="fa-solid ${icon}"></i>
                <span>${escapeHtml(message)}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3500);
        }

        /* ========================================
           INITIALIZE
        ======================================== */

        document.addEventListener("DOMContentLoaded", () => {
            generateReport();
        });
    </script>

</body>

</html>
