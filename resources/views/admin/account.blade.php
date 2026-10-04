<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Admin Account</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap"
        rel="stylesheet"
    >

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

        a {
            text-decoration: none;
        }

        button,
        input {
            font-family: 'Inter', sans-serif;
        }

        /* ========================================
           TOP GREEN LINE
        ======================================== */

        .top-line {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: #6f8f72;
            z-index: 2000;
        }

        /* ========================================
           ADMIN HEADER
        ======================================== */

        .navbar {
            position: fixed;
            top: 3px;
            left: 0;
            right: 0;

            height: 68px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #ffffff;

            border-bottom: 1px solid #e8e3dc;

            z-index: 1500;

            padding: 0 28px;
        }

        .header-logo {
            position: absolute;
            left: 50%;
            top: 50%;

            transform: translate(-50%, -50%);

            display: flex;
            align-items: center;
            justify-content: center;

            text-decoration: none;
        }

        .header-logo img {
            display: block;
            width: 82px;
            height: 82px;
            object-fit: contain;
        }

        .header-search {
            position: absolute;
            right: 28px;

            display: flex;
            align-items: center;

            width: 260px;
            height: 38px;

            background: #f4f2ee;

            border: 1px solid #e5e0d9;
            border-radius: 22px;

            padding: 0 14px;

            transition: 0.2s ease;
        }

        .header-search:focus-within {
            border-color: #6f8f72;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(111, 143, 114, 0.08);
        }

        .header-search i {
            color: #6b665f;
            font-size: 0.8rem;
            margin-right: 9px;
        }

        .header-search input {
            width: 100%;

            border: none;
            outline: none;

            background: transparent;

            color: #1e1e1e;

            font-size: 0.72rem;
            font-weight: 400;
        }

        .header-search input::placeholder {
            color: #99938b;
        }

        /* ========================================
           PAGE LAYOUT
        ======================================== */

        .dashboard-wrapper {
            display: flex;

            min-height: 100vh;

            padding-top: 71px;

            background: #faf7f2;
        }

        /* ========================================
           SIDEBAR
        ======================================== */

        .sidebar {
            position: fixed;
            left: 0;
            top: 71px;
            bottom: 0;

            width: 245px;

            background: #ffffff;

            border-right: 1px solid #e6e1da;

            padding: 24px 0;

            overflow-y: auto;

            z-index: 1200;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 14px;
        }

        .sidebar-menu li {
            margin-bottom: 3px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;

            gap: 12px;

            width: 100%;

            padding: 11px 14px;

            color: #6b665f;

            font-size: 0.75rem;
            font-weight: 500;

            letter-spacing: 0.01em;

            border-radius: 7px;

            transition:
                background 0.18s ease,
                color 0.18s ease,
                transform 0.18s ease;
        }

        .sidebar-menu a:hover {
            background: #f1f5f1;
            color: #26372a;
        }

        .sidebar-menu a.active {
            background: #e9f0e9;
            color: #47634d;
            font-weight: 600;
        }

        .sidebar-menu a i {
            width: 19px;

            text-align: center;

            font-size: 0.82rem;

            color: #7b827b;

            transition: 0.18s ease;
        }

        .sidebar-menu a.active i {
            color: #5d7c62;
        }

        .sidebar-menu a:hover i {
            color: #5d7c62;
        }

        .sidebar-menu .badge {
            margin-left: auto;

            min-width: 20px;

            padding: 2px 7px;

            background: #6f8f72;

            color: #ffffff;

            border-radius: 10px;

            font-size: 0.55rem;
            font-weight: 600;

            text-align: center;
        }

        .sidebar-divider {
            height: 1px;

            background: #e8e3dc;

            margin: 14px 8px;
        }

        /* ========================================
           MAIN ACCOUNT CONTENT
        ======================================== */

        .account-content {
            margin-left: 245px;

            width: calc(100% - 245px);

            padding: 36px 42px 50px;

            background: #faf7f2;
        }

        /* ========================================
           PAGE HEADER
        ======================================== */

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 26px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;

            font-size: 1.9rem;
            font-weight: 600;

            color: #1a1714;

            letter-spacing: -0.01em;
        }

        .page-header .subtitle {
            font-size: 0.82rem;

            color: #746c63;

            margin-top: 5px;
        }

        /* ========================================
           ACCOUNT SECTIONS
        ======================================== */

        .account-sections {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 22px;
        }

        .account-card {
            background: #ffffff;

            border: 1px solid #e7e1d9;

            padding: 25px 28px;

            transition:
                border-color 0.18s ease,
                box-shadow 0.18s ease;
        }

        .account-card:hover {
            border-color: #d8d0c7;

            box-shadow: 0 5px 18px rgba(38, 32, 26, 0.035);
        }

        .account-card.full-width {
            grid-column: 1 / -1;
        }

        .account-card .card-title {
            font-family: 'Playfair Display', serif;

            font-size: 1.1rem;
            font-weight: 600;

            color: #1a1714;

            margin-bottom: 4px;
        }

        .account-card .card-description {
            font-size: 0.75rem;

            color: #746c63;

            margin-bottom: 17px;
        }

        /* ========================================
           INFORMATION ROWS
        ======================================== */

        .info-row {
            display: flex;

            justify-content: space-between;
            align-items: center;

            gap: 20px;

            padding: 11px 0;

            border-bottom: 1px solid #f1ede8;

            font-size: 0.78rem;
        }

        .info-row:last-of-type {
            border-bottom: none;
        }

        .info-row .label {
            color: #756d64;
        }

        .info-row .value {
            color: #1a1714;

            font-weight: 500;

            text-align: right;
        }

        /* ========================================
           BUTTONS
        ======================================== */

        .btn-edit {
            margin-top: 17px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            gap: 7px;

            padding: 9px 18px;

            background: transparent;

            border: 1px solid #dcd5cd;

            color: #1a1714;

            font-size: 0.64rem;
            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 0.045em;

            cursor: pointer;

            transition:
                background 0.18s ease,
                border-color 0.18s ease,
                color 0.18s ease;
        }

        .btn-edit:hover {
            background: #1a1714;
            border-color: #1a1714;
            color: #ffffff;
        }

        /* ========================================
           DANGER ZONE
        ======================================== */

        .danger-card {
            border-color: #c98379 !important;
        }

        .danger-card:hover {
            border-color: #b76559 !important;
        }

        .danger-title {
            color: #a94238 !important;
        }

        .danger-button {
            border-color: #c98379;

            color: #a94238;
        }

        .danger-button:hover {
            background: #a94238;
            border-color: #a94238;
            color: #ffffff;
        }

        .danger-note {
            font-size: 0.65rem;

            color: #746c63;

            margin-top: 9px;
        }

        /* ========================================
           NOTIFICATION
        ======================================== */

        .zaylo-notification {
            position: fixed;

            bottom: 24px;
            right: 24px;

            max-width: 360px;

            background: #1a1714;

            color: #ffffff;

            padding: 14px 20px;

            font-size: 0.78rem;

            border-left: 3px solid #6f8f72;

            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);

            z-index: 9999;

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
           RESPONSIVE — TABLET
        ======================================== */

        @media (max-width: 1100px) {

            .header-search {
                width: 220px;
                right: 20px;
            }

            .account-content {
                padding: 32px 28px 45px;
            }

            .account-sections {
                gap: 18px;
            }
        }

        /* ========================================
           RESPONSIVE — MOBILE SIDEBAR
        ======================================== */

        @media (max-width: 820px) {

            .navbar {
                justify-content: center;
            }

            .header-logo img {
                width: 70px;
                height: 70px;
            }

            .header-search {
                position: absolute;

                right: 14px;

                width: 190px;
                height: 35px;
            }

            .sidebar {
                position: fixed;

                top: 71px;
                left: 0;
                right: 0;

                width: 100%;
                height: auto;
                min-height: auto;

                padding: 10px 0;

                border-right: none;
                border-bottom: 1px solid #e6e1da;

                overflow-x: auto;
                overflow-y: hidden;
            }

            .sidebar-menu {
                display: flex;

                align-items: center;

                gap: 4px;

                padding: 0 12px;

                min-width: max-content;
            }

            .sidebar-menu li {
                margin-bottom: 0;
            }

            .sidebar-menu a {
                width: auto;

                white-space: nowrap;

                padding: 9px 12px;

                font-size: 0.68rem;
            }

            .sidebar-menu a i {
                font-size: 0.75rem;
            }

            .sidebar-menu .badge {
                display: none;
            }

            .sidebar-divider {
                display: none;
            }

            .account-content {
                margin-left: 0;

                width: 100%;

                padding: 130px 20px 40px;
            }

            .account-sections {
                grid-template-columns: 1fr;
            }

            .account-card.full-width {
                grid-column: auto;
            }
        }

        /* ========================================
           RESPONSIVE — SMALL MOBILE
        ======================================== */

        @media (max-width: 600px) {

            .navbar {
                height: 62px;
                top: 3px;
            }

            .header-logo img {
                width: 62px;
                height: 62px;
            }

            .header-search {
                right: 10px;

                width: 150px;
                height: 32px;
            }

            .header-search input {
                font-size: 0.65rem;
            }

            .dashboard-wrapper {
                padding-top: 65px;
            }

            .sidebar {
                top: 65px;
            }

            .account-content {
                padding: 120px 14px 30px;
            }

            .page-header h1 {
                font-size: 1.55rem;
            }

            .page-header .subtitle {
                font-size: 0.74rem;
            }

            .account-card {
                padding: 20px 18px;
            }

            .info-row {
                flex-direction: column;

                align-items: flex-start;

                gap: 4px;
            }

            .info-row .value {
                text-align: left;
            }

            .btn-edit {
                width: 100%;
            }

            .zaylo-notification {
                left: 14px;
                right: 14px;
                bottom: 14px;

                max-width: none;
            }
        }

        /* ========================================
           VERY SMALL MOBILE
        ======================================== */

        @media (max-width: 420px) {

            .header-search {
                width: 125px;
            }

            .header-search i {
                margin-right: 5px;
            }

            .sidebar-menu a {
                padding: 8px 10px;
            }

            .account-content {
                padding-left: 10px;
                padding-right: 10px;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>

    <!-- ========================================
         TOP GREEN LINE
    ======================================== -->

    <div class="top-line"></div>


    <!-- ========================================
         ADMIN HEADER
    ======================================== -->

    @include('admin.partials.header', ['searchId' => 'headerSearch', 'searchPlaceholder' => 'Search admin pages...'])


    <!-- ========================================
         ADMIN LAYOUT
    ======================================== -->

    <div class="dashboard-wrapper">

        <!-- ========================================
             ADMIN SIDEBAR
        ======================================== -->

        @include('admin.partials.sidebar')


        <!-- ========================================
             ACCOUNT CONTENT
        ======================================== -->

        <main class="main-content account-content">

            <!-- Page Header -->
            <div class="page-header">

                <div>
                    <h1>Account Settings</h1>

                    <p class="subtitle">
                        Manage your administrator profile
                    </p>
                </div>

            </div>


            <!-- ========================================
                 ACCOUNT SECTIONS
            ======================================== -->

            <div class="account-sections">

                <!-- ========================================
                     PERSONAL INFORMATION
                ======================================== -->

                <div class="account-card">

                    <h2 class="card-title">
                        Personal Information
                    </h2>

                    <p class="card-description">
                        Your administrator details
                    </p>

                    <div class="info-row">
                        <span class="label">
                            Full Name
                        </span>

                        <span
                            class="value"
                            id="fullName"
                        >
                            Administrator
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Email
                        </span>

                        <span
                            class="value"
                            id="email"
                        >
                            admin@zaylo.com
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Phone
                        </span>

                        <span
                            class="value"
                            id="phone"
                        >
                            09171234567
                        </span>
                    </div>

                    <button
                        type="button"
                        class="btn-edit"
                        onclick="showNotification('Edit personal information coming soon!')"
                    >
                        Edit Information
                    </button>

                </div>


                <!-- ========================================
                     ROLE & PERMISSIONS
                ======================================== -->

                <div class="account-card">

                    <h2 class="card-title">
                        Role &amp; Permissions
                    </h2>

                    <p class="card-description">
                        Your administrator role and access level
                    </p>

                    <div class="info-row">
                        <span class="label">
                            Role
                        </span>

                        <span class="value">
                            Super Admin
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Access Level
                        </span>

                        <span class="value">
                            Full Access
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Assigned Date
                        </span>

                        <span class="value">
                            Dec 1, 2024
                        </span>
                    </div>

                </div>


                <!-- ========================================
                     SECURITY
                ======================================== -->

                <div class="account-card full-width">

                    <h2 class="card-title">
                        Security
                    </h2>

                    <p class="card-description">
                        Manage your password and security settings
                    </p>

                    <div class="info-row">
                        <span class="label">
                            Password
                        </span>

                        <span class="value">
                            ••••••••
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Two-Factor Authentication
                        </span>

                        <span class="value">
                            Disabled
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Last Login
                        </span>

                        <span class="value">
                            Dec 15, 2024 2:30 PM
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Login IP
                        </span>

                        <span class="value">
                            192.168.1.1
                        </span>
                    </div>

                    <button
                        type="button"
                        class="btn-edit"
                        onclick="showNotification('Change password coming soon!')"
                    >
                        Change Password
                    </button>

                </div>


                <!-- ========================================
                     RECENT ACTIVITY
                ======================================== -->

                <div class="account-card full-width">

                    <h2 class="card-title">
                        Recent Activity
                    </h2>

                    <p class="card-description">
                        Your recent administrative actions
                    </p>

                    <div class="info-row">
                        <span class="label">
                            Dec 15, 2024 2:30 PM
                        </span>

                        <span class="value">
                            Logged in
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Dec 15, 2024 2:25 PM
                        </span>

                        <span class="value">
                            Approved seller registration - Juan Dela Cruz
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Dec 15, 2024 1:45 PM
                        </span>

                        <span class="value">
                            Updated platform settings
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Dec 15, 2024 11:20 AM
                        </span>

                        <span class="value">
                            Resolved dispute #DSP-2024-002
                        </span>
                    </div>

                    <div class="info-row">
                        <span class="label">
                            Dec 14, 2024 4:00 PM
                        </span>

                        <span class="value">
                            Generated monthly report
                        </span>
                    </div>

                    <button
                        type="button"
                        class="btn-edit"
                        onclick="showNotification('View full activity log coming soon!')"
                    >
                        View Full Log
                    </button>

                </div>


                <!-- ========================================
                     DANGER ZONE
                ======================================== -->

                <div class="account-card full-width danger-card">

                    <h2 class="card-title danger-title">
                        Danger Zone
                    </h2>

                    <p class="card-description">
                        Deactivate your administrator account
                    </p>

                    <button
                        type="button"
                        class="btn-edit danger-button"
                        onclick="deleteAccount()"
                    >
                        <i class="fas fa-trash-alt"></i>
                        Deactivate Account
                    </button>

                    <p class="danger-note">
                        Deactivating your account will remove your access
                        to the admin panel.
                    </p>

                </div>

            </div>

        </main>

    </div>


    <!-- ========================================
         JAVASCRIPT
    ======================================== -->

    <script>

        /* ========================================
           LOAD ADMIN DATA
        ======================================== */

        function loadAdminData() {

            try {

                const storedData =
                    localStorage.getItem('zaylo_user_data');

                if (!storedData) {
                    return;
                }

                const userData =
                    JSON.parse(storedData);

                if (
                    userData &&
                    userData.role === 'admin'
                ) {

                    if (
                        userData.firstName &&
                        userData.lastName
                    ) {

                        document.getElementById(
                            'fullName'
                        ).textContent =
                            `${userData.firstName} ${userData.lastName}`;
                    }

                    if (userData.email) {

                        document.getElementById(
                            'email'
                        ).textContent =
                            userData.email;
                    }

                    if (userData.phone) {

                        document.getElementById(
                            'phone'
                        ).textContent =
                            userData.phone;
                    }
                }

            } catch (error) {

                console.error(
                    'Unable to load admin data:',
                    error
                );

            }
        }


        /* ========================================
           ADMIN SEARCH
        ======================================== */

        const headerSearch =
            document.getElementById('headerSearch');

        if (headerSearch) {

            headerSearch.addEventListener(
                'keydown',
                function (event) {

                    if (event.key === 'Enter') {

                        const searchValue =
                            this.value.trim();

                        if (searchValue !== '') {

                            showNotification(
                                `Searching admin pages for "${searchValue}"...`
                            );

                        } else {

                            showNotification(
                                'Please enter something to search.'
                            );

                        }
                    }
                }
            );

        }


        /* ========================================
           DELETE / DEACTIVATE ACCOUNT
        ======================================== */

        function deleteAccount() {

            const firstConfirmation =
                confirm(
                    'Are you sure you want to deactivate your admin account? This action cannot be undone.'
                );

            if (!firstConfirmation) {
                return;
            }

            const secondConfirmation =
                confirm(
                    'Please confirm again: You will lose access to the admin panel.'
                );

            if (!secondConfirmation) {
                return;
            }

            localStorage.removeItem(
                'zaylo_user_data'
            );

            showNotification(
                'Account deactivated. Redirecting...'
            );

            setTimeout(function () {

                window.location.href =
                    "{{ route('login') }}";

            }, 2000);
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

                    if (notification) {
                        notification.remove();
                    }

                }, 300);

            }, 3000);
        }


        /* ========================================
           INITIALIZE
        ======================================== */

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                loadAdminData();

            }
        );

    </script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>

</html>