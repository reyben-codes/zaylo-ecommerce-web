
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO | Handover to Courier</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* =========================
           RESET
        ========================= */

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
            background: #faf7f2;
            color: #1e1e1e;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
        }

        button,
        input,
        select {
            font-family: inherit;
        }

        /* =========================
           HEADER
        ========================= */

        .navbar {
            width: 100%;
            height: 80px;
            padding: 0 32px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1100;
            border-bottom: 1px solid #ece4db;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo img {
            width: 110px;
            height: 110px;
            object-fit: contain;
        }

        .nav-actions {
            position: absolute;
            right: 32px;
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f4f1ec;
            border: 1px solid #e5dfd8;
            border-radius: 30px;
            padding: 6px 14px;
        }

        .search-wrapper input {
            width: 120px;
            border: none;
            outline: none;
            background: transparent;
            padding: 5px;
            font-size: .75rem;
        }

        .icon-group {
            display: flex;
            align-items: center;
            gap: 17px;
        }

        .icon-group a {
            position: relative;
            color: #1e1e1e;
            font-size: .85rem;
            transition: .2s;
        }

        .icon-group a:hover {
            color: #b28b6f;
        }

        .badge-count {
            position: absolute;
            top: -10px;
            right: -10px;
            min-width: 17px;
            height: 17px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #b28b6f;
            color: white;
            border-radius: 50%;
            font-size: .55rem;
        }

        .login-text {
            border-left: 1px solid #d8d0c8;
            padding-left: 16px;
            font-size: .65rem !important;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        /* =========================
           MAIN LAYOUT
        ========================= */

        .dashboard-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            padding-top: 80px;
        }

        /* =========================
           FIXED SIDEBAR
        ========================= */

        .sidebar {
            width: 245px;
            min-width: 245px;
            height: calc(100vh - 80px);
            position: fixed;
            top: 80px;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
            background: #fff;
            border-right: 1px solid #ece4db;
            padding: 24px 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
        }

        .sidebar-menu li {
            margin-bottom: 4px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            color: #6b5f54;
            font-size: .75rem;
            transition: .2s;
        }

        .sidebar-menu a:hover {
            background: #f5f0ea;
            color: #1e1e1e;
        }

        .sidebar-menu a.active {
            background: #1a1714;
            color: #fff;
        }

        .sidebar-menu i {
            width: 18px;
            text-align: center;
        }

        .badge {
            margin-left: auto;
            background: #b28b6f;
            color: white;
            border-radius: 20px;
            padding: 3px 7px;
            font-size: .55rem;
        }

        .sidebar-divider {
            height: 1px;
            background: #ece4db;
            margin: 18px 0;
        }

        /* =========================
           SCROLLABLE MAIN CONTENT
        ========================= */

        .main-content {
            width: calc(100% - 245px);
            margin-left: 245px;
            min-height: calc(100vh - 80px);
            padding: 36px 42px;
            background: #faf7f2;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 600;
        }

        .subtitle {
            color: #6b5f54;
            font-size: .85rem;
            margin-top: 6px;
        }

        /* =========================
           TOOLS
        ========================= */

        .tools {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .tools input,
        .tools select {
            min-height: 42px;
            background: #fff;
            border: 1px solid #e5dfd8;
            padding: 10px 14px;
            outline: none;
            font-size: .75rem;
        }

        .btn {
            border: none;
            padding: 12px 18px;
            cursor: pointer;
            background: #1a1714;
            color: white;
            font-size: .7rem;
            letter-spacing: .04em;
            transition: .2s;
        }

        .btn:hover {
            background: #b28b6f;
        }

        /* =========================
           ORDERS
        ========================= */

        .orders-container {
            display: grid;
            gap: 18px;
        }

        .order-card {
            background: white;
            border: 1px solid #ece4db;
            padding: 24px;
        }

        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 18px;
        }

        .order-id {
            font-size: .9rem;
            font-weight: 600;
        }

        .order-date {
            color: #8a7a6b;
            font-size: .7rem;
            margin-top: 5px;
        }

        .status {
            padding: 6px 12px;
            font-size: .6rem;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .pending {
            background: #f5f0ea;
            color: #6b5f54;
        }

        .ready {
            background: #e8dfd6;
            color: #1a1714;
        }

        .assigned {
            background: #d9e5ed;
            color: #31566e;
        }

        .pickup {
            background: #eee0c7;
            color: #8a5b20;
        }

        .transit {
            background: #d8e4d3;
            color: #38603a;
        }

        .delivered {
            background: #d6e4d0;
            color: #2d7d46;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            border-top: 1px solid #f0ebe5;
            border-bottom: 1px solid #f0ebe5;
            padding: 18px 0;
        }

        .detail-label {
            color: #8a7a6b;
            text-transform: uppercase;
            letter-spacing: .05em;
            font-size: .6rem;
            margin-bottom: 6px;
        }

        .detail-value {
            font-size: .8rem;
            color: #1e1e1e;
            word-break: break-word;
        }

        .order-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        .order-total {
            font-size: 1rem;
            font-weight: 600;
        }

        .order-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn-small {
            border: 1px solid #d8d0c8;
            background: white;
            padding: 9px 12px;
            cursor: pointer;
            font-size: .65rem;
            transition: .2s;
        }

        .btn-small:hover {
            background: #f5f0ea;
        }

        .empty-state {
            background: white;
            border: 1px solid #ece4db;
            padding: 50px;
            text-align: center;
            color: #8a7a6b;
        }

        /* =========================
           MODALS
        ========================= */

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
        }

        .modal-content {
            width: 100%;
            max-width: 430px;
            background: white;
            padding: 28px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-family: 'Playfair Display', serif;
        }

        .close {
            cursor: pointer;
            font-size: 1.3rem;
        }

        .modal-content label {
            display: block;
            font-size: .7rem;
            margin: 14px 0 6px;
        }

        .modal-content select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5dfd8;
        }

        .modal-content .btn {
            width: 100%;
            margin-top: 20px;
        }

        .barcode {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 3px;
            height: 90px;
            margin: 20px 0;
        }

        .barcode span {
            display: block;
            height: 80px;
            background: #1a1714;
        }

        /* =========================
           TABLET
        ========================= */

        @media (max-width: 1100px) {

            .sidebar {
                width: 215px;
                min-width: 215px;
            }

            .main-content {
                width: calc(100% - 215px);
                margin-left: 215px;
                padding: 30px;
            }

            .order-details {
                grid-template-columns: repeat(2, 1fr);
            }

            .nav-actions {
                right: 20px;
            }

            .search-wrapper {
                display: none;
            }
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 760px) {

            .navbar {
                height: 85px;
                padding: 0 12px;
            }

            .logo img {
                width: 95px;
                height: 95px;
            }

            .nav-actions {
                right: 12px;
                gap: 10px;
            }

            .icon-group {
                gap: 12px;
            }

            .login-text {
                display: none;
            }

            .dashboard-wrapper {
                padding-top: 85px;
            }

            .sidebar {
                position: fixed;
                top: 85px;
                left: 0;
                width: 100%;
                min-width: 100%;
                height: 58px;
                padding: 8px 0;
                overflow-x: auto;
                overflow-y: hidden;
                border-right: none;
                border-bottom: 1px solid #ece4db;
            }

            .sidebar-menu {
                display: flex;
                gap: 5px;
                padding: 0 12px;
                white-space: nowrap;
            }

            .sidebar-menu li {
                flex-shrink: 0;
                margin-bottom: 0;
            }

            .sidebar-menu a {
                padding: 11px 13px;
            }

            .sidebar-divider {
                display: none;
            }

            .main-content {
                width: 100%;
                margin-left: 0;
                padding: 82px 16px 24px;
            }

            .page-header h1 {
                font-size: 1.7rem;
            }
        }

        /* =========================
           SMALL MOBILE
        ========================= */

        @media (max-width: 480px) {

            .navbar {
                height: 78px;
            }

            .logo img {
                width: 85px;
                height: 85px;
            }

            .nav-actions {
                right: 8px;
            }

            .dashboard-wrapper {
                padding-top: 78px;
            }

            .sidebar {
                top: 78px;
            }

            .main-content {
                padding: 82px 10px 20px;
            }

            .page-header h1 {
                font-size: 1.45rem;
            }

            .subtitle {
                font-size: .75rem;
            }

            .tools input,
            .tools select,
            .tools .btn {
                width: 100%;
            }

            .order-card {
                padding: 18px;
            }

            .order-details {
                grid-template-columns: 1fr;
            }

            .order-bottom {
                align-items: flex-start;
                flex-direction: column;
            }

            .order-actions {
                width: 100%;
            }

            .btn-small {
                flex: 1;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
</head>

<body class="seller-workspace">

    <!-- =========================
         HEADER
    ========================= -->

    <header class="navbar">

        <a href="{{ route('seller.dashboard') }}" class="logo">
            <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO Logo">
        </a>

        <div class="nav-actions">

            <div class="search-wrapper">
                <i class="fas fa-search"></i>
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search">
            </div>

            <div class="icon-group">

                <a href="#" title="Notifications">
                    <i class="far fa-bell"></i>
                    <span class="badge-count">5</span>
                </a>

                <a href="#" title="Cart">
                    <i class="fas fa-shopping-bag"></i>
                    <span class="badge-count">2</span>
                </a>

                <a href="{{ route('seller.account') }}" title="Account">
                    <i class="far fa-user"></i>
                </a>

                <a href="{{ route('login') }}" class="login-text">
                    Logout
                </a>

            </div>
        </div>

    </header>

    <!-- =========================
         DASHBOARD WRAPPER
    ========================= -->

    <div class="dashboard-wrapper">

        <!-- SIDEBAR -->

        @include('partials.seller-sidebar')

        <!-- MAIN CONTENT -->

        <main class="main-content">

            <div class="page-header">

                <div>
                    <h1>Handover to Courier</h1>

                    <p class="subtitle">
                        Prepare and hand over customer orders to the courier.
                    </p>
                </div>

            </div>

            <!-- TOOLS -->

            <div class="tools">

                <input
                    type="text"
                    id="orderRefInput"
                    placeholder="Enter order reference">

                <button class="btn" onclick="generateBarcode()">
                    <i class="fas fa-barcode"></i>
                    Generate Barcode
                </button>

                <select id="courierSelect">
                    <option value="">Select Courier</option>
                    <option value="J&T Express">J&T Express</option>
                    <option value="LBC Express">LBC Express</option>
                    <option value="JRS Express">JRS Express</option>
                    <option value="Flash Express">Flash Express</option>
                </select>

            </div>

            <!-- ORDERS -->

            <div class="orders-container" id="ordersContainer"></div>

        </main>

    </div>

    <!-- =========================
         ASSIGN COURIER MODAL
    ========================= -->

    <div class="modal" id="assignModal">

        <div class="modal-content">

            <div class="modal-header">
                <h2>Assign Courier</h2>

                <span class="close" onclick="closeAssignModal()">
                    &times;
                </span>
            </div>

            <p id="assignOrderReference"></p>

            <label for="assignCourierSelect">
                Courier
            </label>

            <select id="assignCourierSelect">

                <option value="">Select Courier</option>
                <option value="J&T Express">J&T Express</option>
                <option value="LBC Express">LBC Express</option>
                <option value="JRS Express">JRS Express</option>
                <option value="Flash Express">Flash Express</option>

            </select>

            <button class="btn" onclick="assignToCourier()">
                Confirm Assignment
            </button>

        </div>

    </div>

    <!-- =========================
         BARCODE MODAL
    ========================= -->

    <div class="modal" id="barcodeModal">

        <div class="modal-content">

            <div class="modal-header">
                <h2>Order Barcode</h2>

                <span class="close" onclick="closeBarcode()">
                    &times;
                </span>
            </div>

            <p id="barcodeRef"></p>

            <div class="barcode" id="barcodeGraphic"></div>

            <button class="btn" onclick="window.print()">
                <i class="fas fa-print"></i>
                Print Barcode
            </button>

        </div>

    </div>

    <!-- =========================
         JAVASCRIPT
    ========================= -->

    <script>

        let selectedOrderId = null;

        let ordersData = [
            {
                id: 'ZYL-2024-001',
                customer: 'Juan Dela Cruz',
                address: 'Santa Cruz, Laguna',
                items: '2 items',
                total: 4250,
                date: 'December 15, 2024',
                status: 'ready',
                assignedTo: ''
            },
            {
                id: 'ZYL-2024-002',
                customer: 'Maria Reyes',
                address: 'Calamba, Laguna',
                items: '1 item',
                total: 1280,
                date: 'December 14, 2024',
                status: 'assigned',
                assignedTo: 'J&T Express'
            },
            {
                id: 'ZYL-2024-003',
                customer: 'Pedro Santos',
                address: 'Los Baños, Laguna',
                items: '3 items',
                total: 3750,
                date: 'December 12, 2024',
                status: 'pickup',
                assignedTo: 'LBC Express'
            },
            {
                id: 'ZYL-2024-004',
                customer: 'Ana Lopez',
                address: 'Biñan, Laguna',
                items: '1 item',
                total: 890,
                date: 'December 10, 2024',
                status: 'delivered',
                assignedTo: 'JRS Express'
            },
            {
                id: 'ZYL-2024-005',
                customer: 'Carlo Mendoza',
                address: 'Pagsanjan, Laguna',
                items: '2 items',
                total: 2150,
                date: 'December 9, 2024',
                status: 'pending',
                assignedTo: ''
            }
        ];

        const statusLabels = {
            pending: 'Pending',
            ready: 'Ready for Handover',
            assigned: 'Courier Assigned',
            pickup: 'For Pickup',
            transit: 'In Transit',
            delivered: 'Delivered'
        };

        function renderOrders() {

            const container =
                document.getElementById('ordersContainer');

            const search =
                document.getElementById('searchInput').value.toLowerCase();

            const filteredOrders = ordersData.filter(order =>

                order.id.toLowerCase().includes(search) ||
                order.customer.toLowerCase().includes(search) ||
                order.status.toLowerCase().includes(search)

            );

            if (filteredOrders.length === 0) {

                container.innerHTML = `
                    <div class="empty-state">
                        No orders found.
                    </div>
                `;

                return;
            }

            container.innerHTML = filteredOrders.map(order => `

                <div class="order-card">

                    <div class="order-top">

                        <div>
                            <div class="order-id">
                                #${order.id}
                            </div>

                            <div class="order-date">
                                ${order.date}
                            </div>
                        </div>

                        <span class="status ${order.status}">
                            ${statusLabels[order.status] || order.status}
                        </span>

                    </div>

                    <div class="order-details">

                        <div>
                            <div class="detail-label">
                                Customer
                            </div>

                            <div class="detail-value">
                                ${order.customer}
                            </div>
                        </div>

                        <div>
                            <div class="detail-label">
                                Delivery Address
                            </div>

                            <div class="detail-value">
                                ${order.address}
                            </div>
                        </div>

                        <div>
                            <div class="detail-label">
                                Items
                            </div>

                            <div class="detail-value">
                                ${order.items}
                            </div>
                        </div>

                        <div>
                            <div class="detail-label">
                                Courier
                            </div>

                            <div class="detail-value">
                                ${order.assignedTo || 'Not assigned'}
                            </div>
                        </div>

                    </div>

                    <div class="order-bottom">

                        <div class="order-total">
                            ₱${order.total.toLocaleString('en-PH', {
                                minimumFractionDigits: 2
                            })}
                        </div>

                        <div class="order-actions">

                            <button
                                class="btn-small"
                                onclick="openAssignModal('${order.id}')">
                                Assign Courier
                            </button>

                            <button
                                class="btn-small"
                                onclick="generateBarcodeFor('${order.id}')">
                                Barcode
                            </button>

                            <button
                                class="btn-small"
                                onclick="updateOrderStatus('${order.id}')">
                                Update Status
                            </button>

                        </div>

                    </div>

                </div>

            `).join('');

        }

        function openAssignModal(orderId) {

            selectedOrderId = orderId;

            document.getElementById('assignOrderReference')
                .textContent = `Order: ${orderId}`;

            document.getElementById('assignCourierSelect').value = '';

            document.getElementById('assignModal')
                .style.display = 'flex';

        }

        function closeAssignModal() {

            document.getElementById('assignModal')
                .style.display = 'none';

            selectedOrderId = null;

        }

        function assignToCourier() {

            const courier =
                document.getElementById('assignCourierSelect').value;

            if (!courier) {

                alert('Please select a courier.');

                return;
            }

            const order = ordersData.find(
                item => item.id === selectedOrderId
            );

            if (!order) return;

            order.assignedTo = courier;
            order.status = 'assigned';

            closeAssignModal();
            renderOrders();

            showNotification(
                `Courier assigned to ${order.id}.`
            );

        }

        function updateOrderStatus(orderId) {

            const order = ordersData.find(
                item => item.id === orderId
            );

            if (!order) return;

            const statuses = [
                'pending',
                'ready',
                'assigned',
                'pickup',
                'transit',
                'delivered'
            ];

            const currentIndex = statuses.indexOf(order.status);

            const nextIndex =
                (currentIndex + 1) % statuses.length;

            order.status = statuses[nextIndex];

            renderOrders();

            showNotification(
                `${order.id} status updated to ${statusLabels[order.status]}.`
            );

        }

        function generateBarcode() {

            const reference =
                document.getElementById('orderRefInput').value.trim();

            if (!reference) {

                alert('Please enter an order reference.');

                return;
            }

            generateBarcodeFor(reference);

        }

        function generateBarcodeFor(orderId) {

            document.getElementById('barcodeRef')
                .textContent = `Order Reference: ${orderId}`;

            const barcodeGraphic =
                document.getElementById('barcodeGraphic');

            barcodeGraphic.innerHTML = '';

            for (let i = 0; i < 45; i++) {

                const bar = document.createElement('span');

                bar.style.width =
                    `${Math.floor(Math.random() * 4) + 1}px`;

                barcodeGraphic.appendChild(bar);

            }

            document.getElementById('barcodeModal')
                .style.display = 'flex';

        }

        function closeBarcode() {

            document.getElementById('barcodeModal')
                .style.display = 'none';

        }

        function showNotification(message) {

            const existing =
                document.querySelector('.zaylo-notification');

            if (existing) {
                existing.remove();
            }

            const notification =
                document.createElement('div');

            notification.className = 'zaylo-notification';

            notification.textContent = message;

            notification.style.cssText = `
                position: fixed;
                bottom: 24px;
                right: 24px;
                background: #1a1714;
                color: white;
                padding: 14px 22px;
                z-index: 3000;
                font-size: .75rem;
                box-shadow: 0 4px 16px rgba(0,0,0,.2);
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 3000);

        }

        document.getElementById('searchInput')
            .addEventListener('input', renderOrders);

        window.addEventListener('click', function(event) {

            if (
                event.target ===
                document.getElementById('assignModal')
            ) {
                closeAssignModal();
            }

            if (
                event.target ===
                document.getElementById('barcodeModal')
            ) {
                closeBarcode();
            }

        });

        renderOrders();

    </script>

</body>
</html>
