
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Account Management</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet">

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root {
            --black: #1a1714;
            --brown: #6b5f54;
            --light-brown: #8a7a6b;
            --accent: #b28b6f;
            --cream: #faf7f2;
            --soft-cream: #f5f0ea;
            --border: #ece4db;
            --input-border: #ded5cc;
            --white: #ffffff;
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
            color: var(--black);
            font-family: "Inter", sans-serif;
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

        .container {
            width: 100%;
            min-height: 100vh;
        }

        /* HEADER */

        .navbar {
            width: 100%;
            height: 64px;
            padding: 0 28px;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            border-bottom: 1px solid var(--border);
        }

        .nav-spacer {
            width: 240px;
        }

        .logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }

        .logo img {
            width: 78px;
            height: auto;
            display: block;
        }

        .nav-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: #f4f1ec;
            border: 1px solid #e5dfd8;
            border-radius: 30px;
        }

        .search-wrapper i {
            color: var(--brown);
            font-size: 12px;
        }

        .search-wrapper input {
            width: 130px;
            border: none;
            outline: none;
            background: transparent;
            font-size: 12px;
        }

        .icon-group {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .icon-group a {
            position: relative;
            font-size: 16px;
        }

        .icon-group a:hover {
            color: var(--accent);
        }

        .badge-count {
            position: absolute;
            top: -10px;
            right: -10px;
            padding: 2px 6px;
            background: var(--accent);
            color: var(--white);
            border-radius: 12px;
            font-size: 10px;
        }

        .login-text {
            border-left: 1px solid #d8d0c8;
            padding-left: 16px;
            font-size: 11px;
            font-weight: 600;
        }

        /* MAIN LAYOUT */

        .dashboard-wrapper {
            display: flex;
            width: 100%;
            min-height: calc(100vh - 64px);
        }

        /* SIDEBAR */

        .sidebar {
            width: 240px;
            flex-shrink: 0;
            background: var(--white);
            border-right: 1px solid var(--border);
            padding: 24px 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
        }

        .sidebar-menu li {
            margin-bottom: 3px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: var(--brown);
            font-size: 12px;
            border-radius: 3px;
            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: var(--soft-cream);
            color: var(--black);
        }

        .sidebar-menu a.active {
            background: var(--black);
            color: var(--white);
        }

        .sidebar-menu i {
            width: 18px;
            text-align: center;
        }

        .sidebar-menu .badge {
            margin-left: auto;
            padding: 2px 6px;
            background: var(--accent);
            color: var(--white);
            border-radius: 12px;
            font-size: 10px;
        }

        .sidebar-divider {
            height: 1px;
            background: var(--border);
            margin: 14px 0;
        }

        /* CONTENT */

        .content-area {
            flex: 1;
            min-width: 0;
            width: 100%;
            padding: 36px;
        }

        .page-heading {
            margin-bottom: 26px;
        }

        .page-heading h1 {
            margin-bottom: 6px;
            font-family: "Playfair Display", serif;
            font-size: clamp(25px, 3vw, 34px);
            font-weight: 600;
        }

        .page-heading p {
            color: var(--light-brown);
            font-size: 13px;
            line-height: 1.6;
        }

        /* ACCOUNT CARD */

        .account-card {
            width: 100%;
            max-width: 1200px;
            background: var(--white);
            border: 1px solid var(--border);
            padding: 32px;
        }

        .approval-notice {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 18px;
            margin-bottom: 30px;
            background: var(--soft-cream);
            border-left: 3px solid var(--accent);
        }

        .approval-notice i {
            color: var(--accent);
            margin-top: 2px;
        }

        .approval-notice p {
            color: var(--brown);
            font-size: 11px;
            line-height: 1.7;
        }

        .form-section {
            margin-bottom: 38px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            margin-bottom: 22px;
            border-bottom: 1px solid var(--border);
        }

        .section-title i {
            color: var(--accent);
            font-size: 18px;
        }

        .section-title h2 {
            font-family: "Playfair Display", serif;
            font-size: 22px;
            font-weight: 600;
        }

        .section-description {
            margin-top: -10px;
            margin-bottom: 24px;
            color: var(--light-brown);
            font-size: 12px;
            line-height: 1.6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: #4d4239;
            font-size: 11px;
            font-weight: 600;
        }

        .required {
            color: #a05f43;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            min-height: 43px;
            padding: 12px 13px;
            border: 1px solid var(--input-border);
            outline: none;
            background: var(--cream);
            color: var(--black);
            font-size: 12px;
            transition: 0.2s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--black);
            background: var(--white);
        }

        .form-group input:disabled,
        .form-group input:read-only {
            background: #eee8e1;
            color: var(--light-brown);
        }

        .form-help {
            color: var(--light-brown);
            font-size: 10px;
            line-height: 1.5;
        }

        .address-label {
            margin-bottom: 16px;
            color: var(--black);
            font-family: "Playfair Display", serif;
            font-size: 18px;
            font-weight: 600;
        }

        /* UPLOAD */

        .upload-box {
            padding: 24px 18px;
            text-align: center;
            background: var(--cream);
            border: 1px dashed #c8b9aa;
            transition: 0.2s ease;
        }

        .upload-box:hover {
            background: var(--white);
            border-color: var(--black);
        }

        .upload-box i {
            display: block;
            margin-bottom: 10px;
            color: var(--accent);
            font-size: 26px;
        }

        .upload-box p {
            margin-bottom: 14px;
            color: var(--brown);
            font-size: 11px;
            line-height: 1.5;
        }

        .upload-box input {
            width: 100%;
            font-size: 11px;
        }

        .file-name {
            display: block;
            margin-top: 10px;
            color: var(--light-brown);
            font-size: 10px;
            overflow-wrap: anywhere;
        }

        /* BUTTONS */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 43px;
            padding: 12px 25px;
            border: none;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.03em;
            transition: 0.2s ease;
        }

        .btn-primary {
            background: var(--black);
            color: var(--white);
        }

        .btn-primary:hover {
            background: var(--accent);
        }

        .btn-secondary {
            background: var(--soft-cream);
            color: var(--black);
            border: 1px solid var(--input-border);
        }

        .btn-secondary:hover {
            background: var(--border);
        }

        /* NOTIFICATION */

        .zaylo-notification {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            width: min(350px, calc(100% - 32px));
            padding: 15px 20px;
            background: var(--black);
            color: var(--white);
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.15);
            font-size: 12px;
            line-height: 1.6;
            animation: slideIn 0.25s ease;
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

        /* RESPONSIVE */

        @media (max-width: 1000px) {
            .content-area {
                padding: 28px;
            }

            .account-card {
                padding: 26px;
            }
        }

        @media (max-width: 820px) {
            .nav-spacer {
                display: none;
            }

            .navbar {
                padding: 0 16px;
            }

            .search-wrapper input {
                width: 90px;
            }

            .dashboard-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                padding: 10px 0;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .sidebar-menu {
                display: flex;
                gap: 4px;
                padding: 0 10px;
                overflow-x: auto;
            }

            .sidebar-menu li {
                flex-shrink: 0;
                margin-bottom: 0;
            }

            .sidebar-menu a {
                white-space: nowrap;
                padding: 10px 12px;
            }

            .sidebar-divider {
                display: none;
            }

            .content-area {
                padding: 24px;
            }

            .account-card {
                max-width: none;
            }
        }

        @media (max-width: 600px) {
            .navbar {
                height: 60px;
            }

            .dashboard-wrapper {
                min-height: calc(100vh - 60px);
            }

            .search-wrapper {
                display: none;
            }

            .logo img {
                width: 65px;
            }

            .login-text {
                display: none;
            }

            .icon-group {
                gap: 15px;
            }

            .content-area {
                padding: 16px;
            }

            .page-heading {
                margin-bottom: 20px;
            }

            .page-heading h1 {
                font-size: 26px;
            }

            .page-heading p {
                font-size: 12px;
            }

            .account-card {
                padding: 18px;
            }

            .approval-notice {
                padding: 13px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 17px;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .section-title h2 {
                font-size: 20px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }

            .zaylo-notification {
                right: 16px;
                bottom: 16px;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
</head>

<body class="seller-workspace">

    <div class="container">

        <!-- HEADER -->

        <header class="navbar">

            <div class="nav-spacer"></div>

            <a href="{{ route('seller.dashboard') }}" class="logo">
                <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO Logo">
            </a>

            <div class="nav-actions">

                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search" aria-label="Search">
                </div>

                <div class="icon-group">

                    <a href="#"
                        onclick="showNotification('You have 5 notifications'); return false;"
                        aria-label="Notifications">
                        <i class="far fa-bell"></i>
                        <span class="badge-count">5</span>
                    </a>

                    <a href="#"
                        onclick="showNotification('Shopping bag preview'); return false;"
                        aria-label="Shopping bag">
                        <i class="fas fa-shopping-bag"></i>
                        <span class="badge-count">2</span>
                    </a>

                    <a href="{{ route('seller.account') }}" aria-label="Account">
                        <i class="far fa-user"></i>
                    </a>

                    <a href="{{ route('login') }}" class="login-text">
                        Logout
                    </a>

                </div>

            </div>

        </header>

        <div class="dashboard-wrapper">

            <!-- SIDEBAR -->

            @include('partials.seller-sidebar')

            <!-- MAIN CONTENT -->

            <main class="content-area">

                <div class="page-heading">
                    <h1>Account Management</h1>
                    <p>
                        Manage your personal information, business details,
                        and verification documents.
                    </p>
                    <a href="{{ route('addresses.index') }}" class="btn btn-secondary" style="margin-top: 14px; width: auto;">
                        <i class="fas fa-location-dot"></i>
                        Manage Saved Addresses
                    </a>
                </div>

                <div class="account-card">

                    <div class="approval-notice">
                        <i class="fas fa-info-circle"></i>

                        <p>
                            Please ensure that all information and uploaded documents
                            are correct. Your seller account may require administrator
                            approval before full access is granted.
                        </p>
                    </div>

                    <form
                        method="POST"
                        action="#"
                        enctype="multipart/form-data"
                        id="accountForm"
                        onsubmit="submitAccount(event)"
                    >

                        @csrf

                        <!-- PERSONAL INFORMATION -->

                        <section class="form-section">

                            <div class="section-title">
                                <i class="fas fa-user"></i>
                                <h2>Personal Information</h2>
                            </div>

                            <p class="section-description">
                                Enter your complete and accurate personal details.
                            </p>

                            <div class="form-grid">

                                <div class="form-group">
                                    <label for="last_name">
                                        Last Name <span class="required">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        id="last_name"
                                        name="last_name"
                                        placeholder="Enter last name"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="first_name">
                                        First Name <span class="required">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        id="first_name"
                                        name="first_name"
                                        placeholder="Enter first name"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="middle_initial">
                                        Middle Initial
                                    </label>

                                    <input
                                        type="text"
                                        id="middle_initial"
                                        name="middle_initial"
                                        placeholder="e.g. A."
                                        maxlength="3"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="sex">
                                        Sex <span class="required">*</span>
                                    </label>

                                    <select id="sex" name="sex" required>
                                        <option value="">Select sex</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Prefer not to say">
                                            Prefer not to say
                                        </option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="email">
                                        E-mail <span class="required">*</span>
                                    </label>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        placeholder="Enter email address"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="contact_no">
                                        Contact No. <span class="required">*</span>
                                    </label>

                                    <input
                                        type="tel"
                                        id="contact_no"
                                        name="contact_no"
                                        placeholder="09XXXXXXXXX"
                                        pattern="^09[0-9]{9}$"
                                        required
                                    >

                                    <span class="form-help">
                                        Format: 09XXXXXXXXX
                                    </span>
                                </div>

                                <div class="form-group">
                                    <label for="birthday">
                                        Birthday <span class="required">*</span>
                                    </label>

                                    <input
                                        type="date"
                                        id="birthday"
                                        name="birthday"
                                        required
                                        onchange="calculateAge()"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="age">
                                        Age (Autogenerated)
                                    </label>

                                    <input
                                        type="number"
                                        id="age"
                                        name="age"
                                        placeholder="Automatically calculated"
                                        readonly
                                        disabled
                                    >

                                    <span class="form-help">
                                        Your age will be calculated from your birthday.
                                    </span>
                                </div>

                            </div>

                        </section>

                        <!-- ADDRESS INFORMATION -->

                        <section class="form-section">

                            <div class="section-title">
                                <i class="fas fa-location-dot"></i>
                                <h2>Address Information</h2>
                            </div>

                            <p class="section-description">
                                Select your location and enter your complete address.
                            </p>

                            <h3 class="address-label">
                                Address via API
                            </h3>

                            <div class="form-grid">

                                <div class="form-group">
                                    <label for="province">
                                        Province <span class="required">*</span>
                                    </label>

                                    <select id="province" name="province" required>
                                        <option value="">Select province</option>
                                        <option value="Laguna">Laguna</option>
                                        <option value="Batangas">Batangas</option>
                                        <option value="Cavite">Cavite</option>
                                        <option value="Rizal">Rizal</option>
                                        <option value="Quezon">Quezon</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="municipality">
                                        Municipality / City
                                        <span class="required">*</span>
                                    </label>

                                    <select id="municipality" name="municipality" required>
                                        <option value="">Select municipality</option>
                                        <option value="Magdalena">Magdalena</option>
                                        <option value="Santa Cruz">Santa Cruz</option>
                                        <option value="Calamba">Calamba</option>
                                        <option value="Los Baños">Los Baños</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="barangay">
                                        Barangay <span class="required">*</span>
                                    </label>

                                    <select id="barangay" name="barangay" required>
                                        <option value="">Select barangay</option>
                                        <option value="Barangay 1">Barangay 1</option>
                                        <option value="Barangay 2">Barangay 2</option>
                                        <option value="Barangay 3">Barangay 3</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                            </div>

                            <h3 class="address-label" style="margin-top: 28px;">
                                Manual Address Entry
                            </h3>

                            <div class="form-grid">

                                <div class="form-group">
                                    <label for="house_number">
                                        House Number
                                    </label>

                                    <input
                                        type="text"
                                        id="house_number"
                                        name="house_number"
                                        placeholder="House number"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="street">
                                        Street
                                    </label>

                                    <input
                                        type="text"
                                        id="street"
                                        name="street"
                                        placeholder="Street name"
                                    >
                                </div>

                                <div class="form-group full-width">
                                    <label for="additional_address">
                                        Additional Address Details
                                    </label>

                                    <input
                                        type="text"
                                        id="additional_address"
                                        name="additional_address"
                                        placeholder="Subdivision, building, unit number, etc."
                                    >
                                </div>

                            </div>

                        </section>

                        <!-- BUSINESS INFORMATION -->

                        <section class="form-section">

                            <div class="section-title">
                                <i class="fas fa-store"></i>
                                <h2>Business Information</h2>
                            </div>

                            <p class="section-description">
                                Provide your business information for seller verification.
                            </p>

                            <div class="form-grid">

                                <div class="form-group">
                                    <label for="business_name">
                                        Business Name
                                    </label>

                                    <input
                                        type="text"
                                        id="business_name"
                                        name="business_name"
                                        value="{{ $storeName }}"
                                        placeholder="Enter business name"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="line_of_business">
                                        Line of Business / Category
                                    </label>

                                    <select
                                        id="line_of_business"
                                        name="line_of_business"
                                    >
                                        <option value="">Select category</option>
                                        <option value="Home and Garden">
                                            Home and Garden
                                        </option>
                                        <option value="Kitchen Appliances">
                                            Kitchen Appliances
                                        </option>
                                        <option value="Furniture and Decor">
                                            Furniture and Decor
                                        </option>
                                        <option value="Gardening Tools">
                                            Gardening Tools
                                        </option>
                                        <option value="Outdoor Living">
                                            Outdoor Living
                                        </option>
                                        <option value="Home Improvement Tools">
                                            Home Improvement Tools
                                        </option>
                                        <option value="Bedding and Bath">
                                            Bedding and Bath
                                        </option>
                                        <option value="Fashion and Accessories">
                                            Fashion and Accessories
                                        </option>
                                        <option value="Other">
                                            Other
                                        </option>
                                    </select>
                                </div>

                            </div>

                        </section>

                        <!-- VERIFICATION DOCUMENTS -->

                        <section class="form-section">

                            <div class="section-title">
                                <i class="fas fa-file-shield"></i>
                                <h2>Verification Documents</h2>
                            </div>

                            <p class="section-description">
                                Upload clear and valid documents for administrator review.
                            </p>

                            <div class="form-grid">

                                <div class="form-group">

                                    <label for="valid_id">
                                        Upload ID <span class="required">*</span>
                                    </label>

                                    <div class="upload-box">
                                        <i class="fas fa-id-card"></i>

                                        <p>
                                            Upload a valid government-issued ID.
                                        </p>

                                        <input
                                            type="file"
                                            id="valid_id"
                                            name="valid_id"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            required
                                            onchange="showFileName(this, 'validIdName')"
                                        >

                                        <span class="file-name" id="validIdName">
                                            No file selected
                                        </span>
                                    </div>

                                </div>

                                <div class="form-group">

                                    <label for="business_permit">
                                        Upload Business Permit
                                    </label>

                                    <div class="upload-box">
                                        <i class="fas fa-file-contract"></i>

                                        <p>
                                            Upload your business permit document.
                                        </p>

                                        <input
                                            type="file"
                                            id="business_permit"
                                            name="business_permit"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            onchange="showFileName(this, 'businessPermitName')"
                                        >

                                        <span class="file-name" id="businessPermitName">
                                            No file selected
                                        </span>
                                    </div>

                                </div>

                            </div>

                        </section>

                        <!-- FORM ACTIONS -->

                        <div class="form-actions">

                            <button
                                type="reset"
                                class="btn btn-secondary"
                                onclick="resetFileNames()"
                            >
                                <i class="fas fa-rotate-left"></i>
                                Reset Form
                            </button>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>

                        </div>

                    </form>

                </div>

            </main>

        </div>

    </div>

    <script>
        // AUTOMATIC AGE CALCULATION

        function calculateAge() {
            const birthdayInput = document.getElementById("birthday");
            const ageInput = document.getElementById("age");

            if (!birthdayInput.value) {
                ageInput.value = "";
                return;
            }

            const birthDate = new Date(birthdayInput.value);
            const today = new Date();

            let age = today.getFullYear() - birthDate.getFullYear();

            const monthDifference =
                today.getMonth() - birthDate.getMonth();

            if (
                monthDifference < 0 ||
                (
                    monthDifference === 0 &&
                    today.getDate() < birthDate.getDate()
                )
            ) {
                age--;
            }

            ageInput.value = age >= 0 ? age : "";
        }

        // FILE NAME DISPLAY

        function showFileName(input, outputId) {
            const output = document.getElementById(outputId);

            if (input.files && input.files.length > 0) {
                output.textContent = input.files[0].name;
            } else {
                output.textContent = "No file selected";
            }
        }

        function resetFileNames() {
            setTimeout(() => {
                document.getElementById("validIdName").textContent =
                    "No file selected";

                document.getElementById("businessPermitName").textContent =
                    "No file selected";

                document.getElementById("age").value = "";
            }, 50);
        }

        // FORM SUBMISSION

        function submitAccount(event) {
            event.preventDefault();

            const form = document.getElementById("accountForm");

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            showNotification(
                "Account details are ready. Connect this form to your Laravel controller to save the data."
            );
        }

        // NOTIFICATION

        function showNotification(message) {
            const existingNotification =
                document.querySelector(".zaylo-notification");

            if (existingNotification) {
                existingNotification.remove();
            }

            const notification = document.createElement("div");

            notification.className = "zaylo-notification";
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.remove();
                }
            }, 4000);
        }

        // SET MAXIMUM BIRTHDAY TO TODAY

        document.addEventListener("DOMContentLoaded", function () {
            const birthdayInput = document.getElementById("birthday");

            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, "0");
            const day = String(today.getDate()).padStart(2, "0");

            birthdayInput.max = `${year}-${month}-${day}`;
        });
    </script>

</body>

</html>
