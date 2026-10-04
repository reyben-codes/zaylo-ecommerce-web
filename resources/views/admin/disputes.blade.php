
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Disputes Management</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>
        :root {
            --primary: #1a1714;
            --brown: #b28b6f;
            --cream: #faf7f2;
            --white: #ffffff;
            --border: #ece4db;
            --text: #1e1e1e;
            --muted: #6b5f54;
            --warning: #e67e22;
            --danger: #c0392b;
            --success: #2d7d46;
            --blue: #2c6b9e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: "Inter", sans-serif;
            line-height: 1.4;
            overflow-x: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font-family: inherit;
        }

        button {
            cursor: pointer;
        }

        /* ========================================
           FULL-SCREEN HEADER
        ======================================== */

        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;

            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;

            width: 100%;
            height: 70px;
            padding: 0 56px;

            background: #ffffff;
            border-bottom: 1px solid #eeeeeb;
        }

        .header-logo {
            grid-column: 2;

            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header-logo img {
            display: block;
            width: 78px;
            height: auto;
            object-fit: contain;
        }

        .header-right {
            grid-column: 3;

            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .header-search {
            display: flex;
            align-items: center;
            gap: 10px;

            width: 360px;
            height: 42px;
            padding: 0 18px;

            background: #f5f3f0;
            border: 1px solid #e5dfd8;
            border-radius: 24px;
        }

        .header-search i {
            color: #4a4037;
            font-size: 14px;
        }

        .header-search input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;

            color: var(--text);
            font-size: 13px;
        }

        .header-search input::placeholder {
            color: #a89b8c;
        }

        /* ========================================
           DASHBOARD LAYOUT
        ======================================== */

        .dashboard-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            padding-top: 70px;
        }

        /* ========================================
           SIDEBAR
        ======================================== */

        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            z-index: 900;

            width: 250px;
            padding: 28px 18px;

            background: var(--white);
            border-right: 1px solid var(--border);
            overflow-y: auto;
        }

        .sidebar-title {
            padding: 0 14px;
            margin-bottom: 20px;

            color: #aaa19a;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.3px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;

            min-height: 43px;
            padding: 10px 14px;

            color: #6b5f54;
            border-radius: 10px;

            font-size: 12px;
            font-weight: 500;
            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            color: var(--primary);
            background: #f5f0ea;
        }

        .sidebar-menu a.active {
            color: var(--white);
            background: var(--primary);
        }

        .sidebar-menu a i {
            width: 18px;
            font-size: 14px;
            text-align: center;
        }

        .sidebar-menu .badge {
            display: flex;
            align-items: center;
            justify-content: center;

            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            margin-left: auto;

            color: white;
            background: var(--brown);
            border-radius: 20px;

            font-size: 10px;
            font-weight: 700;
        }

        .sidebar-divider {
            height: 1px;
            margin: 22px 12px;
            background: var(--border);
        }

        .logout-link {
            color: #a14d4d !important;
        }

        .logout-link:hover {
            color: white !important;
            background: #a14d4d !important;
        }

        /* ========================================
           MAIN CONTENT
        ======================================== */

        .main-content {
            width: calc(100% - 250px);
            min-height: calc(100vh - 70px);
            margin-left: 250px;
            padding: 42px 48px 60px;
        }

        .content-wrapper {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        /* ========================================
           PAGE HEADER
        ======================================== */

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .page-header h1 {
            color: var(--primary);
            font-family: "Playfair Display", serif;
            font-size: 30px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .subtitle {
            margin-top: 7px;
            color: var(--muted);
            font-size: 13px;
        }

        .pending-disputes {
            padding: 8px 17px;

            color: white;
            background: var(--danger);
            border-radius: 20px;

            font-size: 11px;
            font-weight: 600;
        }

        /* ========================================
           DISPUTE STATISTICS
        ======================================== */

        .dispute-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .dispute-stat {
            padding: 22px;
            background: white;
            border: 1px solid var(--border);
            box-shadow: 0 3px 18px rgba(45, 33, 26, 0.03);
        }

        .dispute-stat .label {
            color: var(--muted);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .dispute-stat .number {
            margin-top: 10px;
            color: var(--primary);
            font-size: 28px;
            font-weight: 700;
        }

        .number.warning {
            color: var(--warning);
        }

        .number.danger {
            color: var(--danger);
        }

        .number.success {
            color: var(--success);
        }

        /* ========================================
           FILTER BAR
        ======================================== */

        .filter-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;

            padding: 18px 20px;
            margin-bottom: 24px;

            background: white;
            border: 1px solid var(--border);
        }

        .complaint-type-buttons { display:flex; flex-wrap:wrap; gap:9px; margin:0 0 16px; }
        .complaint-type-button { display:inline-flex; align-items:center; gap:8px; min-height:42px; padding:10px 14px; color:var(--brown); background:#fff; border:1px solid var(--border); font-size:11px; font-weight:600; cursor:pointer; transition:.2s ease; }
        .complaint-type-button:hover { border-color:var(--primary); color:var(--primary); }
        .complaint-type-button.active { color:#fff; background:var(--primary); border-color:var(--primary); }
        .complaint-type-count { padding:2px 7px; background:#f5f0ea; color:var(--brown); border-radius:12px; font-size:10px; }
        .complaint-type-button.active .complaint-type-count { color:#fff; background:var(--brown); }

        .filter-bar input,
        .filter-bar select {
            height: 40px;
            padding: 0 14px;

            color: var(--text);
            background: white;
            border: 1px solid var(--border);
            border-radius: 7px;
            outline: none;

            font-size: 12px;
            transition: 0.2s ease;
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: var(--primary);
        }

        .filter-bar .search-input {
            flex: 1;
            min-width: 220px;
        }

        .btn-filter {
            height: 40px;
            padding: 0 20px;

            color: white;
            background: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 7px;

            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            transition: 0.2s ease;
        }

        .btn-filter:hover {
            background: var(--brown);
            border-color: var(--brown);
        }

        .btn-filter.clear {
            color: var(--muted);
            background: transparent;
            border-color: var(--border);
        }

        .btn-filter.clear:hover {
            color: var(--primary);
            background: #f5f0ea;
            border-color: var(--primary);
        }

        /* ========================================
           DISPUTE CARDS
        ======================================== */

        .dispute-card {
            padding: 24px;
            margin-bottom: 18px;

            background: white;
            border: 1px solid var(--border);

            transition: 0.2s ease;
            animation: fadeInUp 0.3s ease forwards;
            opacity: 0;
        }

        .dispute-card:hover {
            border-color: var(--brown);
        }

        .dispute-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;

            padding-bottom: 15px;
            border-bottom: 1px solid #f5f0ea;
        }

        .dispute-id {
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
        }

        .order-id {
            margin-left: 12px;
            color: var(--muted);
            font-size: 11px;
        }

        .dispute-date {
            color: var(--muted);
            font-size: 11px;
        }

        .dispute-status {
            padding: 5px 13px;

            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .status-open {
            color: #a75a14;
            background: #f5e0c0;
        }

        .status-review {
            color: var(--blue);
            background: #d4e0f5;
        }

        .status-resolved {
            color: var(--success);
            background: #d6e4d0;
        }

        .status-escalated {
            color: var(--danger);
            background: #f5e0e0;
        }

        .status-closed {
            color: var(--muted);
            background: #e5dfd8;
        }

        .dispute-body {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(160px, 1fr) 150px;
            gap: 24px;
            padding-top: 20px;
        }

        .dispute-details h4,
        .dispute-parties h4 {
            margin-bottom: 10px;

            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .detail-item {
            padding: 3px 0;
            color: var(--primary);
            font-size: 12px;
        }

        .detail-item .label,
        .party .role,
        .amount-label {
            color: var(--muted);
        }

        .issue-desc {
            padding-top: 10px;
            margin-top: 8px;

            color: #51473f;
            border-top: 1px solid #f5f0ea;

            font-size: 12px;
            line-height: 1.7;
        }

        .party {
            padding: 4px 0;
            color: var(--primary);
            font-size: 12px;
            line-height: 1.5;
        }

        .party .role {
            display: block;
            font-size: 10px;
        }

        .amount {
            margin-top: 12px;
        }

        .amount-value {
            display: block;
            margin-top: 5px;
            color: var(--primary);
            font-size: 15px;
            font-weight: 700;
        }

        .dispute-actions {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 7px;
        }

        .dispute-actions button {
            min-height: 34px;
            padding: 7px 12px;

            border: none;
            border-radius: 6px;

            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            transition: 0.2s ease;
        }

        .btn-review {
            color: white;
            background: var(--blue);
        }

        .btn-review:hover {
            background: #1e4a6e;
        }

        .btn-resolve {
            color: white;
            background: var(--success);
        }

        .btn-resolve:hover {
            background: #1e5a34;
        }

        .btn-escalate {
            color: white;
            background: var(--danger);
        }

        .btn-escalate:hover {
            background: #962d22;
        }

        .btn-close {
            color: white;
            background: #6b5f54;
        }

        .btn-close:hover {
            background: #4a4037;
        }

        .btn-view {
            color: var(--primary);
            background: transparent;
            border: 1px solid var(--border) !important;
        }

        .btn-view:hover {
            border-color: var(--primary) !important;
            background: #f5f0ea;
        }

        /* ========================================
           EMPTY STATE
        ======================================== */

        .empty-disputes {
            padding: 70px 20px;

            background: white;
            border: 1px solid var(--border);
            text-align: center;
        }

        .empty-disputes i {
            margin-bottom: 18px;
            color: var(--brown);
            font-size: 40px;
        }

        .empty-disputes h3 {
            margin-bottom: 8px;

            color: var(--primary);
            font-family: "Playfair Display", serif;
            font-size: 22px;
        }

        .empty-disputes p {
            color: var(--muted);
            font-size: 12px;
        }

        /* ========================================
           NOTIFICATION
        ======================================== */

        .zaylo-notification {
            position: fixed;
            right: 28px;
            bottom: 28px;
            z-index: 9999;

            display: flex;
            align-items: center;
            gap: 10px;

            max-width: 380px;
            padding: 15px 20px;

            color: white;
            background: var(--primary);
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);

            font-size: 12px;
            animation: slideUp 0.3s ease;
        }

        .zaylo-notification i {
            color: #d9b38b;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(100px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideDown {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(100px);
            }
        }

        /* ========================================
           RESPONSIVE DESIGN
        ======================================== */

        @media (max-width: 1200px) {
            .header {
                padding: 0 32px;
            }

            .main-content {
                padding: 35px 30px 50px;
            }

            .dispute-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dispute-body {
                grid-template-columns: 2fr 1fr;
            }

            .dispute-actions {
                grid-column: 1 / -1;
                flex-direction: row;
                align-items: center;
            }

            .dispute-actions button {
                flex: 1;
            }
        }

        @media (max-width: 900px) {
            .header {
                padding: 0 24px;
            }

            .header-search {
                width: 270px;
            }

            .sidebar {
                width: 220px;
            }

            .main-content {
                width: calc(100% - 220px);
                margin-left: 220px;
                padding: 30px 24px;
            }

            .dispute-body {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .dispute-actions {
                grid-column: auto;
            }
        }

        @media (max-width: 680px) {
            .header {
                display: flex;
                justify-content: space-between;
                height: 64px;
                padding: 0 18px;
            }

            .header-logo {
                order: 1;
            }

            .header-right {
                order: 2;
            }

            .header-logo img {
                width: 72px;
            }

            .header-search {
                width: 42px;
                height: 40px;
                padding: 0;
                justify-content: center;
            }

            .header-search input {
                display: none;
            }

            .dashboard-wrapper {
                display: block;
                padding-top: 64px;
            }

            .sidebar {
                position: relative;
                top: auto;
                width: 100%;
                height: auto;
                min-height: auto;
                padding: 18px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-menu {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .sidebar-menu li {
                margin-bottom: 0;
            }

            .sidebar-divider {
                margin: 18px 12px;
            }

            .main-content {
                width: 100%;
                min-height: auto;
                margin-left: 0;
                padding: 25px 18px 40px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .filter-bar {
                align-items: stretch;
                flex-direction: column;
            }

            .filter-bar .search-input {
                width: 100%;
                min-width: 0;
            }

            .filter-bar select {
                width: 100%;
            }

            .filter-bar button {
                width: 100%;
            }

            .dispute-stats {
                gap: 10px;
            }

            .dispute-stat {
                padding: 17px;
            }

            .dispute-stat .number {
                font-size: 24px;
            }

            .dispute-card {
                padding: 18px;
            }

            .dispute-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .dispute-actions {
                flex-direction: column;
            }

            .dispute-actions button {
                width: 100%;
            }
        }

        @media (max-width: 420px) {
            .sidebar-menu {
                grid-template-columns: 1fr;
            }

            .dispute-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>

    <!-- ========================================
         HEADER
    ======================================== -->

    @include('admin.partials.header', ['searchId' => 'headerSearch', 'searchPlaceholder' => 'Search dashboard...', 'searchOnInput' => 'syncHeaderSearch(this.value)'])

    <!-- ========================================
         DASHBOARD WRAPPER
    ======================================== -->

    <div class="dashboard-wrapper">

        <!-- ========================================
             SIDEBAR
        ======================================== -->

        @include('admin.partials.sidebar')

        <!-- ========================================
             MAIN CONTENT
        ======================================== -->

        <main class="main-content">

            <div class="content-wrapper">

                <!-- Page Header -->

                <div class="page-header">

                    <div>
                        <h1>
                            Disputes Management
                        </h1>

                        <p class="subtitle">
                            Review and resolve customer disputes.
                        </p>
                    </div>

                    <span
                        class="pending-disputes"
                        id="pendingCount"
                    >
                        2 Pending
                    </span>

                </div>

                <!-- Dispute Statistics -->

                <div class="dispute-stats">

                    <div class="dispute-stat">
                        <div class="label">
                            Total Disputes
                        </div>

                        <div
                            class="number"
                            id="totalDisputes"
                        >
                            18
                        </div>
                    </div>

                    <div class="dispute-stat">
                        <div class="label">
                            Open
                        </div>

                        <div
                            class="number warning"
                            id="openDisputes"
                        >
                            4
                        </div>
                    </div>

                    <div class="dispute-stat">
                        <div class="label">
                            Resolved
                        </div>

                        <div
                            class="number success"
                            id="resolvedDisputes"
                        >
                            12
                        </div>
                    </div>

                    <div class="dispute-stat">
                        <div class="label">
                            Escalated
                        </div>

                        <div
                            class="number danger"
                            id="escalatedDisputes"
                        >
                            2
                        </div>
                    </div>

                </div>

                <div class="complaint-type-buttons" id="complaintTypeButtons" aria-label="Filter complaints by issue type"></div>

                <!-- Filter Bar -->

                <div class="filter-bar">

                    <input
                        type="text"
                        class="search-input"
                        id="searchDispute"
                        placeholder="Search by dispute ID, customer, or seller..."
                        oninput="filterDisputes()"
                    >

                    <select
                        id="statusFilter"
                        onchange="filterDisputes()"
                    >
                        <option value="all">
                            All Status
                        </option>

                        <option value="open">
                            Open
                        </option>

                        <option value="review">
                            Under Review
                        </option>

                        <option value="resolved">
                            Resolved
                        </option>

                        <option value="escalated">
                            Escalated
                        </option>

                        <option value="closed">
                            Closed
                        </option>
                    </select>

                    <button
                        type="button"
                        class="btn-filter"
                        onclick="filterDisputes()"
                    >
                        Apply
                    </button>

                    <button
                        type="button"
                        class="btn-filter clear"
                        onclick="clearFilters()"
                    >
                        Clear
                    </button>

                </div>

                <!-- Disputes Container -->

                <div id="disputesContainer"></div>

            </div>

        </main>

    </div>

    <script>
        // ========================================
        // DISPUTE DATA
        // ========================================

        const disputesData = [
            {
                id: "DSP-2024-001",
                date: "Dec 15, 2024",
                status: "open",
                customer: "Juan Dela Cruz",
                customerRole: "Buyer",
                seller: "ZAYLO Fashion Hub",
                orderId: "ZYL-2024-001",
                issue: "Wrong item delivered - received a different color",
                issueCategory: "Wrong item or size",
                description: "I ordered the Wool Blend Blazer in Navy but received Black instead. I need a replacement or refund.",
                amount: 4250
            },
            {
                id: "DSP-2024-002",
                date: "Dec 14, 2024",
                status: "review",
                customer: "Maria Reyes",
                customerRole: "Buyer",
                seller: "Luxury Bags Co.",
                orderId: "ZYL-2024-002",
                issue: "Damaged item on arrival",
                issueCategory: "Damaged or defective item",
                description: "The Leather Tote Bag arrived with a scratch on the front. I have attached photos for reference.",
                amount: 3750
            },
            {
                id: "DSP-2024-003",
                date: "Dec 13, 2024",
                status: "resolved",
                customer: "Pedro Santos",
                customerRole: "Buyer",
                seller: "Urban Wear PH",
                orderId: "ZYL-2024-003",
                issue: "Late delivery - item not received",
                issueCategory: "Delivery problem",
                description: "My order has been marked as shipped for five days but I have not received it yet.",
                amount: 6670
            },
            {
                id: "DSP-2024-004",
                date: "Dec 12, 2024",
                status: "escalated",
                customer: "Ana Lopez",
                customerRole: "Buyer",
                seller: "Elegant Accessories",
                orderId: "ZYL-2024-004",
                issue: "Counterfeit product received",
                issueCategory: "Product authenticity",
                description: "I received what appears to be a counterfeit product. The quality is very poor.",
                amount: 3800
            },
            {
                id: "DSP-2024-005",
                date: "Dec 11, 2024",
                status: "closed",
                customer: "Carlos Garcia",
                customerRole: "Buyer",
                seller: "Footwear Plus",
                orderId: "ZYL-2024-005",
                issue: "Size mismatch",
                issueCategory: "Wrong item or size",
                description: "I ordered size 10 but received size 9. I need to exchange it for the correct size.",
                amount: 5200
            },
            {
                id: "DSP-2024-006",
                date: "Dec 10, 2024",
                status: "open",
                customer: "Rosa Santos",
                customerRole: "Buyer",
                seller: "Watch Emporium",
                orderId: "ZYL-2024-006",
                issue: "Watch not working",
                issueCategory: "Damaged or defective item",
                description: "The watch I received is not working properly. The battery seems dead.",
                amount: 7000
            }
        ];

        let filteredDisputes = [...disputesData];
        let selectedIssueCategory = "all";

        // ========================================
        // STATUS MAP
        // ========================================

        const statusMap = {
            open: {
                label: "Open",
                class: "status-open"
            },
            review: {
                label: "Under Review",
                class: "status-review"
            },
            resolved: {
                label: "Resolved",
                class: "status-resolved"
            },
            escalated: {
                label: "Escalated",
                class: "status-escalated"
            },
            closed: {
                label: "Closed",
                class: "status-closed"
            }
        };

        function renderComplaintTypeButtons() {
            const counts = new Map();
            disputesData.forEach(item => {
                const type = item.issueCategory || "Other complaint";
                counts.set(type, (counts.get(type) || 0) + 1);
            });
            const container = document.getElementById("complaintTypeButtons");
            const buttons = [
                `<button type="button" class="complaint-type-button ${selectedIssueCategory === 'all' ? 'active' : ''}" data-issue="all" aria-pressed="${selectedIssueCategory === 'all'}">All Complaints <span class="complaint-type-count">${disputesData.length}</span></button>`,
                ...[...counts.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([type, count]) => `<button type="button" class="complaint-type-button ${selectedIssueCategory === type ? 'active' : ''}" data-issue="${escapeHtml(type)}" aria-pressed="${selectedIssueCategory === type}">${escapeHtml(type)} <span class="complaint-type-count">${count}</span></button>`)
            ];
            container.innerHTML = buttons.join("");
            container.querySelectorAll(".complaint-type-button").forEach(button => {
                button.addEventListener("click", () => {
                    selectedIssueCategory = button.dataset.issue;
                    renderComplaintTypeButtons();
                    filterDisputes();
                });
            });
        }

        // ========================================
        // ESCAPE HTML
        // ========================================

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // ========================================
        // UPDATE STATISTICS
        // ========================================

        function updateStatistics() {
            const total = disputesData.length;

            const open = disputesData.filter(
                item => item.status === "open"
            ).length;

            const resolved = disputesData.filter(
                item => item.status === "resolved"
            ).length;

            const escalated = disputesData.filter(
                item => item.status === "escalated"
            ).length;

            const pending = disputesData.filter(
                item =>
                    item.status === "open" ||
                    item.status === "review" ||
                    item.status === "escalated"
            ).length;

            document.getElementById("totalDisputes").textContent = total;
            document.getElementById("openDisputes").textContent = open;
            document.getElementById("resolvedDisputes").textContent = resolved;
            document.getElementById("escalatedDisputes").textContent = escalated;

            document.getElementById("pendingCount").textContent =
                `${pending} Pending`;
        }

        // ========================================
        // ACTION BUTTONS
        // ========================================

        function getActionButtons(item) {
            if (item.status === "open") {
                return `
                    <button
                        class="btn-review"
                        onclick="updateStatus('${item.id}', 'review')"
                    >
                        <i class="fas fa-search"></i>
                        Review
                    </button>

                    <button
                        class="btn-escalate"
                        onclick="updateStatus('${item.id}', 'escalated')"
                    >
                        <i class="fas fa-arrow-up"></i>
                        Escalate
                    </button>

                    <button
                        class="btn-view"
                        onclick="viewDispute('${item.id}')"
                    >
                        <i class="fas fa-eye"></i>
                        Details
                    </button>
                `;
            }

            if (item.status === "review") {
                return `
                    <button
                        class="btn-resolve"
                        onclick="updateStatus('${item.id}', 'resolved')"
                    >
                        <i class="fas fa-check"></i>
                        Resolve
                    </button>

                    <button
                        class="btn-escalate"
                        onclick="updateStatus('${item.id}', 'escalated')"
                    >
                        <i class="fas fa-arrow-up"></i>
                        Escalate
                    </button>

                    <button
                        class="btn-view"
                        onclick="viewDispute('${item.id}')"
                    >
                        <i class="fas fa-eye"></i>
                        Details
                    </button>
                `;
            }

            if (item.status === "escalated") {
                return `
                    <button
                        class="btn-resolve"
                        onclick="updateStatus('${item.id}', 'resolved')"
                    >
                        <i class="fas fa-check"></i>
                        Resolve
                    </button>

                    <button
                        class="btn-close"
                        onclick="updateStatus('${item.id}', 'closed')"
                    >
                        <i class="fas fa-times"></i>
                        Close
                    </button>

                    <button
                        class="btn-view"
                        onclick="viewDispute('${item.id}')"
                    >
                        <i class="fas fa-eye"></i>
                        Details
                    </button>
                `;
            }

            if (item.status === "resolved") {
                return `
                    <button
                        class="btn-close"
                        onclick="updateStatus('${item.id}', 'closed')"
                    >
                        <i class="fas fa-times"></i>
                        Close
                    </button>

                    <button
                        class="btn-view"
                        onclick="viewDispute('${item.id}')"
                    >
                        <i class="fas fa-eye"></i>
                        Details
                    </button>
                `;
            }

            return `
                <button
                    class="btn-view"
                    onclick="viewDispute('${item.id}')"
                >
                    <i class="fas fa-eye"></i>
                    Details
                </button>
            `;
        }

        // ========================================
        // RENDER DISPUTES
        // ========================================

        function renderDisputes() {
            const container = document.getElementById("disputesContainer");

            if (filteredDisputes.length === 0) {
                container.innerHTML = `
                    <div class="empty-disputes">
                        <i class="fas fa-gavel"></i>
                        <h3>No disputes found</h3>
                        <p>Try changing your search or filter options.</p>
                    </div>
                `;

                return;
            }

            let html = "";

            filteredDisputes.forEach((item, index) => {
                const statusInfo =
                    statusMap[item.status] || statusMap.open;

                html += `
                    <article
                        class="dispute-card"
                        style="animation-delay: ${index * 0.05}s"
                    >

                        <div class="dispute-header">

                            <div>
                                <span class="dispute-id">
                                    #${escapeHtml(item.id)}
                                </span>

                                <span class="order-id">
                                    Order: ${escapeHtml(item.orderId)}
                                </span>
                            </div>

                            <span class="dispute-date">
                                ${escapeHtml(item.date)}
                            </span>

                            <span class="dispute-status ${statusInfo.class}">
                                ${escapeHtml(statusInfo.label)}
                            </span>

                        </div>

                        <div class="dispute-body">

                            <div class="dispute-details">

                                <h4>
                                    Issue Details
                                </h4>

                                <div class="detail-item">
                                    <span class="label">Issue:</span>
                                    ${escapeHtml(item.issue)}
                                </div>

                                <div class="issue-desc">
                                    ${escapeHtml(item.description)}
                                </div>

                            </div>

                            <div class="dispute-parties">

                                <h4>
                                    Parties
                                </h4>

                                <div class="party">
                                    <strong>
                                        ${escapeHtml(item.customer)}
                                    </strong>

                                    <span class="role">
                                        ${escapeHtml(item.customerRole)}
                                    </span>
                                </div>

                                <div class="party">
                                    <strong>
                                        ${escapeHtml(item.seller)}
                                    </strong>

                                    <span class="role">
                                        Seller
                                    </span>
                                </div>

                                <div class="amount">

                                    <span class="amount-label">
                                        Amount
                                    </span>

                                    <strong class="amount-value">
                                        ₱${item.amount.toLocaleString("en-PH")}.00
                                    </strong>

                                </div>

                            </div>

                            <div class="dispute-actions">
                                ${getActionButtons(item)}
                            </div>

                        </div>

                    </article>
                `;
            });

            container.innerHTML = html;
        }

        // ========================================
        // FILTER DISPUTES
        // ========================================

        function filterDisputes() {
            const search = document
                .getElementById("searchDispute")
                .value
                .toLowerCase()
                .trim();

            const status = document.getElementById("statusFilter").value;

            filteredDisputes = disputesData.filter(item => {
                const matchesSearch =
                    item.id.toLowerCase().includes(search) ||
                    item.customer.toLowerCase().includes(search) ||
                    item.seller.toLowerCase().includes(search) ||
                    item.orderId.toLowerCase().includes(search) ||
                    item.issue.toLowerCase().includes(search);

                const matchesStatus =
                    status === "all" ||
                    item.status === status;

                const matchesIssueCategory = selectedIssueCategory === "all" ||
                    (item.issueCategory || "Other complaint") === selectedIssueCategory;

                return matchesSearch && matchesStatus && matchesIssueCategory;
            });

            renderDisputes();
        }

        // ========================================
        // CLEAR FILTERS
        // ========================================

        function clearFilters() {
            document.getElementById("searchDispute").value = "";
            document.getElementById("statusFilter").value = "all";
            document.getElementById("headerSearch").value = "";
            selectedIssueCategory = "all";
            renderComplaintTypeButtons();

            filteredDisputes = [...disputesData];

            renderDisputes();
        }

        // ========================================
        // HEADER SEARCH
        // ========================================

        function syncHeaderSearch(value) {
            document.getElementById("searchDispute").value = value;
            filterDisputes();
        }

        // ========================================
        // UPDATE STATUS
        // ========================================

        function updateStatus(disputeId, newStatus) {
            const item = disputesData.find(
                dispute => dispute.id === disputeId
            );

            if (!item) {
                return;
            }

            const statusLabels = {
                review: "Under Review",
                resolved: "Resolved",
                escalated: "Escalated",
                closed: "Closed"
            };

            const newStatusLabel =
                statusLabels[newStatus] || newStatus;

            const confirmed = confirm(
                `Update dispute #${disputeId} status to ${newStatusLabel}?`
            );

            if (!confirmed) {
                return;
            }

            item.status = newStatus;

            updateStatistics();
            filterDisputes();

            showNotification(
                `Dispute #${disputeId} updated to ${newStatusLabel}.`
            );
        }

        // ========================================
        // VIEW DISPUTE
        // ========================================

        function viewDispute(disputeId) {
            const item = disputesData.find(
                dispute => dispute.id === disputeId
            );

            if (!item) {
                return;
            }

            const statusInfo =
                statusMap[item.status] || statusMap.open;

            alert(
                `DISPUTE REPORT\n\n` +
                `ID: #${item.id}\n` +
                `Date: ${item.date}\n` +
                `Status: ${statusInfo.label}\n\n` +
                `Customer: ${item.customer} (${item.customerRole})\n` +
                `Seller: ${item.seller}\n` +
                `Order: ${item.orderId}\n` +
                `Amount: ₱${item.amount.toLocaleString("en-PH")}.00\n\n` +
                `Issue: ${item.issue}\n` +
                `Description: ${item.description}`
            );
        }

        // ========================================
        // NOTIFICATION SYSTEM
        // ========================================

        function showNotification(message) {
            const existing =
                document.querySelector(".zaylo-notification");

            if (existing) {
                existing.remove();
            }

            const notification = document.createElement("div");

            notification.className = "zaylo-notification";

            notification.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <span>${escapeHtml(message)}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation =
                    "slideDown 0.3s ease";

                setTimeout(() => {
                    notification.remove();
                }, 300);

            }, 3000);
        }

        // ========================================
        // INITIALIZE PAGE
        // ========================================

        document.addEventListener("DOMContentLoaded", () => {
            updateStatistics();
            renderComplaintTypeButtons();
            renderDisputes();
        });
    </script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>

</html>
