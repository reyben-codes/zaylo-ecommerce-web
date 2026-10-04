
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Compliance Management</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        :root {
            --primary: #2f211b;
            --primary-light: #6d5141;
            --cream: #f8f7f4;
            --white: #ffffff;
            --border: #e9e5df;
            --text: #292522;
            --muted: #8c847d;
            --green: #287a4c;
            --green-bg: #e8f5eb;
            --orange: #b87525;
            --orange-bg: #fff2df;
            --red: #b94343;
            --red-bg: #fdeaea;
            --shadow: 0 3px 18px rgba(45, 33, 26, 0.04);
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
            font-family: "Inter", sans-serif;
            color: var(--text);
            background: var(--cream);
        }

        body {
            min-height: 100vh;
            overflow-x: hidden;
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

        button {
            cursor: pointer;
        }

        /* ==============================
           FULL-SCREEN HEADER
        ============================== */

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

            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .header-left {
            grid-column: 1;
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
            color: var(--primary);
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
            color: var(--muted);
        }

        /* ==============================
           DASHBOARD BODY
        ============================== */

        .dashboard-body {
            display: flex;
            width: 100%;
            min-height: 100vh;
            padding-top: 70px;
        }

        /* ==============================
           SIDEBAR
        ============================== */

        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            bottom: 0;
            z-index: 900;

            display: flex;
            flex-direction: column;

            width: 250px;
            padding: 28px 18px;

            background: var(--white);
            border-right: 1px solid var(--border);
            overflow-y: auto;
        }

        .sidebar-title {
            padding: 0 14px;
            margin-bottom: 22px;

            color: #aaa19a;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            justify-content: space-between;

            min-height: 44px;
            padding: 0 14px;

            color: #827870;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;

            transition: 0.2s ease;
        }

        .sidebar-link:hover {
            color: var(--primary);
            background: #f8f5f1;
        }

        .sidebar-link.active {
            color: var(--white);
            background: var(--primary);
        }

        .sidebar-link-left {
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .sidebar-link i {
            width: 17px;
            font-size: 14px;
            text-align: center;
        }

        .sidebar-badge {
            display: flex;
            align-items: center;
            justify-content: center;

            min-width: 20px;
            height: 20px;
            padding: 0 6px;

            color: #ffffff;
            background: #bd554d;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
        }

        .sidebar-divider {
            height: 1px;
            margin: 22px 12px;
            background: var(--border);
        }

        .sidebar-bottom {
            margin-top: auto;
        }

        .logout-link {
            color: #a14d4d;
        }

        .logout-link:hover {
            color: #ffffff;
            background: #a14d4d;
        }

        /* ==============================
           MAIN CONTENT
        ============================== */

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

        /* ==============================
           PAGE HEADER
        ============================== */

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 30px;
        }

        .page-title {
            color: var(--primary);
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.8px;
        }

        .page-subtitle {
            margin-top: 9px;
            color: var(--muted);
            font-size: 13px;
        }

        .violation-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 10px 14px;

            color: var(--red);
            background: var(--red-bg);
            border-radius: 24px;

            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .violation-label i {
            font-size: 11px;
        }

        /* ==============================
           STATISTICS
        ============================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            padding: 23px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
        }

        .stat-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 34px;
            height: 34px;
            border-radius: 10px;
            font-size: 14px;
        }

        .stat-icon.total {
            color: #755a42;
            background: #f3e9dc;
        }

        .stat-icon.compliant {
            color: var(--green);
            background: var(--green-bg);
        }

        .stat-icon.review {
            color: var(--orange);
            background: var(--orange-bg);
        }

        .stat-icon.violation {
            color: var(--red);
            background: var(--red-bg);
        }

        .stat-value {
            margin-top: 18px;
            color: var(--primary);
            font-size: 30px;
            font-weight: 700;
            letter-spacing: -1px;
        }

        .stat-description {
            margin-top: 7px;
            color: #a39a93;
            font-size: 11px;
        }

        /* ==============================
           FILTER PANEL
        ============================== */

        .filter-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;

            padding: 18px 20px;
            margin-bottom: 22px;

            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
        }

        .filter-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            flex-wrap: wrap;
        }

        .filter-search {
            position: relative;
            min-width: 250px;
            flex: 1;
            max-width: 360px;
        }

        .filter-search i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);

            color: #aaa19a;
            font-size: 13px;
        }

        .filter-search input {
            width: 100%;
            height: 40px;
            padding: 0 15px 0 40px;

            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;

            color: var(--text);
            background: #fcfbf9;
            font-size: 12px;
        }

        .filter-search input:focus,
        .filter-select:focus {
            border-color: var(--primary-light);
        }

        .filter-select {
            height: 40px;
            min-width: 150px;
            padding: 0 12px;

            color: #665c55;
            background: #fcfbf9;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: none;

            font-size: 12px;
        }

        .filter-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            min-height: 40px;
            padding: 0 17px;

            border: none;
            border-radius: 8px;

            font-size: 12px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .btn-primary {
            color: var(--white);
            background: var(--primary);
        }

        .btn-primary:hover {
            background: #4b362a;
        }

        .btn-secondary {
            color: #786e66;
            background: #f4f1ed;
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #eae4de;
        }

        /* ==============================
           COMPLIANCE LIST
        ============================== */

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 16px;
        }

        .section-title {
            color: var(--primary);
            font-size: 16px;
            font-weight: 700;
        }

        .section-count {
            color: var(--muted);
            font-size: 12px;
        }

        .compliance-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .issue-group { margin-bottom:28px; }
        .issue-group .compliance-list { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .issue-filter-buttons { display:flex; flex-wrap:wrap; gap:9px; margin:0 0 22px; }
        .issue-filter-button { display:inline-flex; align-items:center; gap:9px; min-height:42px; padding:10px 14px; border:1px solid var(--border); background:var(--white); color:var(--brown); font:500 12px 'Inter',sans-serif; cursor:pointer; transition:.2s ease; }
        .issue-filter-button:hover { border-color:var(--accent); color:var(--primary); }
        .issue-filter-button.active { border-color:var(--dark); background:var(--dark); color:var(--white); }
        .issue-filter-count { padding:2px 7px; border-radius:12px; background:#f5f0ea; color:var(--brown); font-size:10px; }
        .issue-filter-button.active .issue-filter-count { background:var(--accent); color:#fff; }
        .issue-group-heading { display:flex; align-items:center; gap:10px; margin-bottom:12px; padding-bottom:9px; border-bottom:1px solid var(--border); color:var(--primary); font-size:14px; font-weight:700; }
        .issue-group-heading .group-count { color:var(--muted); font-size:11px; font-weight:500; }
        .detail-link { color:inherit; text-decoration:none; }
        .detail-link:hover { color:var(--accent); text-decoration:underline; }
        .issue-detail-page { padding:24px; background:var(--white); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); }
        .detail-back-link { display:inline-flex; align-items:center; gap:8px; margin-bottom:20px; color:var(--brown); font-size:12px; text-decoration:none; }
        .detail-back-link:hover { color:var(--accent); }
        .issue-detail-header { display:flex; justify-content:space-between; align-items:flex-start; gap:18px; padding-bottom:18px; border-bottom:1px solid var(--border); }
        .issue-detail-header h2 { color:var(--primary); font:600 24px 'Playfair Display',serif; }
        .issue-detail-header p { margin-top:6px; color:var(--muted); font-size:12px; }
        .issue-detail-grid { display:grid; grid-template-columns:minmax(0,1.15fr) minmax(260px,.85fr); gap:22px; margin-top:22px; }
        .issue-photo-panel { min-height:280px; display:flex; align-items:center; justify-content:center; padding:16px; background:#faf7f2; border:1px solid var(--border); }
        .issue-photo-panel img { max-width:100%; max-height:620px; object-fit:contain; }
        .issue-photo-empty { color:var(--muted); text-align:center; font-size:12px; line-height:1.6; }
        .issue-detail-fields { display:grid; grid-template-columns:1fr 1fr; gap:16px; align-content:start; }
        .issue-field { padding:12px; background:#faf7f2; }
        .issue-field .detail-value { overflow-wrap:anywhere; }
        .issue-detail-note { margin-top:18px; padding:14px; background:#f5f0ea; color:var(--brown); font-size:12px; line-height:1.7; }
        .issue-detail-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:18px; }

        .compliance-card {
            padding: 23px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
        }

        .compliance-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }

        .seller-info {
            display: flex;
            align-items: center;
            gap: 13px;
            min-width: 0;
        }

        .seller-avatar {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 46px;
            height: 46px;
            flex-shrink: 0;

            color: #765b45;
            background: #f2e7da;
            border-radius: 50%;

            font-size: 14px;
            font-weight: 700;
        }

        .seller-name {
            color: var(--primary);
            font-size: 14px;
            font-weight: 700;
        }

        .seller-email {
            margin-top: 5px;
            color: var(--muted);
            font-size: 11px;
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 6px 10px;
            border-radius: 20px;

            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-compliant,
        .status-approved {
            color: var(--green);
            background: var(--green-bg);
        }

        .status-review,
        .status-pending {
            color: var(--orange);
            background: var(--orange-bg);
        }

        .status-violation,
        .status-rejected {
            color: var(--red);
            background: var(--red-bg);
        }

        .compliance-details {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;

            padding: 20px 0;
            margin-top: 20px;

            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .detail-label {
            color: #a19891;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        .detail-value {
            margin-top: 7px;
            color: #51473f;
            font-size: 12px;
            font-weight: 600;
        }

        .compliance-note {
            margin-top: 16px;
            padding: 12px 13px;

            color: #766b63;
            background: #faf8f5;
            border-radius: 8px;

            font-size: 11px;
            line-height: 1.6;
        }

        .compliance-note i {
            margin-right: 6px;
            color: #a58c76;
        }

        .card-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 19px;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;

            min-height: 34px;
            padding: 0 12px;

            border: 1px solid var(--border);
            border-radius: 7px;

            color: #766b63;
            background: var(--white);
            font-size: 10px;
            font-weight: 600;

            transition: 0.2s ease;
        }

        .action-btn:hover {
            background: #f7f3ef;
        }

        .action-btn.approve {
            color: var(--green);
            border-color: #cde6d5;
            background: #f4fbf5;
        }

        .action-btn.approve:hover {
            background: var(--green-bg);
        }

        .action-btn.reject {
            color: var(--red);
            border-color: #f0d0d0;
            background: #fff8f8;
        }

        .action-btn.reject:hover {
            background: var(--red-bg);
        }

        .empty-state {
            grid-column: 1 / -1;

            padding: 60px 20px;
            color: var(--muted);
            background: var(--white);
            border: 1px dashed var(--border);
            border-radius: 14px;
            text-align: center;
        }

        .empty-state i {
            margin-bottom: 14px;
            color: #bdb3aa;
            font-size: 32px;
        }

        .empty-state p {
            font-size: 13px;
        }

        /* ==============================
           NOTIFICATION
        ============================== */

        .notification {
            position: fixed;
            right: 28px;
            bottom: 28px;
            z-index: 2000;

            display: flex;
            align-items: center;
            gap: 12px;

            max-width: 350px;
            padding: 15px 18px;

            color: var(--white);
            background: var(--primary);
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);

            font-size: 12px;
            animation: slideIn 0.25s ease;
        }

        .notification i {
            color: #d9b38b;
        }

        .notification.hide {
            animation: slideOut 0.25s ease forwards;
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

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(15px);
            }
        }

        /* ==============================
           RESPONSIVE DESIGN
        ============================== */

        @media (max-width: 1200px) {
            .header {
                padding: 0 32px;
            }

            .main-content {
                padding: 35px 30px 50px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .compliance-list {
                grid-template-columns: 1fr;
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

            .page-header {
                flex-direction: column;
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

            .dashboard-body {
                display: block;
                padding-top: 64px;
            }

            .sidebar {
                position: relative;
                top: auto;
                width: 100%;
                height: auto;
                max-height: none;
                padding: 18px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-nav {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 5px;
            }

            .sidebar-title {
                margin-bottom: 12px;
            }

            .sidebar-bottom {
                margin-top: 18px;
            }

            .sidebar-divider {
                margin: 15px 12px;
            }

            .main-content {
                width: 100%;
                min-height: auto;
                margin-left: 0;
                padding: 25px 18px 40px;
            }

            .page-title {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 17px;
            }

            .stat-value {
                font-size: 25px;
            }

            .filter-panel {
                align-items: stretch;
                flex-direction: column;
            }

            .filter-left {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-search {
                max-width: none;
                min-width: 0;
            }

            .filter-select {
                width: 100%;
            }

            .filter-actions {
                width: 100%;
            }

            .filter-actions .btn {
                flex: 1;
            }

            .compliance-card {
                padding: 18px;
            }

            .issue-group .compliance-list { grid-template-columns:1fr; }

            .compliance-card-header {
                flex-direction: column;
            }

            .card-actions {
                justify-content: stretch;
                flex-wrap: wrap;
            }

            .action-btn {
                flex: 1;
            }
        }

        @media (max-width: 420px) {
            .sidebar-nav {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .compliance-details {
                grid-template-columns: 1fr;
            }

            .issue-detail-grid { grid-template-columns:1fr; }
            .issue-detail-header { flex-direction:column; }
            .issue-detail-fields { grid-template-columns:1fr; }
            .issue-detail-page { padding:16px; }
            .issue-group .compliance-list { grid-template-columns:1fr; }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>

    <div class="dashboard-container">

        <!-- ==============================
             HEADER
        ============================== -->

        @include('admin.partials.header', ['searchId' => 'headerSearch', 'searchPlaceholder' => 'Search dashboard...', 'searchOnInput' => 'syncHeaderSearch(this.value)'])

        <!-- ==============================
             DASHBOARD BODY
        ============================== -->

        <div class="dashboard-body">

            <!-- ==============================
                 SIDEBAR
            ============================== -->

            @include('admin.partials.sidebar')

            <!-- ==============================
                 MAIN CONTENT
            ============================== -->

            <main class="main-content">

                <div class="content-wrapper">

                    <!-- Page Header -->

                    <div class="page-header">

                        <div>
                            <h1 class="page-title">
                                Compliance Management
                            </h1>

                            <p class="page-subtitle">
                                Monitor seller compliance and product violations.
                            </p>
                        </div>

                        <div class="violation-label">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span id="violationCount">2 Violations</span>
                        </div>

                    </div>

                    <div style="margin:0 0 22px;padding:14px 16px;background:#f5f0ea;border-left:3px solid #b28b6f;color:#6b5f54;font-size:12px;line-height:1.6">
                        <strong style="color:#1a1714">Strict listing review:</strong>
                        verify each photo depicts the named product, belongs to the seller, and fits the seller's registered category. Flag or reject sensitive, prohibited, misleading, or unrelated images; do not approve while any check fails.
                    </div>

                    <div class="issue-filter-buttons" id="issueTypeButtons" aria-label="Filter compliance records by issue"></div>

                    <!-- Statistics -->

                    <section class="stats-grid">

                        <div class="stat-card">

                            <div class="stat-card-top">
                                <span class="stat-label">
                                    Total Sellers
                                </span>

                                <span class="stat-icon total">
                                    <i class="fas fa-store"></i>
                                </span>
                            </div>

                            <div class="stat-value">
                                156
                            </div>

                            <p class="stat-description">
                                Registered sellers
                            </p>

                        </div>

                        <div class="stat-card">

                            <div class="stat-card-top">
                                <span class="stat-label">
                                    Compliant
                                </span>

                                <span class="stat-icon compliant">
                                    <i class="fas fa-check"></i>
                                </span>
                            </div>

                            <div class="stat-value">
                                142
                            </div>

                            <p class="stat-description">
                                Sellers meeting requirements
                            </p>

                        </div>

                        <div class="stat-card">

                            <div class="stat-card-top">
                                <span class="stat-label">
                                    Under Review
                                </span>

                                <span class="stat-icon review">
                                    <i class="fas fa-clock"></i>
                                </span>
                            </div>

                            <div class="stat-value">
                                12
                            </div>

                            <p class="stat-description">
                                Pending compliance checks
                            </p>

                        </div>

                        <div class="stat-card">

                            <div class="stat-card-top">
                                <span class="stat-label">
                                    Violations
                                </span>

                                <span class="stat-icon violation">
                                    <i class="fas fa-triangle-exclamation"></i>
                                </span>
                            </div>

                            <div class="stat-value">
                                2
                            </div>

                            <p class="stat-description">
                                Requires administrator action
                            </p>

                        </div>

                    </section>

                    <!-- Filters -->

                    <section class="filter-panel">

                        <div class="filter-left">

                            <div class="filter-search">
                                <i class="fas fa-search"></i>

                                <input
                                    type="text"
                                    id="searchComp"
                                    placeholder="Search seller or email..."
                                    oninput="filterCompliance()"
                                >
                            </div>

                            <select
                                id="statusFilter"
                                class="filter-select"
                                onchange="filterCompliance()"
                            >
                                <option value="all">
                                    All Statuses
                                </option>

                                <option value="compliant">
                                    Compliant
                                </option>

                                <option value="review">
                                    Under Review
                                </option>

                                <option value="violation">
                                    Violation
                                </option>
                            </select>

                        </div>

                        <div class="filter-actions">

                            <button
                                type="button"
                                class="btn btn-primary"
                                onclick="filterCompliance()"
                            >
                                <i class="fas fa-filter"></i>
                                Apply
                            </button>

                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="clearFilters()"
                            >
                                <i class="fas fa-rotate-left"></i>
                                Clear
                            </button>

                        </div>

                    </section>

                    <!-- Compliance List -->

                    <section>

                        <div class="section-header">

                            <h2 class="section-title">
                                Seller Compliance Records
                            </h2>

                            <span
                                class="section-count"
                                id="resultCount"
                            >
                                6 records
                            </span>

                        </div>

                        <div
                            class="compliance-list"
                            id="complianceList"
                        ></div>

                    </section>

                </div>

            </main>

        </div>

    </div>

    <script>
        const complianceData = [
            {
                id: 1,
                seller: "ZAYLO Fashion Hub",
                email: "zaylofashion@example.com",
                category: "Clothing & Apparel",
                product: "Fashion Collection",
                issueType: "No active issue",
                productImage: null,
                imageMatchesProduct: true,
                sensitiveImage: false,
                sellerOwnsProduct: true,
                status: "compliant",
                productStatus: "Approved",
                lastChecked: "September 15, 2026",
                note: "All required documents and product listings meet compliance requirements."
            },
            {
                id: 2,
                seller: "Urban Style Store",
                email: "urbanstyle@example.com",
                category: "Clothing & Apparel",
                product: "Streetwear Collection",
                issueType: "Seller documents pending",
                productImage: null,
                imageMatchesProduct: true,
                sensitiveImage: false,
                sellerOwnsProduct: true,
                status: "review",
                productStatus: "Under Review",
                lastChecked: "September 14, 2026",
                note: "Seller documents are currently being reviewed by the compliance team."
            },
            {
                id: 3,
                seller: "Daily Essentials",
                email: "dailyessentials@example.com",
                category: "Home & Lifestyle",
                product: "Home Essentials",
                issueType: "No active issue",
                productImage: null,
                imageMatchesProduct: true,
                sensitiveImage: false,
                sellerOwnsProduct: true,
                status: "compliant",
                productStatus: "Approved",
                lastChecked: "September 13, 2026",
                note: "No compliance issues were found during the latest inspection."
            },
            {
                id: 4,
                seller: "Premium Finds",
                email: "premiumfinds@example.com",
                category: "Bags & Accessories",
                product: "Premium Bags",
                issueType: "Product photo or ownership mismatch",
                productImage: null,
                imageMatchesProduct: false,
                sensitiveImage: false,
                sellerOwnsProduct: false,
                status: "violation",
                productStatus: "Flagged",
                lastChecked: "September 12, 2026",
                note: "Some product listings require additional documentation and verification."
            },
            {
                id: 5,
                seller: "Modern Closet",
                email: "moderncloset@example.com",
                category: "Shoes & Footwear",
                product: "Footwear Collection",
                issueType: "Seller documents pending",
                productImage: null,
                imageMatchesProduct: true,
                sensitiveImage: false,
                sellerOwnsProduct: true,
                status: "review",
                productStatus: "Under Review",
                lastChecked: "September 11, 2026",
                note: "The seller is waiting for approval of updated business documents."
            },
            {
                id: 6,
                seller: "Trend Market",
                email: "trendmarket@example.com",
                category: "Accessories",
                product: "Accessories Collection",
                issueType: "Sensitive or prohibited image",
                productImage: null,
                imageMatchesProduct: true,
                sensitiveImage: true,
                sellerOwnsProduct: true,
                status: "violation",
                productStatus: "Flagged",
                lastChecked: "September 10, 2026",
                note: "A product listing was flagged and requires administrator review."
            }
        ];

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function getInitials(name) {
            return name
                .split(" ")
                .map(word => word.charAt(0))
                .slice(0, 2)
                .join("")
                .toUpperCase();
        }

        function getStatusLabel(status) {
            const labels = {
                compliant: "Compliant",
                review: "Under Review",
                violation: "Violation"
            };

            return labels[status] || "Unknown";
        }

        function getStatusClass(status) {
            const classes = {
                compliant: "status-compliant",
                review: "status-review",
                violation: "status-violation"
            };

            return classes[status] || "";
        }

        const complianceBaseUrl = @json(route('admin.compliance'));

        let selectedIssueType = "all";

        function renderIssueTypeButtons() {
            const container = document.getElementById("issueTypeButtons");
            const counts = new Map();
            complianceData.forEach(item => {
                const issue = item.issueType || "Uncategorized issue";
                counts.set(issue, (counts.get(issue) || 0) + 1);
            });

            const buttons = [
                `<button type="button" class="issue-filter-button ${selectedIssueType === 'all' ? 'active' : ''}" data-issue="all" aria-pressed="${selectedIssueType === 'all'}">All Issues <span class="issue-filter-count">${complianceData.length}</span></button>`,
                ...[...counts.entries()].sort(([a], [b]) => a.localeCompare(b)).map(([issue, count]) => `
                    <button type="button" class="issue-filter-button ${selectedIssueType === issue ? 'active' : ''}" data-issue="${escapeHtml(issue)}" aria-pressed="${selectedIssueType === issue}">
                        ${escapeHtml(issue)} <span class="issue-filter-count">${count}</span>
                    </button>
                `)
            ];

            container.innerHTML = buttons.join("");
            container.querySelectorAll(".issue-filter-button").forEach(button => {
                button.addEventListener("click", () => {
                    selectedIssueType = button.dataset.issue;
                    renderIssueTypeButtons();
                    filterCompliance();
                });
            });
        }

        function renderCompliance(data = complianceData) {
            const list = document.getElementById("complianceList");
            const resultCount = document.getElementById("resultCount");

            resultCount.textContent =
                `${data.length} ${data.length === 1 ? "record" : "records"}`;

            if (data.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-folder-open"></i>
                        <p>No compliance records found.</p>
                    </div>
                `;

                return;
            }

            const groupedRecords = new Map();
            [...data]
                .sort((a, b) => a.seller.localeCompare(b.seller))
                .forEach(item => {
                    const issue = item.issueType || "Uncategorized issue";
                    if (!groupedRecords.has(issue)) groupedRecords.set(issue, []);
                    groupedRecords.get(issue).push(item);
                });

            list.innerHTML = [...groupedRecords.entries()]
                .sort(([issueA], [issueB]) => issueA.localeCompare(issueB))
                .map(([issue, items]) => `
                    <section class="issue-group">
                        <h3 class="issue-group-heading">${escapeHtml(issue)} <span class="group-count">${items.length} ${items.length === 1 ? 'record' : 'records'}</span></h3>
                        <div class="compliance-list">${items.map(item => {
                const initials = getInitials(item.seller);
                const statusLabel = getStatusLabel(item.status);
                const statusClass = getStatusClass(item.status);
                const detailUrl = `${complianceBaseUrl}/${item.id}`;

                return `
                    <article class="compliance-card">

                        <div class="compliance-card-header">

                            <div class="seller-info">

                                <div class="seller-avatar">
                                    ${escapeHtml(initials)}
                                </div>

                                <div>
                                    <h3 class="seller-name">
                                        <a class="detail-link" href="${detailUrl}">
                                        ${escapeHtml(item.seller)}
                                        </a>
                                    </h3>

                                    <p class="seller-email">
                                        ${escapeHtml(item.email)}
                                    </p>
                                </div>

                            </div>

                            <span class="status-badge ${statusClass}">
                                ${escapeHtml(statusLabel)}
                            </span>

                        </div>

                        <div class="compliance-details">

                            <div>
                                <p class="detail-label">
                                    Business Category
                                </p>

                                <p class="detail-value">
                                    ${escapeHtml(item.category)}
                                </p>
                            </div>

                            <div>
                                <p class="detail-label">
                                    Product Status
                                </p>

                                <p class="detail-value">
                                    ${escapeHtml(item.productStatus)}
                                </p>
                            </div>

                            <div>
                                <p class="detail-label">
                                    Product Collection
                                </p>

                                <p class="detail-value">
                                    ${escapeHtml(item.product)}
                                </p>
                            </div>

                            <div>
                                <p class="detail-label">
                                    Last Checked
                                </p>

                                <p class="detail-value">
                                    ${escapeHtml(item.lastChecked)}
                                </p>
                            </div>

                            <div>
                                <p class="detail-label">Photo matches product name</p>
                                <p class="detail-value">${item.imageMatchesProduct ? 'Pass' : 'Fail · review required'}</p>
                            </div>

                            <div>
                                <p class="detail-label">Sensitive content</p>
                                <p class="detail-value">${item.sensitiveImage ? 'Flagged · reject' : 'No issue found'}</p>
                            </div>

                            <div>
                                <p class="detail-label">Seller ownership</p>
                                <p class="detail-value">${item.sellerOwnsProduct ? 'Verified' : 'Not verified'}</p>
                            </div>

                        </div>

                        <div class="compliance-note">
                            <i class="fas fa-info-circle"></i>
                            ${escapeHtml(item.note)}
                        </div>

                        <div class="card-actions">

                            <a class="action-btn" href="${detailUrl}">
                                <i class="fas fa-eye"></i>
                                View Details
                            </a>

                            ${
                                item.status !== "compliant"
                                ? `
                                    <button
                                        type="button"
                                        class="action-btn approve"
                                        onclick="updateStatus(${item.id}, 'compliant')"
                                    >
                                        <i class="fas fa-check"></i>
                                        Approve
                                    </button>
                                `
                                : ""
                            }

                            ${
                                item.status !== "violation"
                                ? `
                                    <button
                                        type="button"
                                        class="action-btn reject"
                                        onclick="updateStatus(${item.id}, 'violation')"
                                    >
                                        <i class="fas fa-flag"></i>
                                        Flag
                                    </button>
                                `
                                : ""
                            }

                        </div>

                    </article>
                `;
            }).join("")}</div>
                    </section>
                `).join("");
        }

        function filterCompliance() {
            const searchValue = document
                .getElementById("searchComp")
                .value
                .toLowerCase()
                .trim();

            const selectedStatus = document
                .getElementById("statusFilter")
                .value;

            const filteredData = complianceData.filter(item => {
                const matchesSearch =
                    item.seller.toLowerCase().includes(searchValue) ||
                    item.email.toLowerCase().includes(searchValue) ||
                    item.category.toLowerCase().includes(searchValue) ||
                    item.product.toLowerCase().includes(searchValue) ||
                    (item.issueType || '').toLowerCase().includes(searchValue);

                const matchesStatus =
                    selectedStatus === "all" ||
                    item.status === selectedStatus;

                const matchesIssue = selectedIssueType === "all" ||
                    (item.issueType || "Uncategorized issue") === selectedIssueType;

                return matchesSearch && matchesStatus && matchesIssue;
            });

            renderCompliance(filteredData);
        }

        function clearFilters() {
            document.getElementById("searchComp").value = "";
            document.getElementById("statusFilter").value = "all";
            document.getElementById("headerSearch").value = "";

            renderCompliance(complianceData);
        }

        function syncHeaderSearch(value) {
            document.getElementById("searchComp").value = value;
            filterCompliance();
        }

        function updateStatus(id, newStatus) {
            const item = complianceData.find(record => record.id === id);

            if (!item) {
                return;
            }

            if (newStatus === "compliant") {
                const failedChecks = [];
                if (item.imageMatchesProduct !== true) failedChecks.push("photo does not match the product name");
                if (item.sensitiveImage === true) failedChecks.push("photo contains sensitive or prohibited content");
                if (item.sellerOwnsProduct !== true) failedChecks.push("seller ownership is not verified");
                if (failedChecks.length) {
                    showNotification(`Cannot approve: ${failedChecks.join("; ")}.`);
                    return;
                }
            }

            const statusLabel = getStatusLabel(newStatus);

            const confirmed = confirm(
                `Change ${item.seller}'s status to ${statusLabel}?`
            );

            if (!confirmed) {
                return;
            }

            item.status = newStatus;

            if (newStatus === "compliant") {
                item.productStatus = "Approved";
                item.note = "The seller has been marked as compliant by the administrator.";
            } else if (newStatus === "violation") {
                item.productStatus = "Flagged";
                item.note = "This seller has been flagged for additional compliance review.";
            }

            filterCompliance();

            showNotification(
                `${item.seller} status updated to ${statusLabel}.`
            );
        }

        function renderComplianceDetail(id) {
            const item = complianceData.find(record => record.id === id);
            const content = document.querySelector('.content-wrapper');

            if (!item) {
                content.innerHTML = `
                    <a class="detail-back-link" href="${complianceBaseUrl}"><i class="fas fa-arrow-left"></i> Back to Compliance Management</a>
                    <div class="empty-state"><i class="fas fa-folder-open"></i><p>Compliance record not found.</p></div>
                `;
                return;
            }

            const imageUrl = getSafeProductImage(item.productImage);
            const photoContent = imageUrl
                ? `<img src="${escapeHtml(imageUrl)}" alt="Submitted photo for ${escapeHtml(item.product)}">`
                : `<div class="issue-photo-empty"><i class="fas fa-image" style="font-size:30px;display:block;margin-bottom:10px"></i>No uploaded product photo is attached to this sample record.</div>`;

            content.innerHTML = `
                <a class="detail-back-link" href="${complianceBaseUrl}"><i class="fas fa-arrow-left"></i> Back to Compliance Management</a>
                <article class="issue-detail-page">
                    <header class="issue-detail-header">
                        <div>
                            <h2>${escapeHtml(item.issueType || 'Compliance review')}</h2>
                            <p>${escapeHtml(item.seller)} · ${escapeHtml(item.email)}</p>
                        </div>
                        <span class="status-badge ${getStatusClass(item.status)}">${getStatusLabel(item.status)}</span>
                    </header>
                    <div class="issue-detail-grid">
                        <section>
                            <h3 class="section-title" style="margin-bottom:12px">Submitted product photo</h3>
                            <div class="issue-photo-panel">${photoContent}</div>
                            ${imageUrl ? `<a class="detail-back-link" href="${escapeHtml(imageUrl)}" target="_blank" rel="noopener">Open original image <i class="fas fa-arrow-up-right-from-square"></i></a>` : ''}
                        </section>
                        <section>
                            <h3 class="section-title" style="margin-bottom:12px">Review details</h3>
                            <div class="issue-detail-fields">
                                <div class="issue-field"><p class="detail-label">Product name</p><p class="detail-value">${escapeHtml(item.product)}</p></div>
                                <div class="issue-field"><p class="detail-label">Registered category</p><p class="detail-value">${escapeHtml(item.category)}</p></div>
                                <div class="issue-field"><p class="detail-label">Photo/name match</p><p class="detail-value">${item.imageMatchesProduct ? 'Pass' : 'Fail · mismatched'}</p></div>
                                <div class="issue-field"><p class="detail-label">Sensitive content</p><p class="detail-value">${item.sensitiveImage ? 'Flagged' : 'No issue found'}</p></div>
                                <div class="issue-field"><p class="detail-label">Seller ownership</p><p class="detail-value">${item.sellerOwnsProduct ? 'Verified' : 'Not verified'}</p></div>
                                <div class="issue-field"><p class="detail-label">Product status</p><p class="detail-value">${escapeHtml(item.productStatus)}</p></div>
                                <div class="issue-field"><p class="detail-label">Last checked</p><p class="detail-value">${escapeHtml(item.lastChecked)}</p></div>
                                <div class="issue-field"><p class="detail-label">Issue group</p><p class="detail-value">${escapeHtml(item.issueType || 'Uncategorized issue')}</p></div>
                            </div>
                            <div class="issue-detail-note"><strong>Review note</strong><br>${escapeHtml(item.note)}</div>
                        </section>
                    </div>
                </article>
            `;
        }

        function getSafeProductImage(value) {
            if (typeof value !== 'string' || !value.trim()) return null;
            try {
                const url = new URL(value, window.location.origin);
                if (url.origin !== window.location.origin && url.protocol !== 'https:') return null;
                return url.href;
            } catch (error) {
                return null;
            }
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
                <i class="fas fa-check-circle"></i>
                <span>${escapeHtml(message)}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add("hide");

                setTimeout(() => {
                    notification.remove();
                }, 250);
            }, 3000);
        }

        document.addEventListener("DOMContentLoaded", () => {
            const detailRecordId = @json($detailRecordId ?? null);
            if (detailRecordId !== null) {
                renderComplianceDetail(Number(detailRecordId));
            } else {
                renderIssueTypeButtons();
                renderCompliance(complianceData);
            }
        });
    </script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>

</html>
