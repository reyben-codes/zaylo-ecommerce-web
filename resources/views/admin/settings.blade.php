<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Admin Settings</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>
        /* ========================================
           RESET
           ======================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background: #faf7f2;
            font-family: 'Inter', 'Helvetica Neue', sans-serif;
            color: #1e1e1e;
            line-height: 1.4;
        }

        /* ========================================
           MAIN CONTAINER
           ======================================== */

        .container {
            width: 100%;
            min-height: 100vh;
            background: #faf7f2;
        }

        /* ========================================
           HEADER
           ONLY LOGO + SEARCH BAR
           ======================================== */

        .navbar {
            position: relative;
            width: 100%;
            height: 64px;
            background: #ffffff;
            border-top: 1px solid #e6dfd7;
            border-bottom: 1px solid #e8e1da;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 52px;
            z-index: 1000;
        }

        /* Centered Logo */

        .logo {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);

            display: flex;
            align-items: center;
            justify-content: center;

            text-decoration: none;
            width: auto;
            height: 42px;
        }

        .logo img {
            display: block;
            width: 78px;
            height: auto;
            max-height: 34px;
            object-fit: contain;
        }

        .logo:hover {
            opacity: 0.85;
        }

        /* Right Search Bar */

        .search-wrapper {
            position: absolute;
            right: 52px;
            top: 50%;
            transform: translateY(-50%);

            width: 360px;
            height: 42px;

            display: flex;
            align-items: center;

            background: #f5f2ee;
            border: 1px solid #e3dbd3;
            border-radius: 24px;

            padding: 0 16px;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease,
                background 0.2s ease;
        }

        .search-wrapper:focus-within {
            background: #ffffff;
            border-color: #b8aa9c;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .search-icon {
            flex-shrink: 0;
            color: #403831;
            font-size: 0.85rem;
            margin-right: 11px;
        }

        .search-wrapper input {
            width: 100%;
            height: 100%;

            border: none;
            outline: none;

            background: transparent;

            font-family: 'Inter', sans-serif;
            font-size: 0.72rem;
            font-weight: 400;

            color: #1e1e1e;
        }

        .search-wrapper input::placeholder {
            color: #8f8378;
            opacity: 1;
        }

        /* ========================================
           DASHBOARD LAYOUT
           ======================================== */

        .dashboard-wrapper {
            display: flex;
            width: 100%;
            min-height: calc(100vh - 64px);
            background: #faf7f2;
        }

        /* ========================================
           SIDEBAR
           ======================================== */

        .sidebar {
            width: 240px;
            flex-shrink: 0;

            background: #ffffff;

            border-right: 1px solid #ece4db;

            padding: 24px 0;

            min-height: calc(100vh - 64px);
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

            padding: 10px 14px;

            color: #6b5f54;

            text-decoration: none;

            font-size: 0.75rem;
            font-weight: 400;

            letter-spacing: 0.02em;

            border-radius: 0;

            transition:
                background 0.15s ease,
                color 0.15s ease;
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

            font-size: 0.85rem;

            text-align: center;
        }

        .sidebar-menu .badge {
            margin-left: auto;

            background: #b28b6f;
            color: #ffffff;

            font-size: 0.5rem;
            font-weight: 500;

            padding: 2px 8px;

            border-radius: 10px;
        }

        .sidebar-divider {
            height: 1px;

            background: #ece4db;

            margin: 12px 16px;
        }

        /* ========================================
           MAIN CONTENT
           ======================================== */

        .main-content {
            flex: 1;

            min-width: 0;

            padding: 32px 40px;

            background: #faf7f2;
        }

        /* ========================================
           PAGE HEADER
           ======================================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 24px;

            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;

            font-size: 1.8rem;
            font-weight: 600;

            color: #1a1714;
        }

        .page-header .subtitle {
            font-size: 0.85rem;

            color: #6b5f54;

            margin-top: 4px;
        }

        /* ========================================
           SETTINGS GRID
           ======================================== */

        .settings-grid {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 24px;
        }

        .settings-card {
            background: #ffffff;

            border: 1px solid #ece4db;

            padding: 24px 28px;
        }

        .settings-card.full-width {
            grid-column: 1 / -1;
        }

        .settings-card .card-title {
            font-family: 'Playfair Display', serif;

            font-size: 1.1rem;
            font-weight: 600;

            color: #1a1714;

            margin-bottom: 4px;
        }

        .settings-card .card-description {
            font-size: 0.75rem;

            color: #6b5f54;

            margin-bottom: 16px;
        }

        /* ========================================
           FORM ELEMENTS
           ======================================== */

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;

            font-size: 0.7rem;
            font-weight: 500;

            color: #1a1714;

            margin-bottom: 6px;

            letter-spacing: 0.02em;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;

            padding: 10px 14px;

            border: 1px solid #ece4db;

            background: #ffffff;

            color: #1e1e1e;

            font-family: 'Inter', sans-serif;

            font-size: 0.8rem;

            outline: none;

            transition:
                border-color 0.15s ease,
                box-shadow 0.15s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #1a1714;

            box-shadow: 0 0 0 2px rgba(26, 23, 20, 0.04);
        }

        .form-group textarea {
            resize: vertical;

            min-height: 80px;
        }

        .form-row {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 16px;
        }

        /* ========================================
           SAVE BUTTON
           ======================================== */

        .btn-save {
            padding: 10px 32px;

            background: #1a1714;
            color: #ffffff;

            border: none;

            font-family: 'Inter', sans-serif;

            font-size: 0.7rem;
            font-weight: 500;

            text-transform: uppercase;

            letter-spacing: 0.04em;

            cursor: pointer;

            transition:
                background 0.15s ease,
                transform 0.15s ease;
        }

        .btn-save:hover {
            background: #b28b6f;
        }

        .btn-save:active {
            transform: translateY(1px);
        }

        .btn-save:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ========================================
           TOGGLE SWITCH
           ======================================== */

        .toggle-group {
            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 10px 0;

            border-bottom: 1px solid #f5f0ea;
        }

        .toggle-group:last-of-type {
            border-bottom: none;
        }

        .toggle-info {
            padding-right: 20px;
        }

        .toggle-label {
            font-size: 0.8rem;

            font-weight: 500;

            color: #1a1714;
        }

        .toggle-desc {
            font-size: 0.65rem;

            color: #6b5f54;

            margin-top: 2px;
        }

        .toggle-switch {
            position: relative;

            width: 44px;
            height: 24px;

            flex-shrink: 0;

            background: #ece4db;

            border-radius: 12px;

            cursor: pointer;

            transition: background 0.15s ease;
        }

        .toggle-switch.active {
            background: #1a1714;
        }

        .toggle-slider {
            position: absolute;

            top: 2px;
            left: 2px;

            width: 20px;
            height: 20px;

            background: #ffffff;

            border-radius: 50%;

            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);

            transition: left 0.15s ease;
        }

        .toggle-switch.active .toggle-slider {
            left: 22px;
        }

        /* ========================================
           NOTIFICATION
           ======================================== */

        .zaylo-notification {
            position: fixed;

            right: 24px;
            bottom: 24px;

            z-index: 9999;

            background: #1a1714;

            color: #ffffff;

            padding: 14px 24px;

            font-family: 'Inter', sans-serif;

            font-size: 0.8rem;

            border: none;

            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);

            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(100px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes slideDown {
            from {
                transform: translateY(0);
                opacity: 1;
            }

            to {
                transform: translateY(100px);
                opacity: 0;
            }
        }

        /* ========================================
           RESPONSIVE
           ======================================== */

        @media (max-width: 1100px) {

            .search-wrapper {
                right: 28px;
                width: 300px;
            }

            .main-content {
                padding: 28px 28px;
            }
        }

        @media (max-width: 900px) {

            .search-wrapper {
                width: 260px;
            }

            .sidebar {
                width: 220px;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }

            .settings-card.full-width {
                grid-column: 1;
            }
        }

        @media (max-width: 760px) {

            .navbar {
                height: 62px;
                padding: 0 16px;
            }

            .logo img {
                width: 70px;
            }

            .search-wrapper {
                right: 16px;

                width: 220px;
                height: 38px;
            }

            .search-wrapper input {
                font-size: 0.68rem;
            }

            .dashboard-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;

                min-height: auto;

                border-right: none;
                border-bottom: 1px solid #ece4db;

                padding: 12px 0;
            }

            .sidebar-menu {
                display: flex;

                overflow-x: auto;

                gap: 4px;

                padding: 0 16px;
            }

            .sidebar-menu li {
                flex-shrink: 0;

                white-space: nowrap;
            }

            .sidebar-menu a {
                padding: 8px 14px;

                font-size: 0.7rem;
            }

            .sidebar-menu a .badge {
                display: none;
            }

            .sidebar-divider {
                display: none;
            }

            .main-content {
                padding: 24px 18px;
            }
        }

        @media (max-width: 560px) {

            .navbar {
                justify-content: center;
            }

            .logo {
                position: static;

                transform: none;

                height: 36px;
            }

            .logo img {
                width: 68px;
            }

            .search-wrapper {
                position: absolute;

                right: 12px;

                width: 42px;

                padding: 0 12px;

                overflow: hidden;

                cursor: pointer;
            }

            .search-wrapper input {
                display: none;
            }

            .search-icon {
                margin-right: 0;
            }

            .main-content {
                padding: 20px 12px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .settings-card {
                padding: 20px 18px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .toggle-group {
                align-items: flex-start;
                gap: 12px;
            }

            .btn-save {
                width: 100%;
            }

            .zaylo-notification {
                left: 12px;
                right: 12px;
                bottom: 12px;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>

<div class="container">

    <!-- ========================================
         HEADER
         ONLY LOGO + SEARCH
         ======================================== -->

    @include('admin.partials.header', ['searchId' => 'adminSearch', 'searchPlaceholder' => 'Search admin pages...', 'searchOnInput' => ''])


    <!-- ========================================
         DASHBOARD WRAPPER
         ======================================== -->

    <div class="dashboard-wrapper">

        <!-- ========================================
             ADMIN SIDEBAR
             ======================================== -->

        @include('admin.partials.sidebar')


        <!-- ========================================
             MAIN CONTENT
             ======================================== -->

        <main class="main-content">

            <!-- Page Header -->

            <div class="page-header">

                <div>

                    <h1>
                        Platform Settings
                    </h1>

                    <p class="subtitle">
                        Configure and manage platform settings
                    </p>

                </div>

            </div>


            <!-- ========================================
                 SETTINGS GRID
                 ======================================== -->

            <div class="settings-grid">


                <!-- ========================================
                     GENERAL SETTINGS
                     ======================================== -->

                <div class="settings-card full-width">

                    <h2 class="card-title">
                        General Settings
                    </h2>

                    <p class="card-description">
                        Basic platform configuration
                    </p>


                    <div class="form-row">

                        <div class="form-group">

                            <label for="siteName">
                                Platform Name
                            </label>

                            <input
                                type="text"
                                id="siteName"
                                value="ZAYLO"
                            >

                        </div>


                        <div class="form-group">

                            <label for="siteEmail">
                                Support Email
                            </label>

                            <input
                                type="email"
                                id="siteEmail"
                                value="support@zaylo.com"
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label for="siteDescription">
                            Platform Description
                        </label>

                        <textarea
                            id="siteDescription"
                        >ZAYLO - Premium fashion marketplace.</textarea>

                    </div>


                    <button
                        class="btn-save"
                        type="button"
                        onclick="saveSettings('general')"
                    >
                        Save General Settings
                    </button>

                </div>


                <!-- ========================================
                     COMMISSION SETTINGS
                     ======================================== -->

                <div class="settings-card">

                    <h2 class="card-title">
                        Commission Settings
                    </h2>

                    <p class="card-description">
                        Configure platform commission rates
                    </p>


                    <div class="form-group">

                        <label for="commissionRate">
                            Commission Rate (%)
                        </label>

                        <input
                            type="number"
                            id="commissionRate"
                            value="10"
                            min="0"
                            max="100"
                        >

                    </div>


                    <div class="form-group">

                        <label for="minCommission">
                            Minimum Commission (₱)
                        </label>

                        <input
                            type="number"
                            id="minCommission"
                            value="10"
                            min="0"
                        >

                    </div>


                    <button
                        class="btn-save"
                        type="button"
                        onclick="saveSettings('commission')"
                    >
                        Save Commission Settings
                    </button>

                </div>


                <!-- ========================================
                     PAYMENT SETTINGS
                     ======================================== -->

                <div class="settings-card">

                    <h2 class="card-title">
                        Payment Settings
                    </h2>

                    <p class="card-description">
                        Configure payment gateway
                    </p>


                    <div class="form-group">

                        <label for="paymentGateway">
                            Payment Gateway
                        </label>

                        <select id="paymentGateway">

                            <option value="paypal">
                                PayPal
                            </option>

                            <option
                                value="stripe"
                                selected
                            >
                                Stripe
                            </option>

                            <option value="paymongo">
                                PayMongo
                            </option>

                            <option value="gcash">
                                GCash
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="currency">
                            Currency
                        </label>

                        <select id="currency">

                            <option
                                value="PHP"
                                selected
                            >
                                PHP - Philippine Peso
                            </option>

                            <option value="USD">
                                USD - US Dollar
                            </option>

                            <option value="EUR">
                                EUR - Euro
                            </option>

                        </select>

                    </div>


                    <button
                        class="btn-save"
                        type="button"
                        onclick="saveSettings('payment')"
                    >
                        Save Payment Settings
                    </button>

                </div>


                <!-- ========================================
                     PLATFORM ANNOUNCEMENTS
                     ======================================== -->

                <div class="settings-card full-width">

                    <h2 class="card-title">
                        Platform Announcements
                    </h2>

                    <p class="card-description">
                        Post announcements for all users
                    </p>


                    <div class="form-group">

                        <label for="announcementTitle">
                            Announcement Title
                        </label>

                        <input
                            type="text"
                            id="announcementTitle"
                            placeholder="Enter announcement title"
                        >

                    </div>


                    <div class="form-group">

                        <label for="announcementContent">
                            Announcement Content
                        </label>

                        <textarea
                            id="announcementContent"
                            placeholder="Enter announcement details..."
                        ></textarea>

                    </div>


                    <button
                        class="btn-save"
                        type="button"
                        onclick="postAnnouncement()"
                    >
                        Post Announcement
                    </button>

                </div>


                <!-- ========================================
                     PLATFORM POLICIES
                     ======================================== -->

                <div class="settings-card full-width">

                    <h2 class="card-title">
                        Platform Policies
                    </h2>

                    <p class="card-description">
                        Update platform policies
                    </p>


                    <!-- Seller Verification -->

                    <div class="toggle-group">

                        <div class="toggle-info">

                            <div class="toggle-label">
                                Enable Seller Verification
                            </div>

                            <div class="toggle-desc">
                                Require sellers to verify their identity
                            </div>

                        </div>


                        <div
                            class="toggle-switch active"
                            onclick="toggleSwitch(this)"
                            role="switch"
                            aria-checked="true"
                            tabindex="0"
                        >

                            <div class="toggle-slider"></div>

                        </div>

                    </div>


                    <!-- Buyer Protection -->

                    <div class="toggle-group">

                        <div class="toggle-info">

                            <div class="toggle-label">
                                Enable Buyer Protection
                            </div>

                            <div class="toggle-desc">
                                Protect buyers from fraudulent sellers
                            </div>

                        </div>


                        <div
                            class="toggle-switch active"
                            onclick="toggleSwitch(this)"
                            role="switch"
                            aria-checked="true"
                            tabindex="0"
                        >

                            <div class="toggle-slider"></div>

                        </div>

                    </div>


                    <!-- Product Moderation -->

                    <div class="toggle-group">

                        <div class="toggle-info">

                            <div class="toggle-label">
                                Enable Product Moderation
                            </div>

                            <div class="toggle-desc">
                                Moderate products before publishing
                            </div>

                        </div>


                        <div
                            class="toggle-switch"
                            onclick="toggleSwitch(this)"
                            role="switch"
                            aria-checked="false"
                            tabindex="0"
                        >

                            <div class="toggle-slider"></div>

                        </div>

                    </div>


                    <!-- Dispute Resolution -->

                    <div class="toggle-group">

                        <div class="toggle-info">

                            <div class="toggle-label">
                                Enable Dispute Resolution
                            </div>

                            <div class="toggle-desc">
                                Allow buyers to open disputes
                            </div>

                        </div>


                        <div
                            class="toggle-switch active"
                            onclick="toggleSwitch(this)"
                            role="switch"
                            aria-checked="true"
                            tabindex="0"
                        >

                            <div class="toggle-slider"></div>

                        </div>

                    </div>


                    <button
                        class="btn-save"
                        type="button"
                        style="margin-top: 16px;"
                        onclick="saveSettings('policies')"
                    >
                        Save Policy Settings
                    </button>

                </div>

            </div>

        </main>

    </div>

</div>


<!-- ========================================
     JAVASCRIPT
     ======================================== -->

<script>

    /* ========================================
       TOGGLE SWITCH
       ======================================== */

    function toggleSwitch(element) {

        element.classList.toggle('active');

        const isActive =
            element.classList.contains('active');

        element.setAttribute(
            'aria-checked',
            isActive ? 'true' : 'false'
        );
    }


    /* ========================================
       SAVE SETTINGS
       ======================================== */

    function saveSettings(type) {

        const messages = {

            general:
                'General settings saved successfully!',

            commission:
                'Commission settings saved successfully!',

            payment:
                'Payment settings saved successfully!',

            policies:
                'Policy settings saved successfully!'

        };

        showNotification(
            messages[type] ||
            'Settings saved successfully!'
        );
    }


    /* ========================================
       POST ANNOUNCEMENT
       ======================================== */

    function postAnnouncement() {

        const titleElement =
            document.getElementById(
                'announcementTitle'
            );

        const contentElement =
            document.getElementById(
                'announcementContent'
            );


        const title =
            titleElement.value.trim();

        const content =
            contentElement.value.trim();


        if (!title || !content) {

            showNotification(
                'Please enter both title and content.'
            );

            return;
        }


        showNotification(
            'Announcement posted successfully!'
        );


        titleElement.value = '';

        contentElement.value = '';
    }


    /* ========================================
       NOTIFICATION SYSTEM
       ======================================== */

    function showNotification(message) {

        const existing =
            document.querySelector(
                '.zaylo-notification'
            );


        if (existing) {
            existing.remove();
        }


        const notification =
            document.createElement('div');


        notification.className =
            'zaylo-notification';


        notification.textContent =
            message;


        document.body.appendChild(
            notification
        );


        setTimeout(function () {

            notification.style.animation =
                'slideDown 0.3s ease';


            setTimeout(function () {

                notification.remove();

            }, 300);

        }, 3000);
    }


    /* ========================================
       ADMIN SEARCH
       ======================================== */

    const adminSearch =
        document.getElementById(
            'adminSearch'
        );


    if (adminSearch) {

        adminSearch.addEventListener(
            'keydown',
            function(event) {

                if (event.key !== 'Enter') {
                    return;
                }


                const query =
                    this.value.trim();


                if (!query) {
                    return;
                }


                showNotification(
                    'Searching admin pages for "' +
                    query +
                    '"...'
                );
            }
        );
    }


    /* ========================================
       TOGGLE KEYBOARD ACCESS
       ======================================== */

    document
        .querySelectorAll('.toggle-switch')
        .forEach(function(toggle) {

            toggle.addEventListener(
                'keydown',
                function(event) {

                    if (
                        event.key === 'Enter' ||
                        event.key === ' '
                    ) {

                        event.preventDefault();

                        toggleSwitch(this);
                    }
                }
            );

        });

</script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>
</html>