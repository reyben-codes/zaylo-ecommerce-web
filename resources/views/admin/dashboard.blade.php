
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Admin Dashboard</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --cream: #faf7f2;
            --white: #ffffff;
            --dark: #1a1714;
            --text: #1e1e1e;
            --muted: #6b5f54;
            --brown: #b28b6f;
            --border: #ece4db;
            --light-brown: #f5f0ea;
            --green: #2d7d46;
            --red: #c0392b;
            --orange: #e67e22;
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
            overflow-x: hidden;
            background: var(--cream);
            color: var(--text);
            font-family: "Inter", sans-serif;
            line-height: 1.4;
        }

        a {
            color: inherit;
        }

        button,
        input {
            font-family: inherit;
        }

        button {
            cursor: pointer;
        }

        .container {
            width: 100%;
            min-height: 100vh;
            background: var(--cream);
        }

        /* ================================
           HEADER
        ================================= */

        .navbar {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 72px;
            padding: 0 32px;
            background: var(--white);
            border-top: 1px solid #6f9d73;
            border-bottom: 1px solid var(--border);
        }

        .logo {
            position: absolute;
            top: 50%;
            left: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transform: translate(-50%, -50%);
        }

        .logo img {
            display: block;
            width: 78px;
            height: auto;
            object-fit: contain;
            transition: transform 0.2s ease;
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        .nav-actions {
            position: absolute;
            right: 32px;
            display: flex;
            align-items: center;
        }

        /* LONGER SEARCH BAR */

        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 360px;
            height: 42px;
            padding: 0 17px;
            background: #f5f2ee;
            border: 1px solid #e5ddd5;
            border-radius: 24px;
            transition: border-color 0.2s ease,
                        box-shadow 0.2s ease;
        }

        .search-wrapper:focus-within {
            border-color: var(--brown);
            box-shadow: 0 0 0 3px rgba(178, 139, 111, 0.12);
        }

        .search-icon {
            color: #342b25;
            font-size: 0.85rem;
        }

        .search-wrapper input {
            width: 100%;
            min-width: 0;
            padding: 5px 0;
            border: none;
            outline: none;
            background: transparent;
            color: var(--text);
            font-size: 0.78rem;
        }

        .search-wrapper input::placeholder {
            color: #988d83;
        }

        /* ================================
           DASHBOARD LAYOUT
        ================================= */

        .dashboard-wrapper {
            display: flex;
            width: 100%;
            min-height: calc(100vh - 72px);
        }

        /* ================================
           SIDEBAR
        ================================= */

        .sidebar {
            width: 245px;
            min-height: calc(100vh - 72px);
            flex-shrink: 0;
            padding: 24px 0;
            background: var(--white);
            border-right: 1px solid var(--border);
        }

        .sidebar-menu {
            padding: 0 16px;
            list-style: none;
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
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: var(--light-brown);
            color: var(--text);
        }

        .sidebar-menu a.active {
            background: var(--dark);
            color: var(--white);
        }

        .sidebar-menu a i {
            width: 18px;
            text-align: center;
            font-size: 0.85rem;
        }

        .sidebar-menu .badge {
            margin-left: auto;
            padding: 2px 8px;
            border-radius: 10px;
            background: var(--brown);
            color: var(--white);
            font-size: 0.5rem;
            font-weight: 600;
        }

        .sidebar-divider {
            height: 1px;
            margin: 14px 16px;
            background: var(--border);
        }

        /* ================================
           MAIN CONTENT
        ================================= */

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 34px 42px;
            background: var(--cream);
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 32px;
        }

        .page-header h1 {
            color: var(--dark);
            font-family: "Playfair Display", serif;
            font-size: 1.9rem;
            font-weight: 600;
        }

        .subtitle {
            margin-top: 5px;
            color: var(--muted);
            font-size: 0.85rem;
        }

        .subtitle strong {
            color: var(--dark);
            font-weight: 600;
        }

        /* ================================
           STATISTICS
        ================================= */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            padding: 22px 24px;
            background: var(--white);
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: var(--brown);
            transform: translateY(-2px);
        }

        .stat-icon {
            margin-bottom: 10px;
            color: var(--brown);
            font-size: 1.3rem;
        }

        .stat-card h3 {
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 0.65rem;
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .stat-card .number {
            color: var(--dark);
            font-size: 1.8rem;
            font-weight: 600;
        }

        .trend {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 0.6rem;
        }

        .trend.up {
            color: var(--green);
        }

        .trend.down {
            color: var(--red);
        }

        /* ================================
           RECENT ACTIVITY
        ================================= */

        .notifications-section {
            margin-bottom: 32px;
            padding: 24px 28px;
            background: var(--white);
            border: 1px solid var(--border);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .section-header h2 {
            color: var(--dark);
            font-family: "Playfair Display", serif;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .badge-new {
            display: inline-block;
            margin-left: 5px;
            padding: 3px 10px;
            border-radius: 12px;
            background: var(--red);
            color: var(--white);
            font-family: "Inter", sans-serif;
            font-size: 0.55rem;
            font-weight: 600;
            vertical-align: middle;
        }

        .view-all {
            color: var(--muted);
            font-size: 0.7rem;
            text-decoration: none;
        }

        .view-all:hover {
            color: var(--dark);
        }

        .notification-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 13px 0;
            border-bottom: 1px solid #f5f0ea;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notif-info {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1;
        }

        .notif-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border-radius: 50%;
            font-size: 0.75rem;
        }

        .notif-icon.pending {
            background: #f5e0c0;
            color: var(--orange);
        }

        .notif-icon.approved {
            background: #d6e4d0;
            color: var(--green);
        }

        .notif-icon.rejected {
            background: #f5e0e0;
            color: var(--red);
        }

        .notif-icon.info {
            background: #e0e8f5;
            color: #2c6b9e;
        }

        .notif-text {
            color: var(--dark);
            font-size: 0.8rem;
        }

        .notif-text .highlight {
            font-weight: 600;
        }

        .notif-time {
            flex-shrink: 0;
            color: var(--muted);
            font-size: 0.65rem;
        }

        .notif-action button {
            padding: 5px 13px;
            background: transparent;
            border: 1px solid var(--border);
            color: var(--dark);
            font-size: 0.6rem;
            transition: all 0.2s ease;
        }

        .notif-action button:hover {
            background: var(--dark);
            border-color: var(--dark);
            color: var(--white);
        }

        /* ================================
           QUICK ACTIONS
        ================================= */

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
        }

        .quick-action {
            padding: 25px 20px;
            background: var(--white);
            border: 1px solid var(--border);
            color: var(--dark);
            text-align: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .quick-action:hover {
            border-color: var(--brown);
            transform: translateY(-2px);
        }

        .quick-action i {
            display: block;
            margin-bottom: 11px;
            color: var(--brown);
            font-size: 1.8rem;
        }

        .quick-action span {
            display: block;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .quick-action .action-desc {
            margin-top: 5px;
            color: var(--muted);
            font-size: 0.6rem;
            font-weight: 400;
        }

        /* ================================
           TOAST NOTIFICATION
        ================================= */

        .sable-notification {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            max-width: calc(100% - 48px);
            padding: 14px 24px;
            background: var(--dark);
            border: 1px solid #3b342e;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            color: var(--white);
            font-size: 0.8rem;
            animation: slideUp 0.3s ease;
        }

        .sable-notification.hide {
            animation: slideDown 0.3s ease forwards;
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

        /* ================================
           RESPONSIVE DESIGN
        ================================= */

        @media (max-width: 1200px) {
            .main-content {
                padding: 28px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .quick-actions {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .search-wrapper {
                width: 300px;
            }
        }

        @media (max-width: 900px) {
            .navbar {
                height: 68px;
                padding: 0 20px;
            }

            .logo img {
                width: 72px;
            }

            .nav-actions {
                right: 20px;
            }

            .search-wrapper {
                width: 280px;
            }

            .dashboard-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
                padding: 12px 0;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-menu {
                display: flex;
                gap: 5px;
                overflow-x: auto;
                padding: 0 16px;
            }

            .sidebar-menu li {
                flex-shrink: 0;
                margin-bottom: 0;
            }

            .sidebar-menu a {
                padding: 9px 13px;
                white-space: nowrap;
            }

            .sidebar-menu .badge,
            .sidebar-divider {
                display: none;
            }

            .main-content {
                padding: 24px 20px;
            }
        }

        @media (max-width: 640px) {
            .navbar {
                height: 64px;
            }

            .logo img {
                width: 68px;
            }

            .nav-actions {
                right: 16px;
            }

            .search-wrapper {
                width: 200px;
                height: 36px;
                padding: 0 12px;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card .number {
                font-size: 1.35rem;
            }

            .main-content {
                padding: 20px 12px;
            }

            .page-header {
                align-items: flex-start;
                margin-bottom: 22px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .subtitle {
                font-size: 0.75rem;
            }

            .notifications-section {
                padding: 18px 16px;
            }

            .section-header {
                align-items: flex-start;
            }

            .section-header h2 {
                font-size: 1.05rem;
            }

            .notification-item {
                align-items: flex-start;
                flex-direction: column;
                gap: 9px;
            }

            .notif-time {
                margin-left: 46px;
            }

            .notif-action {
                width: 100%;
            }

            .notif-action button {
                width: 100%;
            }

            .quick-actions {
                gap: 10px;
            }

            .quick-action {
                padding: 18px 10px;
            }

            .quick-action i {
                font-size: 1.4rem;
            }

            .quick-action span {
                font-size: 0.7rem;
            }

            .quick-action .action-desc {
                font-size: 0.55rem;
            }
        }

        @media (max-width: 400px) {
            .logo img {
                width: 62px;
            }

            .search-wrapper {
                width: 160px;
                padding: 0 10px;
            }

            .stats-grid {
                gap: 8px;
            }

            .stat-card {
                padding: 12px;
            }

            .stat-card h3 {
                font-size: 0.55rem;
            }

            .stat-card .number {
                font-size: 1.1rem;
            }

            .trend {
                font-size: 0.52rem;
            }

            .quick-action i {
                font-size: 1.2rem;
            }

            .quick-action span {
                font-size: 0.62rem;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>

    <div class="container">

        <!-- HEADER -->

        @include('admin.partials.header', ['searchId' => 'globalSearch', 'searchPlaceholder' => 'Search dashboard...', 'searchOnInput' => ''])


        <!-- DASHBOARD WRAPPER -->

        <div class="dashboard-wrapper">


            <!-- SIDEBAR -->

            @include('admin.partials.sidebar')


            <!-- MAIN CONTENT -->

            <main class="main-content">


                <!-- PAGE HEADER -->

                <div class="page-header">

                    <div>

                        <h1>Admin Dashboard</h1>

                        <p class="subtitle">

                            Welcome back,
                            <strong>Administrator</strong>!

                            Here's your platform overview.

                        </p>

                    </div>

                </div>


                <!-- STATISTICS -->

                <section class="stats-grid">

                    <div class="stat-card">

                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>

                        <h3>Total Users</h3>

                        <div class="number">
                            1,284
                        </div>

                        <span class="trend up">
                            ↑ 24 new this week
                        </span>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon">
                            <i class="fas fa-store"></i>
                        </div>

                        <h3>Sellers</h3>

                        <div class="number">
                            156
                        </div>

                        <span class="trend up">
                            ↑ 5 new this week
                        </span>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>

                        <h3>Orders</h3>

                        <div class="number">
                            342
                        </div>

                        <span class="trend up">
                            ↑ 12% this month
                        </span>

                    </div>


                    <div class="stat-card">

                        <div class="stat-icon">
                            <i class="fas fa-coins"></i>
                        </div>

                        <h3>Revenue</h3>

                        <div class="number">
                            ₱48,250
                        </div>

                        <span class="trend up">
                            ↑ 8% this month
                        </span>

                    </div>

                </section>


                <!-- RECENT ACTIVITY -->

                <section class="notifications-section">

                    <div class="section-header">

                        <h2>

                            Recent Activity

                            <span class="badge-new">
                                8 New
                            </span>

                        </h2>

                        <a href="{{ route('admin.reports') }}"
                           class="view-all">

                            View All →

                        </a>

                    </div>


                    <div class="notification-item">

                        <div class="notif-info">

                            <div class="notif-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>

                            <div class="notif-text">

                                <span class="highlight">
                                    Juan Dela Cruz
                                </span>

                                requested to register as a
                                <strong>Seller</strong>

                            </div>

                        </div>

                        <div class="notif-time">
                            5 mins ago
                        </div>

                        <div class="notif-action">

                            <button type="button"
                                    onclick="showNotification('Registration review opened.')">

                                Review

                            </button>

                        </div>

                    </div>


                    <div class="notification-item">

                        <div class="notif-info">

                            <div class="notif-icon info">
                                <i class="fas fa-info-circle"></i>
                            </div>

                            <div class="notif-text">

                                New order
                                <span class="highlight">
                                    #SBL-2024-001
                                </span>

                                placed by
                                <strong>Maria Reyes</strong>

                            </div>

                        </div>

                        <div class="notif-time">
                            15 mins ago
                        </div>

                        <div class="notif-action">

                            <button type="button"
                                    onclick="showNotification('Order details opened.')">

                                View

                            </button>

                        </div>

                    </div>


                    <div class="notification-item">

                        <div class="notif-info">

                            <div class="notif-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>

                            <div class="notif-text">

                                <span class="highlight">
                                    Pedro Santos
                                </span>

                                requested to register as a
                                <strong>Sorting Center</strong>

                            </div>

                        </div>

                        <div class="notif-time">
                            1 hour ago
                        </div>

                        <div class="notif-action">

                            <button type="button"
                                    onclick="showNotification('Registration review opened.')">

                                Review

                            </button>

                        </div>

                    </div>


                    <div class="notification-item">

                        <div class="notif-info">

                            <div class="notif-icon approved">
                                <i class="fas fa-check-circle"></i>
                            </div>

                            <div class="notif-text">

                                <span class="highlight">
                                    Ana Lopez
                                </span>

                                registration as
                                <strong>Seller</strong>

                                was approved

                            </div>

                        </div>

                        <div class="notif-time">
                            2 hours ago
                        </div>

                        <div class="notif-action">

                            <button type="button"
                                    onclick="showNotification('User profile opened.')">

                                Profile

                            </button>

                        </div>

                    </div>


                    <div class="notification-item">

                        <div class="notif-info">

                            <div class="notif-icon rejected">
                                <i class="fas fa-times-circle"></i>
                            </div>

                            <div class="notif-text">

                                <span class="highlight">
                                    Carlos Garcia
                                </span>

                                registration as
                                <strong>Seller</strong>

                                was rejected

                            </div>

                        </div>

                        <div class="notif-time">
                            3 hours ago
                        </div>

                        <div class="notif-action">

                            <button type="button"
                                    onclick="showNotification('Rejection details opened.')">

                                Details

                            </button>

                        </div>

                    </div>

                </section>


                <!-- QUICK ACTIONS -->

                <section class="quick-actions">


                    <a href="{{ route('admin.registrations') }}"
                       class="quick-action">

                        <i class="fas fa-user-plus"></i>

                        <span>
                            Pending Registrations
                        </span>

                        <span class="action-desc">
                            Review new account requests
                        </span>

                    </a>


                    <a href="{{ route('admin.disputes') }}"
                       class="quick-action">

                        <i class="fas fa-gavel"></i>

                        <span>
                            Disputes
                        </span>

                        <span class="action-desc">
                            Resolve customer issues
                        </span>

                    </a>


                    <a href="{{ route('admin.reports') }}"
                       class="quick-action">

                        <i class="fas fa-chart-bar"></i>

                        <span>
                            Reports
                        </span>

                        <span class="action-desc">
                            View platform analytics
                        </span>

                    </a>


                    <a href="{{ route('admin.settings') }}"
                       class="quick-action">

                        <i class="fas fa-cog"></i>

                        <span>
                            Settings
                        </span>

                        <span class="action-desc">
                            Manage platform settings
                        </span>

                    </a>

                </section>

            </main>

        </div>

    </div>


    <!-- JAVASCRIPT -->

    <script>

        /*
        |--------------------------------------------------------------------------
        | Toast Notification
        |--------------------------------------------------------------------------
        */

        function showNotification(message) {

            const existingNotification =
                document.querySelector(".sable-notification");

            if (existingNotification) {
                existingNotification.remove();
            }

            const notification =
                document.createElement("div");

            notification.className =
                "sable-notification";

            notification.textContent =
                message;

            document.body.appendChild(notification);

            setTimeout(() => {

                notification.classList.add("hide");

                setTimeout(() => {

                    notification.remove();

                }, 300);

            }, 3000);

        }


        /*
        |--------------------------------------------------------------------------
        | Global Search
        |--------------------------------------------------------------------------
        */

        const globalSearch =
            document.getElementById("globalSearch");

        if (globalSearch) {

            globalSearch.addEventListener("input", function () {

                const searchValue =
                    this.value.toLowerCase().trim();

                const notificationItems =
                    document.querySelectorAll(".notification-item");

                notificationItems.forEach((item) => {

                    const text =
                        item.textContent.toLowerCase();

                    item.style.display =
                        text.includes(searchValue)
                            ? "flex"
                            : "none";

                });

            });

        }

    </script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>

</html>