
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Manage Registrations</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #faf7f2;
            color: #1e1e1e;
            font-family: 'Inter', sans-serif;
        }

        .container {
            width: 100%;
            min-height: 100vh;
        }

        /* ================= HEADER ================= */

        .navbar {
            height: 64px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 28px;
            border-top: 2px solid #79a875;
            border-bottom: 1px solid #ece4db;
            position: relative;
        }

        .logo {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .logo img {
            width: 78px;
            height: auto;
            object-fit: contain;
        }

        .nav-actions {
            position: absolute;
            right: 28px;
            display: flex;
            align-items: center;
        }

        .search-wrapper {
            width: 360px;
            height: 42px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f4f1ec;
            border: 1px solid #e5dfd8;
            border-radius: 30px;
            padding: 4px 14px;
        }

        .search-wrapper span {
            font-size: 18px;
        }

        .search-wrapper input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            padding: 6px;
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
        }

        /* ================= LAYOUT ================= */

        .dashboard-wrapper {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        /* ================= SIDEBAR ================= */

        .sidebar {
            width: 240px;
            flex-shrink: 0;
            background: white;
            border-right: 1px solid #ece4db;
            padding: 24px 16px;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 3px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            color: #6b5f54;
            text-decoration: none;
            font-size: 0.75rem;
            border-radius: 2px;
        }

        .sidebar-menu a:hover {
            background: #f5f0ea;
            color: #1e1e1e;
        }

        .sidebar-menu a.active {
            background: #1a1714;
            color: white;
        }

        .sidebar-menu i {
            width: 18px;
            text-align: center;
        }

        .sidebar-badge {
            background: #b28b6f;
            color: white;
            border-radius: 20px;
            font-size: 0.55rem;
            padding: 2px 7px;
            margin-left: auto;
        }

        .sidebar-divider {
            height: 1px;
            background: #ece4db;
            margin: 14px 0;
        }

        /* ================= MAIN CONTENT ================= */

        .main-content {
            flex: 1;
            padding: 32px 40px;
            min-width: 0;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
        }

        .subtitle {
            color: #6b5f54;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .pending-count {
            background: #c0392b;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 0.7rem;
        }

        /* ================= SMALL ROLE BUTTONS ================= */

        .role-buttons {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 26px;
        }

        .role-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;

            background: white;
            color: #6b5f54;
            border: 1px solid #e5ddd3;

            padding: 10px 18px;
            min-height: 45px;

            cursor: pointer;
            text-align: center;
            font-family: 'Inter', sans-serif;
            transition: 0.2s ease;
        }

        .role-button i {
            display: inline-block;
            font-size: 0.75rem;
            margin-bottom: 0;
        }

        .role-button strong {
            display: inline-block;
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0;
            white-space: nowrap;
        }

        .role-button span {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            background: #b28b6f;
            color: white;

            border-radius: 20px;
            min-width: 20px;
            height: 20px;

            padding: 0 6px;
            font-size: 0.55rem;
            font-weight: 600;
        }

        .role-button:hover,
        .role-button.active {
            background: #1a1714;
            color: white;
            border-color: #1a1714;
        }

        .role-button:hover span,
        .role-button.active span {
            background: #b28b6f;
            color: white;
        }

        /* ================= FILTERS ================= */

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            background: white;
            border: 1px solid #ece4db;
            padding: 16px 20px;
            margin-bottom: 26px;
        }

        .filter-bar input,
        .filter-bar select {
            border: 1px solid #ece4db;
            padding: 10px 14px;
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
            outline: none;
            background: white;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
        }

        .btn-filter {
            border: none;
            background: #1a1714;
            color: white;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 0.65rem;
            text-transform: uppercase;
        }

        .btn-filter.clear {
            background: white;
            color: #6b5f54;
            border: 1px solid #ece4db;
        }

        .btn-filter:hover {
            opacity: 0.85;
        }

        /* ================= ROLE CONTAINER ================= */

        .role-container {
            background: #f4efe8;
            border: 1px solid #e5ddd3;
            padding: 20px;
            margin-bottom: 28px;
        }

        .role-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #ddd2c6;
        }

        .role-title h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
        }

        .role-title span {
            color: #6b5f54;
            font-size: 0.7rem;
        }

        /* ================= REGISTRATION CARD ================= */

        .registration-card {
            background: white;
            border: 1px solid #ece4db;
            padding: 20px;
            margin-bottom: 12px;
        }

        .registration-card:last-child {
            margin-bottom: 0;
        }

        .reg-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0e9e1;
        }

        .reg-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .reg-email {
            display: block;
            color: #6b5f54;
            font-size: 0.75rem;
            margin-top: 3px;
        }

        .reg-status {
            padding: 4px 12px;
            font-size: 0.6rem;
            text-transform: uppercase;
        }

        .status-pending {
            background: #f5e0c0;
            color: #e67e22;
        }

        .status-approved {
            background: #d6e4d0;
            color: #2d7d46;
        }

        .status-rejected {
            background: #f5e0e0;
            color: #c0392b;
        }

        .reg-body {
            display: grid;
            grid-template-columns: 2fr 1fr 180px;
            gap: 20px;
            padding-top: 16px;
        }

        .reg-info h4,
        .reg-documents h4 {
            color: #6b5f54;
            font-size: 0.65rem;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .info-item,
        .doc {
            font-size: 0.75rem;
            margin-bottom: 6px;
        }

        .label {
            color: #6b5f54;
        }

        .doc-icon.verified {
            color: #2d7d46;
        }

        .doc-icon.pending {
            color: #e67e22;
        }

        .doc-icon.rejected {
            color: #c0392b;
        }

        .reg-actions {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .reg-actions button {
            border: none;
            padding: 9px 12px;
            color: white;
            font-size: 0.62rem;
            text-transform: uppercase;
            cursor: pointer;
        }

        .btn-approve {
            background: #2d7d46;
        }

        .btn-reject {
            background: #c0392b;
        }

        .btn-view {
            background: white !important;
            color: #1a1714 !important;
            border: 1px solid #ece4db !important;
        }

        .document-review-dialog { width:min(860px,calc(100% - 28px)); max-height:90vh; margin:auto; padding:0; border:1px solid #ece4db; background:#fff; color:#1a1714; box-shadow:0 20px 70px #0004; }
        .document-review-dialog::backdrop { background:#1a171499; }
        .review-dialog-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; padding:20px 24px; border-bottom:1px solid #ece4db; }
        .review-dialog-head h2 { font:600 1.35rem 'Playfair Display',serif; }
        .review-dialog-head p { margin-top:5px; color:#6b5f54; font-size:.8rem; }
        .review-dialog-close { border:0; background:transparent; color:#6b5f54; font-size:1.2rem; cursor:pointer; }
        .review-dialog-body { max-height:calc(90vh - 84px); overflow:auto; padding:20px 24px; }
        .review-file-card { margin-bottom:16px; padding:14px; border:1px solid #ece4db; background:#faf7f2; }
        .review-file-card h3 { margin-bottom:10px; font-size:.9rem; }
        .review-file-preview { display:block; width:100%; height:min(58vh,520px); border:1px solid #ece4db; background:#fff; object-fit:contain; }
        .review-file-empty { padding:28px 16px; color:#6b5f54; text-align:center; font-size:.8rem; background:#fff; border:1px dashed #ded5cc; }
        .review-screening-note { margin-top:16px; padding:12px 14px; border-left:3px solid #b28b6f; background:#f5f0ea; color:#6b5f54; font-size:.8rem; line-height:1.6; }

        .reg-actions button:hover {
            opacity: 0.8;
        }

        .reg-date {
            color: #6b5f54;
            font-size: 0.6rem;
            text-align: center;
            margin-top: 5px;
        }

        .empty-registrations {
            background: white;
            border: 1px solid #ece4db;
            padding: 35px;
            text-align: center;
            color: #6b5f54;
            font-size: 0.8rem;
        }

        .sable-notification {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            background: #1a1714;
            color: white;
            padding: 15px 24px;
            font-size: 0.8rem;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1024px) {
            .main-content {
                padding: 24px;
            }

            .reg-body {
                grid-template-columns: 1fr 1fr;
            }

            .reg-actions {
                grid-column: 1 / -1;
                flex-direction: row;
            }
        }

        @media (max-width: 820px) {
            .dashboard-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
                overflow-x: auto;
                padding: 12px;
            }

            .sidebar-menu {
                display: flex;
                gap: 4px;
                min-width: max-content;
            }

            .sidebar-divider {
                display: none;
            }

            .main-content {
                padding: 20px 16px;
            }

            .search-wrapper {
                width: 250px;
            }
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 0 14px;
            }

            .logo img {
                width: 65px;
            }

            .nav-actions {
                right: 14px;
            }

            .search-wrapper {
                width: 145px;
            }

            .search-wrapper input {
                font-size: 0.65rem;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            /* SMALL MOBILE ROLE BUTTONS */

            .role-buttons {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 8px;
            }

            .role-button {
                flex: 1;
                min-width: 100px;
                padding: 10px 12px;
            }

            .role-button strong {
                font-size: 0.6rem;
            }

            .role-button span {
                min-width: 18px;
                height: 18px;
                font-size: 0.5rem;
            }

            .reg-body {
                grid-template-columns: 1fr;
            }

            .reg-actions {
                flex-direction: column;
            }

            .reg-actions button {
                width: 100%;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>
<div class="container">

    <!-- ================= ADMIN HEADER ================= -->

    @include('admin.partials.header', ['searchId' => 'headerSearch', 'searchPlaceholder' => 'Search registrations...', 'searchOnInput' => ''])

    <div class="dashboard-wrapper">

        <!-- ================= ADMIN SIDEBAR ================= -->

        @include('admin.partials.sidebar')

        <!-- ================= MAIN CONTENT ================= -->

        <main class="main-content">

            <div class="page-header">
                <div>
                    <h1>Manage Registrations</h1>

                    <p class="subtitle">
                        Select a registration category to review and manage accounts.
                    </p>
                </div>

                <span class="pending-count">
                    <span id="pendingCount">0</span> Pending
                </span>
            </div>

            <div style="margin:0 0 20px;padding:14px 16px;background:#f5f0ea;border-left:3px solid #b28b6f;color:#6b5f54;font-size:12px;line-height:1.6">
                <strong style="color:#1a1714">Strict registration review:</strong>
                approve only applicants (or a sorting center's representative) aged 18 or older, with matching identity documents, complete required documents, and no unresolved duplicate or suspicious-account flags.
            </div>

            <!-- ================= SEPARATE ROLE BUTTONS ================= -->

            <div class="role-buttons">

                <button
                    type="button"
                    class="role-button active"
                    data-role="buyer"
                    onclick="selectRole('buyer')"
                >
                    <i class="fas fa-user"></i>

                    <strong>Buyers</strong>

                    <span id="buyerCount">0</span>
                </button>

                <button
                    type="button"
                    class="role-button"
                    data-role="seller"
                    onclick="selectRole('seller')"
                >
                    <i class="fas fa-store"></i>

                    <strong>Sellers</strong>

                    <span id="sellerCount">0</span>
                </button>

                <button
                    type="button"
                    class="role-button"
                    data-role="sorting_center"
                    onclick="selectRole('sorting_center')"
                >
                    <i class="fas fa-warehouse"></i>

                    <strong>Sorting Center</strong>

                    <span id="sortingCenterCount">0</span>
                </button>

            </div>

            <!-- ================= FILTERS ================= -->

            <div class="filter-bar">

                <input
                    type="text"
                    id="searchReg"
                    class="search-input"
                    placeholder="Search by name or email..."
                    oninput="filterRegistrations()"
                >

                <select
                    id="statusFilter"
                    onchange="filterRegistrations()"
                >
                    <option value="all">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>

                <button
                    type="button"
                    class="btn-filter"
                    onclick="filterRegistrations()"
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

            <!-- ================= REGISTRATIONS ================= -->

            <div id="registrationsContainer"></div>

        </main>
    </div>
</div>

<dialog class="document-review-dialog" id="documentReviewDialog" aria-labelledby="documentReviewTitle">
    <div class="review-dialog-head">
        <div>
            <h2 id="documentReviewTitle">Review submitted requirements</h2>
            <p id="documentReviewApplicant"></p>
        </div>
        <button class="review-dialog-close" type="button" onclick="closeDocumentReview()" aria-label="Close document review">&times;</button>
    </div>
    <div class="review-dialog-body" id="documentReviewBody"></div>
</dialog>

<script>
    /* ================= REGISTRATION DATA ================= */

    const registrationsData = [
        {
            id: 1,
            name: 'Juan Dela Cruz',
            email: 'juan@email.com',
            role: 'seller',
            status: 'pending',
            date: 'September 15, 2026',
            phone: '09171234567',
            address: 'Makati City',
            age: 28,
            identityMatches: true,
            duplicateAccount: false,
            organization: 'ZAYLO Fashion Hub',
            documents: {
                id: 'verified',
                business: 'pending'
            },
            documentFiles: { id: null, business: null },
            authenticityCheck: 'unavailable'
        },
        {
            id: 2,
            name: 'Maria Reyes',
            email: 'maria@email.com',
            role: 'buyer',
            status: 'pending',
            date: 'September 14, 2026',
            phone: '09172345678',
            address: 'Quezon City',
            age: 17,
            identityMatches: true,
            duplicateAccount: false,
            documents: {
                id: 'pending'
            },
            documentFiles: { id: null },
            authenticityCheck: 'unavailable'
        },
        {
            id: 3,
            name: 'Sorting Center Manila',
            email: 'sorting@email.com',
            role: 'sorting_center',
            status: 'pending',
            date: 'September 13, 2026',
            phone: '09173456789',
            address: 'Manila City',
            age: 36,
            identityMatches: true,
            duplicateAccount: false,
            organization: 'ZAYLO Sorting Center Manila',
            documents: {
                id: 'verified',
                business: 'pending'
            },
            documentFiles: { id: null, business: null },
            authenticityCheck: 'unavailable'
        },
        {
            id: 4,
            name: 'Ana Lopez',
            email: 'ana@email.com',
            role: 'seller',
            status: 'approved',
            date: 'September 12, 2026',
            phone: '09174567890',
            address: 'Taguig City',
            age: 32,
            identityMatches: true,
            duplicateAccount: false,
            organization: 'Luxury Bags Co.',
            documents: {
                id: 'verified',
                business: 'verified'
            },
            documentFiles: { id: null, business: null },
            authenticityCheck: 'unavailable'
        },
        {
            id: 5,
            name: 'Carlos Garcia',
            email: 'carlos@email.com',
            role: 'seller',
            status: 'rejected',
            date: 'September 11, 2026',
            phone: '09175678901',
            address: 'Mandaluyong City',
            age: 25,
            identityMatches: false,
            duplicateAccount: true,
            organization: 'Urban Wear PH',
            documents: {
                id: 'rejected',
                business: 'rejected'
            },
            documentFiles: { id: null, business: null },
            authenticityCheck: 'unavailable'
        }
    ];

    let selectedRole = 'buyer';
    let filteredRegistrations = [];

    const roleMap = {
        buyer: {
            label: 'Buyers',
            singular: 'Buyer'
        },

        seller: {
            label: 'Sellers',
            singular: 'Seller'
        },

        sorting_center: {
            label: 'Sorting Centers',
            singular: 'Sorting Center'
        },
    };

    const statusMap = {
        pending: {
            label: 'Pending',
            class: 'status-pending'
        },

        approved: {
            label: 'Approved',
            class: 'status-approved'
        },

        rejected: {
            label: 'Rejected',
            class: 'status-rejected'
        }
    };

    const documentLabels = {
        id: 'Valid ID',
        business: 'Business Permit'
    };

    const documentStatus = {
        verified: {
            icon: 'fa-check-circle',
            class: 'verified',
            label: 'Verified'
        },

        pending: {
            icon: 'fa-clock',
            class: 'pending',
            label: 'Pending'
        },

        rejected: {
            icon: 'fa-times-circle',
            class: 'rejected',
            label: 'Rejected'
        }
    };

    /* ================= ESCAPE HTML ================= */

    function escapeHTML(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    /* ================= SELECT ROLE ================= */

    function selectRole(role) {
        selectedRole = role;

        document.querySelectorAll('.role-button').forEach(button => {
            button.classList.toggle(
                'active',
                button.dataset.role === role
            );
        });

        filterRegistrations();
    }

    /* ================= UPDATE ROLE COUNTS ================= */

    function updateRoleCounts() {
        const buyerCount = registrationsData.filter(
            reg => reg.role === 'buyer'
        ).length;

        const sellerCount = registrationsData.filter(
            reg => reg.role === 'seller'
        ).length;

        const sortingCenterCount = registrationsData.filter(
            reg => reg.role === 'sorting_center'
        ).length;
        document.getElementById('buyerCount').textContent =
            buyerCount;

        document.getElementById('sellerCount').textContent =
            sellerCount;

        document.getElementById('sortingCenterCount').textContent =
            sortingCenterCount;
    }

    /* ================= RENDER REGISTRATIONS ================= */

    function renderRegistrations() {
        const container = document.getElementById(
            'registrationsContainer'
        );

        const roleInfo = roleMap[selectedRole];

        let html = `
            <section class="role-container">

                <div class="role-title">
                    <h2>${roleInfo.label}</h2>

                    <span>
                        ${filteredRegistrations.length} Registration(s)
                    </span>
                </div>
        `;

        if (filteredRegistrations.length === 0) {
            html += `
                <div class="empty-registrations">
                    No ${roleInfo.singular.toLowerCase()}
                    registrations found.
                </div>
            `;
        } else {
            filteredRegistrations.forEach(registration => {
                html += renderRegistrationCard(registration);
            });
        }

        html += `</section>`;

        container.innerHTML = html;

        updatePendingCount();
        updateRoleCounts();
    }

    /* ================= REGISTRATION CARD ================= */

    function renderRegistrationCard(reg) {
        const statusInfo = statusMap[reg.status];

        let documentsHtml = '';

        Object.entries(reg.documents || {}).forEach(([key, value]) => {
            const document =
                documentStatus[value] ||
                documentStatus.pending;

            documentsHtml += `
                <div class="doc">

                    <span class="doc-icon ${document.class}">
                        <i class="fas ${document.icon}"></i>
                    </span>

                    ${documentLabels[key] || key}:
                    ${document.label}

                </div>
            `;
        });

        documentsHtml += `
            <div class="doc">
                <span class="doc-icon pending"><i class="fas fa-shield-halved"></i></span>
                Authenticity screening: ${reg.authenticityCheck === 'passed' ? 'Passed' : reg.authenticityCheck === 'flagged' ? 'Flagged · reject' : 'Unavailable · manual review required'}
            </div>
        `;

        let organizationHtml = '';

        if (reg.organization) {
            organizationHtml = `
                <div class="info-item">
                    <span class="label">Organization:</span>
                    ${escapeHTML(reg.organization)}
                </div>
            `;
        }

        let actionHtml = '';

        if (reg.status === 'pending') {
            actionHtml = `
                <button type="button" class="btn-view" onclick="viewRegistration(${reg.id})">
                    <i class="fas fa-file-shield"></i>
                    Review Requirements
                </button>

                <button
                    type="button"
                    class="btn-approve"
                    onclick="approveRegistration(${reg.id})"
                >
                    <i class="fas fa-check"></i>
                    Approve
                </button>

                <button
                    type="button"
                    class="btn-reject"
                    onclick="rejectRegistration(${reg.id})"
                >
                    <i class="fas fa-times"></i>
                    Reject
                </button>
            `;
        } else {
            actionHtml = `
                <button
                    type="button"
                    class="btn-view"
                    onclick="viewRegistration(${reg.id})"
                >
                    <i class="fas fa-eye"></i>
                    View Details
                </button>
            `;
        }

        return `
            <div class="registration-card">

                <div class="reg-header">

                    <div>
                        <span class="reg-name">
                            ${escapeHTML(reg.name)}
                        </span>

                        <span class="reg-email">
                            ${escapeHTML(reg.email)}
                        </span>
                    </div>

                    <span class="reg-status ${statusInfo.class}">
                        ${statusInfo.label}
                    </span>

                </div>

                <div class="reg-body">

                    <div class="reg-info">

                        <h4>Information</h4>

                        <div class="info-item">
                            <span class="label">Phone:</span>
                            ${escapeHTML(reg.phone)}
                        </div>

                        <div class="info-item">
                            <span class="label">Address:</span>
                            ${escapeHTML(reg.address)}
                        </div>

                        <div class="info-item">
                            <span class="label">Legal age (18+):</span>
                            ${Number.isInteger(reg.age) ? (reg.age >= 18 ? 'Pass · ' + reg.age : 'Fail · ' + reg.age) : 'Not verified'}
                        </div>

                        <div class="info-item">
                            <span class="label">Identity / account checks:</span>
                            ${reg.identityMatches ? 'ID details match' : 'ID details do not match'} · ${reg.duplicateAccount ? 'Possible duplicate account' : 'No duplicate found'}
                        </div>

                        ${organizationHtml}

                    </div>

                    <div class="reg-documents">

                        <h4>Documents</h4>

                        ${documentsHtml}

                    </div>

                    <div class="reg-actions">

                        ${actionHtml}

                        <div class="reg-date">
                            Submitted:
                            ${escapeHTML(reg.date)}
                        </div>

                    </div>

                </div>
            </div>
        `;
    }

    /* ================= FILTER ================= */

    function filterRegistrations() {
        const search = document
            .getElementById('searchReg')
            .value
            .toLowerCase()
            .trim();

        const status = document
            .getElementById('statusFilter')
            .value;

        filteredRegistrations = registrationsData.filter(reg => {
            const matchesRole =
                reg.role === selectedRole;

            const matchesSearch =
                reg.name.toLowerCase().includes(search) ||
                reg.email.toLowerCase().includes(search) ||
                (reg.organization || '')
                    .toLowerCase()
                    .includes(search);

            const matchesStatus =
                status === 'all' ||
                reg.status === status;

            return matchesRole &&
                matchesSearch &&
                matchesStatus;
        });

        renderRegistrations();
    }

    /* ================= CLEAR FILTERS ================= */

    function clearFilters() {
        document.getElementById('searchReg').value = '';

        document.getElementById('statusFilter').value = 'all';

        document.getElementById('headerSearch').value = '';

        filterRegistrations();
    }

    /* ================= APPROVE ================= */

    function approveRegistration(id) {
        const registration = registrationsData.find(
            reg => reg.id === id
        );

        if (!registration) return;

        const failedChecks = [];
        if (!Number.isInteger(registration.age) || registration.age < 18) failedChecks.push('applicant or sorting center representative must be at least 18');
        if (registration.identityMatches !== true) failedChecks.push('identity details must match the submitted ID');
        if (registration.duplicateAccount === true) failedChecks.push('possible duplicate or suspicious account needs review');
        if (Object.values(registration.documents || {}).some(status => status !== 'verified')) {
            failedChecks.push('all required documents must be verified');
        }
        const documentKeys = Object.keys(registration.documents || {});
        if (documentKeys.some(key => !getSafeDocumentUrl(registration.documentFiles?.[key]))) {
            failedChecks.push('submitted ID and required permits must be attached and viewable');
        }
        if (registration.authenticityCheck !== 'passed') {
            failedChecks.push('document authenticity screening must pass before approval');
        }
        if (failedChecks.length) {
            showNotification(`Cannot approve yet: ${failedChecks.join('; ')}.`);
            return;
        }

        const confirmed = confirm(
            `Approve ${registration.name} as ${
                roleMap[registration.role].singular
            }?`
        );

        if (!confirmed) return;

        registration.status = 'approved';

        Object.keys(registration.documents || {}).forEach(key => {
            if (registration.documents[key] === 'pending') {
                registration.documents[key] = 'verified';
            }
        });

        filterRegistrations();

        showNotification(
            `${registration.name}'s registration has been approved.`
        );
    }

    /* ================= REJECT ================= */

    function rejectRegistration(id) {
        const registration = registrationsData.find(
            reg => reg.id === id
        );

        if (!registration) return;

        const reason = prompt(
            `Reject registration for ${registration.name}?\nReason:`,
            'Documents do not match the provided information.'
        );

        if (reason === null) return;

        registration.status = 'rejected';

        Object.keys(registration.documents || {}).forEach(key => {
            if (registration.documents[key] === 'pending') {
                registration.documents[key] = 'rejected';
            }
        });

        filterRegistrations();

        showNotification(
            `${registration.name}'s registration has been rejected.`
        );
    }

    /* ================= VIEW DETAILS ================= */

    function viewRegistration(id) {
        const registration = registrationsData.find(
            reg => reg.id === id
        );

        if (!registration) return;

        const documentBody = document.getElementById('documentReviewBody');
        const fileLabels = { id: 'Government ID', business: 'Business permit' };
        document.getElementById('documentReviewApplicant').textContent =
            `${registration.name} · ${roleMap[registration.role].singular} · ${registration.email}`;

        documentBody.innerHTML = Object.entries(registration.documents || {}).map(([key, status]) => {
            const fileUrl = getSafeDocumentUrl(registration.documentFiles?.[key]);
            let preview = '<div class="review-file-empty">No submitted file is connected to this registration record.</div>';
            if (fileUrl) {
                const isImage = /\.(jpe?g|png|webp|gif)$/i.test(fileUrl);
                const isPdf = /\.pdf$/i.test(fileUrl);
                if (isImage) preview = `<img class="review-file-preview" src="${escapeHTML(fileUrl)}" alt="${escapeHTML(fileLabels[key] || key)} preview">`;
                else if (isPdf) preview = `<iframe class="review-file-preview" src="${escapeHTML(fileUrl)}" title="${escapeHTML(fileLabels[key] || key)} preview"></iframe>`;
                else preview = '<div class="review-file-empty">Unsupported file type. Use a PDF or image file.</div>';
                preview += `<p style="margin-top:8px"><a href="${escapeHTML(fileUrl)}" target="_blank" rel="noopener">Open submitted file</a></p>`;
            }
            return `<section class="review-file-card"><h3>${escapeHTML(fileLabels[key] || key)} · ${escapeHTML(status)}</h3>${preview}</section>`;
        }).join('');

        documentBody.insertAdjacentHTML('beforeend', `
            <div class="review-screening-note">
                <strong>Authenticity screening: ${escapeHTML(registration.authenticityCheck || 'Unavailable')}</strong><br>
                Automatic fake or AI-generated document detection is not configured in this project. Treat unscanned documents as unverified and review the visible original file before deciding.
            </div>
            <div class="review-screening-note">
                Applicant age: ${Number.isInteger(registration.age) ? escapeHTML(registration.age) : 'Not provided'} · Identity match: ${registration.identityMatches === true ? 'Yes' : 'Not verified'} · Duplicate account check: ${registration.duplicateAccount === false ? 'No match found' : 'Unresolved / flagged'}
            </div>
        `);

        document.getElementById('documentReviewDialog').showModal();
    }

    function getSafeDocumentUrl(value) {
        if (typeof value !== 'string' || !value.trim()) return null;
        try {
            const url = new URL(value, window.location.origin);
            return url.origin === window.location.origin && /^https?:$/.test(url.protocol) ? url.href : null;
        } catch (error) {
            return null;
        }
    }

    function closeDocumentReview() {
        document.getElementById('documentReviewDialog').close();
    }

    /* ================= PENDING COUNT ================= */

    function updatePendingCount() {
        const pending = registrationsData.filter(
            reg => reg.status === 'pending'
        ).length;

        document.getElementById('pendingCount').textContent =
            pending;

        document.getElementById('sidebarPending').textContent =
            pending;
    }

    /* ================= NOTIFICATION ================= */

    function showNotification(message) {
        const existing = document.querySelector(
            '.sable-notification'
        );

        if (existing) {
            existing.remove();
        }

        const notification = document.createElement('div');

        notification.className = 'sable-notification';
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    /* ================= HEADER SEARCH ================= */

    document.getElementById('headerSearch').addEventListener(
        'input',
        function () {
            document.getElementById('searchReg').value =
                this.value;

            filterRegistrations();
        }
    );

    /* ================= INITIALIZE ================= */

    updateRoleCounts();
    filterRegistrations();
</script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>
</html>
