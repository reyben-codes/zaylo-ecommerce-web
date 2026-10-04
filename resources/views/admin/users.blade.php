
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Manage Users</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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
            line-height: 1.4;
        }

        .container {
            width: 100%;
            min-height: 100vh;
        }

        /* ================= HEADER ================= */

        .navbar {
            position: relative;
            height: 70px;
            width: 100%;
            background: #ffffff;
            border-top: 3px solid #86a886;
            border-bottom: 1px solid #ece4db;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 0 32px;
        }

        .logo-link {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .logo-image {
            width: 78px;
            height: auto;
            max-height: 42px;
            object-fit: contain;
        }

        .header-search {
            width: 360px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f5f2ee;
            border: 1px solid #e5ddd5;
            border-radius: 25px;
            padding: 11px 17px;
        }

        .header-search i {
            color: #1a1714;
            font-size: 0.8rem;
        }

        .header-search input {
            width: 100%;
            border: none;
            outline: none;
            background: transparent;
            color: #1e1e1e;
            font-family: 'Inter', sans-serif;
            font-size: 0.75rem;
        }

        /* ================= LAYOUT ================= */

        .dashboard-wrapper {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        /* ================= SIDEBAR ================= */

        .sidebar {
            width: 245px;
            flex-shrink: 0;
            background: #ffffff;
            border-right: 1px solid #ece4db;
            padding: 25px 0;
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
            color: #6b5f54;
            text-decoration: none;
            font-size: 0.75rem;
            border-radius: 2px;
            transition: 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: #f5f0ea;
            color: #1a1714;
        }

        .sidebar-menu a.active {
            background: #1a1714;
            color: #ffffff;
        }

        .sidebar-menu a i {
            width: 18px;
            text-align: center;
            font-size: 0.85rem;
        }

        .sidebar-menu .badge {
            margin-left: auto;
            background: #b28b6f;
            color: #ffffff;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.5rem;
        }

        .sidebar-divider {
            height: 1px;
            background: #ece4db;
            margin: 15px 0;
        }

        /* ================= MAIN CONTENT ================= */

        .main-content {
            flex: 1;
            min-width: 0;
            padding: 35px 42px;
            background: #faf7f2;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 600;
            color: #1a1714;
        }

        .subtitle,
        .total-users {
            color: #6b5f54;
            font-size: 0.8rem;
        }

        .subtitle {
            margin-top: 7px;
        }

        /* ================= CATEGORY BUTTONS ================= */

        .category-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 22px;
        }

        .category-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 9px;
            padding: 13px 20px;
            background: #ffffff;
            border: 1px solid #e4dbd2;
            color: #6b5f54;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.68rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            transition: 0.2s ease;
        }

        .category-btn span:first-child {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .category-btn:hover,
        .category-btn.active {
            background: #1a1714;
            border-color: #1a1714;
            color: #ffffff;
        }

        .category-count {
            background: #b28b6f;
            color: #ffffff;
            padding: 3px 7px;
            border-radius: 10px;
            font-size: 0.55rem;
        }

        /* ================= FILTER BAR ================= */

        .filter-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            background: #ffffff;
            border: 1px solid #ece4db;
            padding: 18px 20px;
            margin-bottom: 25px;
        }

        .filter-bar input,
        .filter-bar select {
            background: #ffffff;
            color: #1e1e1e;
            border: 1px solid #e4dbd2;
            outline: none;
            padding: 10px 13px;
            font-family: 'Inter', sans-serif;
            font-size: 0.7rem;
        }

        .filter-bar input:focus,
        .filter-bar select:focus {
            border-color: #1a1714;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
        }

        .btn-filter {
            border: 1px solid #1a1714;
            background: #1a1714;
            color: #ffffff;
            padding: 10px 20px;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .btn-filter:hover {
            background: #b28b6f;
            border-color: #b28b6f;
        }

        .btn-filter.clear {
            background: transparent;
            color: #6b5f54;
            border-color: #e4dbd2;
        }

        .btn-filter.clear:hover {
            background: #f5f0ea;
            color: #1a1714;
        }

        /* ================= TABLE ================= */

        .users-table-wrapper {
            background: #ffffff;
            border: 1px solid #ece4db;
            overflow-x: auto;
        }

        .users-table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
            font-size: 0.75rem;
        }

        .users-table th {
            background: #f5f0ea;
            color: #6b5f54;
            text-align: left;
            padding: 14px 16px;
            border-bottom: 1px solid #ece4db;
            font-size: 0.62rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .users-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f2ece6;
            vertical-align: middle;
        }

        .users-table tr:hover td {
            background: #fcfaf7;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            flex-shrink: 0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #d6ccc1;
            color: #1a1714;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .user-name {
            color: #1a1714;
            font-weight: 500;
        }

        .user-email {
            color: #8a7a6b;
            font-size: 0.65rem;
            margin-top: 3px;
        }

        .role-badge,
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.55rem;
            font-weight: 500;
        }

        .role-badge.buyer {
            background: #d4e0f5;
            color: #2c6b9e;
        }

        .role-badge.seller {
            background: #d4e8d0;
            color: #2d7d46;
        }

        .role-badge.sorting-center {
            background: #ead9f4;
            color: #7b3f98;
        }

        .status-badge.active {
            background: #d6e4d0;
            color: #2d7d46;
        }

        .status-badge.suspended {
            background: #f5e0c0;
            color: #a85d16;
        }

        .status-badge.inactive {
            background: #f5e0e0;
            color: #c0392b;
        }

        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .actions button {
            width: 30px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 1px solid #e4dbd2;
            color: #1a1714;
            cursor: pointer;
            font-size: 0.65rem;
            transition: 0.2s ease;
        }

        .actions button:hover {
            background: #f5f0ea;
            border-color: #1a1714;
        }

        .actions .btn-suspend {
            color: #e67e22;
            border-color: #e67e22;
        }

        .actions .btn-suspend:hover {
            background: #e67e22;
            color: #ffffff;
        }

        .actions .btn-activate {
            color: #2d7d46;
            border-color: #2d7d46;
        }

        .actions .btn-activate:hover {
            background: #2d7d46;
            color: #ffffff;
        }

        .actions .btn-delete {
            color: #c0392b;
            border-color: #c0392b;
        }

        .actions .btn-delete:hover {
            background: #c0392b;
            color: #ffffff;
        }

        /* ================= PAGINATION ================= */

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            margin-top: 25px;
        }

        .pagination button {
            min-width: 34px;
            padding: 8px 12px;
            background: #ffffff;
            border: 1px solid #e4dbd2;
            color: #1a1714;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.7rem;
        }

        .pagination button:hover,
        .pagination button.active {
            background: #1a1714;
            color: #ffffff;
            border-color: #1a1714;
        }

        .pagination button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* ================= NOTIFICATION ================= */

        .sable-notification {
            position: fixed;
            right: 25px;
            bottom: 25px;
            z-index: 9999;
            background: #1a1714;
            color: #ffffff;
            padding: 15px 23px;
            font-size: 0.75rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(80px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ================= RESPONSIVE ================= */

        @media (max-width: 1024px) {
            .navbar {
                padding: 0 22px;
            }

            .header-search {
                width: 300px;
            }

            .sidebar {
                width: 215px;
            }

            .main-content {
                padding: 28px 25px;
            }
        }

        @media (max-width: 800px) {
            .navbar {
                height: 80px;
                padding: 10px 20px;
            }

            .logo-link {
                left: 25px;
                transform: translateY(-50%);
            }

            .logo-image {
                width: 70px;
            }

            .header-search {
                width: 250px;
            }

            .dashboard-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                min-height: auto;
                padding: 12px 0;
                border-right: none;
                border-bottom: 1px solid #ece4db;
            }

            .sidebar-menu {
                display: flex;
                overflow-x: auto;
                gap: 5px;
            }

            .sidebar-menu li {
                white-space: nowrap;
            }

            .sidebar-menu a {
                padding: 9px 12px;
            }

            .sidebar-menu .badge {
                display: none;
            }

            .sidebar-divider {
                display: none;
            }

            .main-content {
                padding: 25px 18px;
            }
        }

        @media (max-width: 560px) {
            .navbar {
                height: auto;
                min-height: 125px;
                justify-content: center;
                padding: 65px 15px 15px;
            }

            .logo-link {
                top: 32px;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            .header-search {
                width: 100%;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .page-header h1 {
                font-size: 1.6rem;
            }

            .category-buttons {
                flex-direction: column;
            }

            .category-btn {
                width: 100%;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: 100%;
            }

            .filter-bar select,
            .filter-bar button {
                width: 100%;
            }

            .sable-notification {
                left: 15px;
                right: 15px;
                bottom: 15px;
            }
        }
    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>

<div class="container">

    <!-- ================= HEADER ================= -->

    @include('admin.partials.header', ['searchId' => 'headerSearch', 'searchPlaceholder' => 'Search dashboard...', 'searchOnInput' => ''])

    <!-- ================= DASHBOARD WRAPPER ================= -->

    <div class="dashboard-wrapper">

        <!-- ================= SIDEBAR ================= -->

        @include('admin.partials.sidebar')

        <!-- ================= MAIN CONTENT ================= -->

        <main class="main-content">

            <div class="page-header">

                <div>
                    <h1>User Management</h1>

                    <p class="subtitle">
                        View and manage platform users by category
                    </p>
                </div>

                <span class="total-users" id="totalUsers">
                    10 total users
                </span>

            </div>

            <!-- ================= CATEGORY BUTTONS ================= -->

            <div class="category-buttons">

                <button
                    class="category-btn active"
                    data-category="buyer"
                    onclick="showCategory('buyer', this)"
                >
                    <span>
                        <i class="fas fa-user"></i>
                        Buyers
                    </span>

                    <span class="category-count" id="buyerCount">
                        4
                    </span>
                </button>

                <button
                    class="category-btn"
                    data-category="seller"
                    onclick="showCategory('seller', this)"
                >
                    <span>
                        <i class="fas fa-store"></i>
                        Sellers
                    </span>

                    <span class="category-count" id="sellerCount">
                        4
                    </span>
                </button>

                <button
                    class="category-btn"
                    data-category="sorting_center"
                    onclick="showCategory('sorting_center', this)"
                >
                    <span>
                        <i class="fas fa-warehouse"></i>
                        Sorting Center
                    </span>

                    <span class="category-count" id="sortingCenterCount">
                        2
                    </span>
                </button>

            </div>

            <!-- ================= FILTER BAR ================= -->

            <div class="filter-bar">

                <input
                    type="text"
                    id="searchUser"
                    class="search-input"
                    placeholder="Search by name or email..."
                    oninput="filterUsers()"
                >

                <select id="statusFilter" onchange="filterUsers()">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="inactive">Inactive</option>
                </select>

                <button class="btn-filter" onclick="filterUsers()">
                    Apply
                </button>

                <button class="btn-filter clear" onclick="clearFilters()">
                    Clear
                </button>

            </div>

            <!-- ================= USERS TABLE ================= -->

            <div class="users-table-wrapper">

                <table class="users-table">

                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody id="usersBody"></tbody>

                </table>

            </div>

            <!-- ================= PAGINATION ================= -->

            <div class="pagination" id="pagination">

                <button onclick="changePage('prev')" id="prevBtn">
                    ‹
                </button>

                <button class="active" data-page="1">
                    1
                </button>

                <button data-page="2">
                    2
                </button>

                <button data-page="3">
                    3
                </button>

                <button onclick="changePage('next')" id="nextBtn">
                    ›
                </button>

            </div>

        </main>

    </div>

</div>

<script>
    /* ================= USER DATA ================= */

    let usersData = [
        {
            id: 1,
            name: 'Juan Dela Cruz',
            email: 'juan@email.com',
            role: 'buyer',
            status: 'active',
            joined: 'Dec 15, 2024'
        },
        {
            id: 2,
            name: 'Rosa Santos',
            email: 'rosa@email.com',
            role: 'buyer',
            status: 'active',
            joined: 'Dec 10, 2024'
        },
        {
            id: 3,
            name: 'Elena Cruz',
            email: 'elena@email.com',
            role: 'buyer',
            status: 'active',
            joined: 'Dec 8, 2024'
        },
        {
            id: 4,
            name: 'Sofia Garcia',
            email: 'sofia@email.com',
            role: 'buyer',
            status: 'inactive',
            joined: 'Dec 6, 2024'
        },
        {
            id: 5,
            name: 'Maria Reyes',
            email: 'maria@email.com',
            role: 'seller',
            status: 'active',
            joined: 'Dec 14, 2024'
        },
        {
            id: 6,
            name: 'Ana Lopez',
            email: 'ana@email.com',
            role: 'seller',
            status: 'active',
            joined: 'Dec 12, 2024'
        },
        {
            id: 7,
            name: 'Carlos Garcia',
            email: 'carlos@email.com',
            role: 'seller',
            status: 'suspended',
            joined: 'Dec 11, 2024'
        },
        {
            id: 8,
            name: 'Mark Reyes',
            email: 'mark@email.com',
            role: 'seller',
            status: 'active',
            joined: 'Dec 7, 2024'
        },
        {
            id: 9,
            name: 'ZAYLO Sorting Center',
            email: 'sortingcenter@zaylo.com',
            role: 'sorting_center',
            status: 'active',
            joined: 'Dec 5, 2024'
        },
        {
            id: 10,
            name: 'Central Sorting Team',
            email: 'central@zaylo.com',
            role: 'sorting_center',
            status: 'active',
            joined: 'Dec 4, 2024'
        },
    ];

    let selectedCategory = 'buyer';
    let filteredUsers = [];
    let currentPage = 1;

    const itemsPerPage = 5;

    /* ================= ROLE LABELS ================= */

    const roleMap = {
        buyer: {
            label: 'Buyer',
            class: 'buyer'
        },

        seller: {
            label: 'Seller',
            class: 'seller'
        },

        sorting_center: {
            label: 'Sorting Center',
            class: 'sorting-center'
        },
    };

    /* ================= STATUS LABELS ================= */

    const statusMap = {
        active: {
            label: 'Active',
            class: 'active'
        },

        suspended: {
            label: 'Suspended',
            class: 'suspended'
        },

        inactive: {
            label: 'Inactive',
            class: 'inactive'
        }
    };

    /* ================= ESCAPE HTML ================= */

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    /* ================= CATEGORY SWITCHING ================= */

    function showCategory(category, button) {
        selectedCategory = category;

        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        button.classList.add('active');

        clearFilters(false);
        filterUsers();
    }

    /* ================= CATEGORY COUNTS ================= */

    function updateCategoryCounts() {
        document.getElementById('buyerCount').textContent =
            usersData.filter(user => user.role === 'buyer').length;

        document.getElementById('sellerCount').textContent =
            usersData.filter(user => user.role === 'seller').length;

        document.getElementById('sortingCenterCount').textContent =
            usersData.filter(user => user.role === 'sorting_center').length;
    }

    /* ================= RENDER USERS ================= */

    function renderUsers() {
        const tbody = document.getElementById('usersBody');

        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;

        const pageItems = filteredUsers.slice(start, end);

        document.getElementById('totalUsers').textContent =
            `${filteredUsers.length} ${roleMap[selectedCategory].label.toLowerCase()} users`;

        if (pageItems.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5"
                        style="text-align:center;padding:45px;color:#6b5f54;">
                        <i class="fas fa-users"
                           style="font-size:2rem;display:block;margin-bottom:10px;color:#b28b6f;">
                        </i>
                        No users found
                    </td>
                </tr>
            `;

            updatePagination();
            return;
        }

        let html = '';

        pageItems.forEach((user, index) => {
            const roleInfo = roleMap[user.role];
            const statusInfo = statusMap[user.status];

            const initials = user.name
                .split(' ')
                .map(name => name[0])
                .join('')
                .substring(0, 2)
                .toUpperCase();

            let actionButtons = '';

            if (user.status === 'active') {
                actionButtons = `
                    <button
                        title="View User"
                        onclick="viewUser(${user.id})">
                        <i class="fas fa-eye"></i>
                    </button>

                    <button
                        class="btn-suspend"
                        title="Suspend User"
                        onclick="suspendUser(${user.id})">
                        <i class="fas fa-pause"></i>
                    </button>

                    <button
                        class="btn-delete"
                        title="Delete User"
                        onclick="deleteUser(${user.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            } else {
                actionButtons = `
                    <button
                        title="View User"
                        onclick="viewUser(${user.id})">
                        <i class="fas fa-eye"></i>
                    </button>

                    <button
                        class="btn-activate"
                        title="Activate User"
                        onclick="activateUser(${user.id})">
                        <i class="fas fa-play"></i>
                    </button>

                    <button
                        class="btn-delete"
                        title="Delete User"
                        onclick="deleteUser(${user.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            }

            html += `
                <tr style="
                    animation: fadeInUp 0.3s ease forwards;
                    animation-delay: ${index * 0.05}s;
                    opacity: 0;
                ">
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar">
                                ${escapeHtml(initials)}
                            </div>

                            <div>
                                <div class="user-name">
                                    ${escapeHtml(user.name)}
                                </div>

                                <div class="user-email">
                                    ${escapeHtml(user.email)}
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="role-badge ${roleInfo.class}">
                            ${roleInfo.label}
                        </span>
                    </td>

                    <td>
                        <span class="status-badge ${statusInfo.class}">
                            ${statusInfo.label}
                        </span>
                    </td>

                    <td>
                        ${escapeHtml(user.joined)}
                    </td>

                    <td>
                        <div class="actions">
                            ${actionButtons}
                        </div>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        updatePagination();
    }

    /* ================= PAGINATION ================= */

    function updatePagination() {
        const totalPages = Math.ceil(filteredUsers.length / itemsPerPage);
        const pagination = document.getElementById('pagination');

        if (totalPages <= 1) {
            pagination.style.display = 'none';
            return;
        }

        pagination.style.display = 'flex';

        const pageButtons = pagination.querySelectorAll(
            'button[data-page]'
        );

        pageButtons.forEach(button => {
            const page = parseInt(button.dataset.page);

            button.classList.toggle(
                'active',
                page === currentPage
            );

            button.style.display =
                page <= totalPages ? 'inline-block' : 'none';
        });

        document.getElementById('prevBtn').disabled =
            currentPage === 1;

        document.getElementById('nextBtn').disabled =
            currentPage === totalPages;
    }

    function changePage(direction) {
        const totalPages = Math.ceil(
            filteredUsers.length / itemsPerPage
        );

        if (direction === 'prev' && currentPage > 1) {
            currentPage--;
        }

        if (direction === 'next' && currentPage < totalPages) {
            currentPage++;
        }

        renderUsers();

        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    document
        .querySelectorAll('#pagination button[data-page]')
        .forEach(button => {
            button.addEventListener('click', function () {
                currentPage = parseInt(this.dataset.page);
                renderUsers();
            });
        });

    /* ================= FILTER USERS ================= */

    function filterUsers() {
        const search = document
            .getElementById('searchUser')
            .value
            .toLowerCase()
            .trim();

        const status = document.getElementById('statusFilter').value;

        filteredUsers = usersData.filter(user => {
            const matchesCategory =
                user.role === selectedCategory;

            const matchesSearch =
                !search ||
                user.name.toLowerCase().includes(search) ||
                user.email.toLowerCase().includes(search);

            const matchesStatus =
                status === 'all' ||
                user.status === status;

            return (
                matchesCategory &&
                matchesSearch &&
                matchesStatus
            );
        });

        currentPage = 1;

        renderUsers();
        updateCategoryCounts();
    }

    function clearFilters(render = true) {
        document.getElementById('searchUser').value = '';
        document.getElementById('statusFilter').value = 'all';

        currentPage = 1;

        if (render) {
            filterUsers();
        }
    }

    /* ================= HEADER SEARCH ================= */

    document
        .getElementById('headerSearch')
        .addEventListener('input', function () {
            document.getElementById('searchUser').value = this.value;
            filterUsers();
        });

    /* ================= USER ACTIONS ================= */

    function findUser(id) {
        return usersData.find(user => user.id === id);
    }

    function viewUser(id) {
        const user = findUser(id);

        if (!user) return;

        const roleInfo = roleMap[user.role];
        const statusInfo = statusMap[user.status];

        alert(
            `USER PROFILE\n\n` +
            `Name: ${user.name}\n` +
            `Email: ${user.email}\n` +
            `Role: ${roleInfo.label}\n` +
            `Status: ${statusInfo.label}\n` +
            `Joined: ${user.joined}`
        );
    }

    function suspendUser(id) {
        const user = findUser(id);

        if (!user) return;

        if (confirm(
            `Suspend user "${user.name}"? They will not be able to access the platform.`
        )) {
            user.status = 'suspended';

            filterUsers();

            showNotification(
                `${user.name}'s account has been suspended`
            );
        }
    }

    function activateUser(id) {
        const user = findUser(id);

        if (!user) return;

        if (confirm(
            `Activate user "${user.name}"? They will regain full access.`
        )) {
            user.status = 'active';

            filterUsers();

            showNotification(
                `${user.name}'s account has been activated`
            );
        }
    }

    function deleteUser(id) {
        const user = findUser(id);

        if (!user) return;

        if (confirm(
            `Delete user "${user.name}"? This action cannot be undone.`
        )) {
            const index = usersData.indexOf(user);

            if (index > -1) {
                usersData.splice(index, 1);

                filterUsers();

                showNotification(
                    `${user.name}'s account has been deleted`
                );
            }
        }
    }

    /* ================= NOTIFICATION SYSTEM ================= */

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
            notification.style.opacity = '0';

            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    /* ================= INITIALIZE ================= */

    updateCategoryCounts();
    filterUsers();
</script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>
</html>
