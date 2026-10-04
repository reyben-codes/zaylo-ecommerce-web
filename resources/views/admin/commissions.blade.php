
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Commissions Management</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --accent: #c9824a;
            --accent-dark: #9f6035;
            --accent-light: #fff1e6;
            --accent-border: #efd4bf;

            --black: #171717;
            --dark: #292522;
            --gray: #77716a;
            --muted: #99928a;
            --light-gray: #f8f6f2;
            --border: #e8e2da;
            --white: #ffffff;

            --red: #b85b5b;
            --orange: #c9824a;
            --blue: #6485b2;

            --shadow: 0 8px 24px rgba(50, 35, 20, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: #faf9f6;
            color: var(--black);
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button,
        input,
        select {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        /* ========================================
           STANDARD ZAYLO HEADER
        ======================================== */

        .top-line {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1001;

            width: 100%;
            height: 3px;

            background: var(--accent);
        }

        .header {
            position: fixed;
            top: 3px;
            left: 0;
            z-index: 1000;

            width: 100%;
            height: 70px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: var(--white);
            border-bottom: 1px solid #eeeae4;
        }

        .header-logo {
            position: absolute;
            top: 50%;
            left: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            transform: translate(-50%, -50%);
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

            width: 360px;
            height: 43px;

            display: flex;
            align-items: center;
            gap: 11px;

            padding: 0 17px;

            background: #f8f6f2;
            border: 1px solid #e4ddd5;
            border-radius: 24px;

            transform: translateY(-50%);
        }

        .header-search i {
            color: #292522;
            font-size: 14px;
        }

        .header-search input {
            width: 100%;

            border: none;
            outline: none;

            background: transparent;
            color: #33302c;
            font-size: 12px;
        }

        .header-search input::placeholder {
            color: #99928a;
        }

        /* ========================================
           LAYOUT
        ======================================== */

        .layout {
            display: flex;
            min-height: 100vh;
            padding-top: 73px;
        }

        .sidebar {
            position: fixed;
            top: 73px;
            left: 0;

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

            color: #aaa39a;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 13px;

            padding: 12px 15px;

            color: #77716a;
            font-size: 12px;
            font-weight: 600;

            border-radius: 10px;
            transition: 0.2s ease;
        }

        .nav-link i {
            width: 17px;
            text-align: center;
            font-size: 14px;
        }

        .nav-link:hover {
            color: var(--accent-dark);
            background: #fff7f0;
        }

        .nav-link.active {
            color: var(--accent-dark);
            background: var(--accent-light);
            font-weight: 800;
        }

        .nav-badge {
            min-width: 20px;
            height: 20px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin-left: auto;
            padding: 0 6px;

            color: #74706a;
            background: #eeece8;

            border-radius: 20px;

            font-size: 10px;
            font-weight: 800;
        }

        .nav-link.active .nav-badge {
            color: white;
            background: var(--accent);
        }

        .sidebar-divider {
            height: 1px;
            margin: 24px 12px;
            background: var(--border);
        }

        .logout-link {
            color: #b96d6d;
        }

        .logout-link:hover {
            color: #a94e4e;
            background: #fff3f3;
        }

        /* ========================================
           MAIN CONTENT
        ======================================== */

        .main-content {
            width: calc(100% - 245px);
            margin-left: 245px;
            padding: 38px 42px 55px;
        }

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;

            margin-bottom: 30px;
        }

        .page-title {
            margin-bottom: 9px;

            color: var(--black);
            font-size: 27px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .page-subtitle {
            color: #858078;
            font-size: 13px;
        }

        .commission-rate {
            padding: 10px 15px;

            color: var(--accent-dark);
            background: var(--accent-light);
            border: 1px solid var(--accent-border);
            border-radius: 10px;

            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .commission-rate i {
            margin-right: 5px;
        }

        /* ========================================
           STATISTICS
        ======================================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;

            margin-bottom: 28px;
        }

        .stat-card {
            padding: 22px;

            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;

            margin-bottom: 19px;
        }

        .stat-label {
            color: #8d877f;
            font-size: 11px;
            font-weight: 700;
        }

        .stat-icon {
            width: 35px;
            height: 35px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 10px;
            font-size: 14px;
        }

        .stat-icon.accent {
            color: var(--accent-dark);
            background: var(--accent-light);
        }

        .stat-icon.blue {
            color: var(--blue);
            background: #edf3fb;
        }

        .stat-icon.orange {
            color: #b77934;
            background: #fff5e8;
        }

        .stat-icon.red {
            color: var(--red);
            background: #fff0f0;
        }

        .stat-value {
            margin-bottom: 8px;

            color: var(--dark);
            font-size: 25px;
            font-weight: 800;
            letter-spacing: -0.8px;
        }

        .stat-note {
            color: #99928a;
            font-size: 10px;
        }

        /* ========================================
           FILTERS
        ======================================== */

        .filter-card {
            padding: 20px;
            margin-bottom: 22px;

            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .input-wrapper {
            position: relative;
            flex: 1;
            min-width: 220px;
        }

        .input-wrapper i {
            position: absolute;
            top: 50%;
            left: 14px;

            color: #aaa39a;
            font-size: 13px;

            transform: translateY(-50%);
        }

        .filter-input,
        .filter-select {
            height: 43px;

            color: #44403b;
            background: #fcfbf9;
            border: 1px solid #e4ded6;
            border-radius: 9px;
            outline: none;

            font-size: 12px;
        }

        .filter-input {
            width: 100%;
            padding: 0 13px 0 38px;
        }

        .filter-select {
            min-width: 170px;
            padding: 0 13px;
        }

        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(201, 130, 74, 0.12);
        }

        .btn {
            height: 43px;

            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            padding: 0 18px;

            border: none;
            border-radius: 9px;

            font-size: 12px;
            font-weight: 800;
            transition: 0.2s ease;
        }

        .btn-primary {
            color: white;
            background: var(--accent);
        }

        .btn-primary:hover {
            background: var(--accent-dark);
        }

        .btn-secondary {
            color: #77716a;
            background: #f2efeb;
        }

        .btn-secondary:hover {
            background: #e7e2dc;
        }

        /* ========================================
           TABLE
        ======================================== */

        .table-card {
            overflow: hidden;

            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .table-header {
            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 23px 25px;

            border-bottom: 1px solid var(--border);
        }

        .table-title {
            color: var(--dark);
            font-size: 15px;
            font-weight: 800;
        }

        .table-count {
            color: #99928a;
            font-size: 11px;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        .commission-table {
            width: 100%;
            min-width: 920px;
            border-collapse: collapse;
        }

        .commission-table th {
            padding: 15px 20px;

            color: #99928a;
            background: #faf9f6;
            border-bottom: 1px solid var(--border);

            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-align: left;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .commission-table td {
            padding: 18px 20px;

            color: #5e5952;
            border-bottom: 1px solid #f0ede8;

            font-size: 12px;
            vertical-align: middle;
        }

        .commission-table tbody tr:hover {
            background: #fffdf9;
        }

        .commission-table tbody tr:last-child td {
            border-bottom: none;
        }

        .transaction-id {
            color: #37322d;
            font-size: 11px;
            font-weight: 800;
        }

        .seller-name {
            margin-bottom: 4px;

            color: #33302c;
            font-weight: 700;
        }

        .seller-email {
            color: #aaa39a;
            font-size: 10px;
        }

        .order-id {
            color: var(--accent-dark);
            font-size: 11px;
            font-weight: 800;
        }

        .amount {
            color: #38342f;
            font-weight: 700;
        }

        .commission-amount {
            color: var(--accent-dark);
            font-weight: 800;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status::before {
            content: "";

            width: 5px;
            height: 5px;

            background: currentColor;
            border-radius: 50%;
        }

        .status.paid {
            color: #54885d;
            background: #edf7ee;
        }

        .status.pending {
            color: #b07c36;
            background: #fff5e6;
        }

        .status.overdue {
            color: #b85a5a;
            background: #fff0f0;
        }

        .status.cancelled {
            color: #88847d;
            background: #f0efed;
        }

        .actions {
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .action-btn {
            width: 31px;
            height: 31px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            color: #888078;
            background: white;
            border: 1px solid #e8e2da;
            border-radius: 8px;

            font-size: 11px;
            transition: 0.2s ease;
        }

        .action-btn:hover {
            color: var(--accent-dark);
            background: var(--accent-light);
            border-color: var(--accent-border);
        }

        .action-btn.pay:hover {
            color: #54885d;
            background: #edf7ee;
            border-color: #cde5d0;
        }

        .empty-state {
            padding: 55px 20px;

            color: #99928a;
            text-align: center;
        }

        .empty-state i {
            margin-bottom: 15px;

            color: #d4cec6;
            font-size: 30px;
        }

        .empty-state p {
            font-size: 12px;
        }

        /* ========================================
           PAGINATION
        ======================================== */

        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            padding: 18px 23px;

            border-top: 1px solid var(--border);
        }

        .pagination-info {
            color: #99928a;
            font-size: 11px;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .page-btn {
            min-width: 30px;
            height: 30px;

            color: #858078;
            background: white;
            border: 1px solid #e8e2da;
            border-radius: 8px;

            font-size: 11px;
            font-weight: 700;
        }

        .page-btn:hover:not(:disabled),
        .page-btn.active {
            color: white;
            background: var(--accent);
            border-color: var(--accent);
        }

        .page-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* ========================================
           NOTIFICATION
        ======================================== */

        .notification {
            position: fixed;
            right: 25px;
            bottom: 25px;
            z-index: 2000;

            display: flex;
            align-items: center;
            gap: 10px;

            padding: 15px 20px;

            color: white;
            background: #292522;
            border-radius: 10px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.2);

            font-size: 12px;
            animation: slideIn 0.25s ease;
        }

        .notification i {
            color: #e8b58c;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(15px);
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
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .main-content {
                padding: 32px 25px 45px;
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

            .layout {
                display: block;
                padding-top: 65px;
            }

            .sidebar {
                position: static;

                width: 100%;
                height: auto;

                padding: 15px;

                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-nav {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
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
                flex-direction: column;
            }

            .page-title {
                font-size: 23px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-value {
                font-size: 20px;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .input-wrapper {
                width: 100%;
            }

            .filter-select,
            .filter-form .btn {
                width: 100%;
            }

            .table-header,
            .table-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .pagination {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 400px) {
            .header-search {
                width: 155px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .sidebar-nav {
                grid-template-columns: 1fr;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>

    <!-- STANDARD ZAYLO HEADER -->

    <div class="top-line"></div>

    @include('admin.partials.header', ['searchId' => 'headerSearch', 'searchPlaceholder' => 'Search dashboard...', 'searchOnInput' => 'syncHeaderSearch(this.value)'])

    <div class="layout">

        <!-- SIDEBAR -->

        @include('admin.partials.sidebar')

        <!-- MAIN CONTENT -->

        <main class="main-content">

            <div class="page-header">

                <div>
                    <h1 class="page-title">Commissions Management</h1>

                    <p class="page-subtitle">
                        Track and manage platform commissions from seller transactions.
                    </p>
                </div>

                <div class="commission-rate">
                    <i class="fa-solid fa-percent"></i>
                    Platform Commission: 10%
                </div>

            </div>

            <!-- STATISTICS -->

            <section class="stats-grid">

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Total Commission</span>

                        <div class="stat-icon accent">
                            <i class="fa-solid fa-coins"></i>
                        </div>
                    </div>

                    <div class="stat-value">₱48,250.00</div>
                    <div class="stat-note">Total recorded commission</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Paid Commission</span>

                        <div class="stat-icon blue">
                            <i class="fa-solid fa-check-double"></i>
                        </div>
                    </div>

                    <div class="stat-value">₱32,400.00</div>
                    <div class="stat-note">Successfully collected</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Pending Commission</span>

                        <div class="stat-icon orange">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                    </div>

                    <div class="stat-value">₱12,850.00</div>
                    <div class="stat-note">Awaiting payment</div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <span class="stat-label">Overdue Commission</span>

                        <div class="stat-icon red">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    </div>

                    <div class="stat-value">₱3,000.00</div>
                    <div class="stat-note">Requires attention</div>
                </div>

            </section>

            <!-- FILTERS -->

            <section class="filter-card">

                <div class="filter-form">

                    <div class="input-wrapper">
                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="searchCommission"
                            class="filter-input"
                            placeholder="Search seller, order, or transaction..."
                            oninput="filterCommissions()"
                        >
                    </div>

                    <select
                        id="statusFilter"
                        class="filter-select"
                        onchange="filterCommissions()"
                    >
                        <option value="">All Statuses</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="overdue">Overdue</option>
                        <option value="cancelled">Cancelled</option>
                    </select>

                    <button class="btn btn-primary" onclick="filterCommissions()">
                        <i class="fa-solid fa-filter"></i>
                        Apply
                    </button>

                    <button class="btn btn-secondary" onclick="clearFilters()">
                        <i class="fa-solid fa-rotate-left"></i>
                        Clear
                    </button>

                </div>

            </section>

            <!-- COMMISSION TABLE -->

            <section class="table-card">

                <div class="table-header">
                    <h2 class="table-title">Commission Records</h2>

                    <span class="table-count" id="tableCount">
                        0 records
                    </span>
                </div>

                <div class="table-scroll">

                    <table class="commission-table">

                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Seller</th>
                                <th>Order ID</th>
                                <th>Order Amount</th>
                                <th>Commission</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody id="commissionTableBody"></tbody>

                    </table>

                    <div class="empty-state" id="emptyState" style="display: none;">
                        <i class="fa-solid fa-folder-open"></i>
                        <p>No commission records found.</p>
                    </div>

                </div>

                <div class="table-footer">

                    <div class="pagination-info" id="paginationInfo">
                        Showing 0 of 0 records
                    </div>

                    <div class="pagination" id="pagination"></div>

                </div>

            </section>

        </main>

    </div>

    <script>
        const commissionsData = [
            {
                id: 1,
                transaction: "COM-2026-001",
                seller: "ZAYLO Fashion Hub",
                email: "fashionhub@example.com",
                order: "ZYL-2026-001",
                amount: 4500,
                commission: 450,
                status: "paid"
            },
            {
                id: 2,
                transaction: "COM-2026-002",
                seller: "Urban Finds",
                email: "urbanfinds@example.com",
                order: "ZYL-2026-002",
                amount: 7800,
                commission: 780,
                status: "pending"
            },
            {
                id: 3,
                transaction: "COM-2026-003",
                seller: "Home Essentials",
                email: "homeessentials@example.com",
                order: "ZYL-2026-003",
                amount: 3200,
                commission: 320,
                status: "paid"
            },
            {
                id: 4,
                transaction: "COM-2026-004",
                seller: "Modern Living",
                email: "modernliving@example.com",
                order: "ZYL-2026-004",
                amount: 9500,
                commission: 950,
                status: "overdue"
            },
            {
                id: 5,
                transaction: "COM-2026-005",
                seller: "Daily Essentials",
                email: "dailyessentials@example.com",
                order: "ZYL-2026-005",
                amount: 2100,
                commission: 210,
                status: "paid"
            },
            {
                id: 6,
                transaction: "COM-2026-006",
                seller: "The Style Corner",
                email: "stylecorner@example.com",
                order: "ZYL-2026-006",
                amount: 6800,
                commission: 680,
                status: "pending"
            },
            {
                id: 7,
                transaction: "COM-2026-007",
                seller: "Casa Living",
                email: "casaliving@example.com",
                order: "ZYL-2026-007",
                amount: 5200,
                commission: 520,
                status: "paid"
            },
            {
                id: 8,
                transaction: "COM-2026-008",
                seller: "Trend Market",
                email: "trendmarket@example.com",
                order: "ZYL-2026-008",
                amount: 12000,
                commission: 1200,
                status: "overdue"
            },
            {
                id: 9,
                transaction: "COM-2026-009",
                seller: "Simple Home",
                email: "simplehome@example.com",
                order: "ZYL-2026-009",
                amount: 3900,
                commission: 390,
                status: "pending"
            },
            {
                id: 10,
                transaction: "COM-2026-010",
                seller: "Everyday Goods",
                email: "everydaygoods@example.com",
                order: "ZYL-2026-010",
                amount: 4700,
                commission: 470,
                status: "paid"
            },
            {
                id: 11,
                transaction: "COM-2026-011",
                seller: "Lifestyle Store",
                email: "lifestyle@example.com",
                order: "ZYL-2026-011",
                amount: 5600,
                commission: 560,
                status: "cancelled"
            },
            {
                id: 12,
                transaction: "COM-2026-012",
                seller: "Prime Collection",
                email: "primecollection@example.com",
                order: "ZYL-2026-012",
                amount: 8700,
                commission: 870,
                status: "pending"
            }
        ];

        let filteredCommissions = [...commissionsData];

        let currentPage = 1;

        const itemsPerPage = 5;

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

        function getStatusLabel(status) {
            const labels = {
                paid: "Paid",
                pending: "Pending",
                overdue: "Overdue",
                cancelled: "Cancelled"
            };

            return labels[status] || status;
        }

        function renderCommissions() {
            const tableBody = document.getElementById("commissionTableBody");
            const emptyState = document.getElementById("emptyState");

            const totalRecords = filteredCommissions.length;
            const totalPages = Math.ceil(totalRecords / itemsPerPage);

            if (currentPage > totalPages && totalPages > 0) {
                currentPage = totalPages;
            }

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;

            const currentRecords = filteredCommissions.slice(
                startIndex,
                endIndex
            );

            tableBody.innerHTML = "";

            if (currentRecords.length === 0) {
                emptyState.style.display = "block";
            } else {
                emptyState.style.display = "none";

                currentRecords.forEach(record => {
                    const row = document.createElement("tr");

                    const actionButtons = `
                        <div class="actions">

                            ${
                                record.status === "pending" ||
                                record.status === "overdue"
                                    ? `
                                        <button
                                            class="action-btn pay"
                                            title="Mark as Paid"
                                            onclick="markAsPaid(${record.id})"
                                        >
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    `
                                    : ""
                            }

                            <button
                                class="action-btn"
                                title="View Details"
                                onclick="viewCommission(${record.id})"
                            >
                                <i class="fa-regular fa-eye"></i>
                            </button>

                        </div>
                    `;

                    row.innerHTML = `
                        <td>
                            <div class="transaction-id">
                                ${escapeHtml(record.transaction)}
                            </div>
                        </td>

                        <td>
                            <div class="seller-name">
                                ${escapeHtml(record.seller)}
                            </div>

                            <div class="seller-email">
                                ${escapeHtml(record.email)}
                            </div>
                        </td>

                        <td>
                            <div class="order-id">
                                ${escapeHtml(record.order)}
                            </div>
                        </td>

                        <td>
                            <div class="amount">
                                ${formatCurrency(record.amount)}
                            </div>
                        </td>

                        <td>
                            <div class="commission-amount">
                                ${formatCurrency(record.commission)}
                            </div>
                        </td>

                        <td>
                            <span class="status ${escapeHtml(record.status)}">
                                ${escapeHtml(getStatusLabel(record.status))}
                            </span>
                        </td>

                        <td>
                            ${actionButtons}
                        </td>
                    `;

                    tableBody.appendChild(row);
                });
            }

            document.getElementById("tableCount").textContent =
                `${totalRecords} record${totalRecords === 1 ? "" : "s"}`;

            document.getElementById("paginationInfo").textContent =
                totalRecords === 0
                    ? "Showing 0 of 0 records"
                    : `Showing ${startIndex + 1}-${Math.min(
                          endIndex,
                          totalRecords
                      )} of ${totalRecords} records`;

            updatePagination(totalPages);
        }

        function updatePagination(totalPages) {
            const pagination = document.getElementById("pagination");

            pagination.innerHTML = "";

            const previousButton = document.createElement("button");

            previousButton.className = "page-btn";
            previousButton.innerHTML =
                '<i class="fa-solid fa-chevron-left"></i>';

            previousButton.disabled =
                currentPage === 1 || totalPages === 0;

            previousButton.onclick = () => changePage(currentPage - 1);

            pagination.appendChild(previousButton);

            for (let page = 1; page <= totalPages; page++) {
                const pageButton = document.createElement("button");

                pageButton.className = "page-btn";
                pageButton.textContent = page;

                if (page === currentPage) {
                    pageButton.classList.add("active");
                }

                pageButton.onclick = () => changePage(page);

                pagination.appendChild(pageButton);
            }

            const nextButton = document.createElement("button");

            nextButton.className = "page-btn";
            nextButton.innerHTML =
                '<i class="fa-solid fa-chevron-right"></i>';

            nextButton.disabled =
                currentPage === totalPages || totalPages === 0;

            nextButton.onclick = () => changePage(currentPage + 1);

            pagination.appendChild(nextButton);
        }

        function changePage(page) {
            const totalPages = Math.ceil(
                filteredCommissions.length / itemsPerPage
            );

            if (page < 1 || page > totalPages) {
                return;
            }

            currentPage = page;

            renderCommissions();
        }

        function filterCommissions() {
            const searchValue = document
                .getElementById("searchCommission")
                .value
                .toLowerCase()
                .trim();

            const statusValue = document.getElementById(
                "statusFilter"
            ).value;

            filteredCommissions = commissionsData.filter(record => {
                const searchableText = [
                    record.transaction,
                    record.seller,
                    record.email,
                    record.order
                ]
                    .join(" ")
                    .toLowerCase();

                const matchesSearch =
                    searchValue === "" ||
                    searchableText.includes(searchValue);

                const matchesStatus =
                    statusValue === "" ||
                    record.status === statusValue;

                return matchesSearch && matchesStatus;
            });

            currentPage = 1;

            renderCommissions();
        }

        function clearFilters() {
            document.getElementById("searchCommission").value = "";
            document.getElementById("statusFilter").value = "";
            document.getElementById("headerSearch").value = "";

            filteredCommissions = [...commissionsData];

            currentPage = 1;

            renderCommissions();
        }

        function syncHeaderSearch(value) {
            document.getElementById("searchCommission").value = value;

            filterCommissions();
        }

        function markAsPaid(id) {
            const record = commissionsData.find(item => item.id === id);

            if (!record) {
                return;
            }

            const confirmed = confirm(
                `Mark commission ${record.transaction} as paid?`
            );

            if (!confirmed) {
                return;
            }

            record.status = "paid";

            filterCommissions();

            showNotification(
                `Commission ${record.transaction} marked as paid.`
            );
        }

        function viewCommission(id) {
            const record = commissionsData.find(item => item.id === id);

            if (!record) {
                return;
            }

            alert(
                `Commission Details\n\n` +
                `Transaction: ${record.transaction}\n` +
                `Seller: ${record.seller}\n` +
                `Order ID: ${record.order}\n` +
                `Order Amount: ${formatCurrency(record.amount)}\n` +
                `Commission: ${formatCurrency(record.commission)}\n` +
                `Status: ${getStatusLabel(record.status)}`
            );
        }

        function showNotification(message) {
            const existingNotification =
                document.querySelector(".notification");

            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement("div");

            notification.className = "notification";

            notification.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                <span>${escapeHtml(message)}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3500);
        }

        document.addEventListener("DOMContentLoaded", () => {
            renderCommissions();
        });
    </script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>
</html>