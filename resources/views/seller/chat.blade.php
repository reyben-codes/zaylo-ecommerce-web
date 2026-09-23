
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SABLE · Seller Messages</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --cream: #faf7f2;
            --white: #ffffff;
            --dark: #1a1714;
            --text: #1e1e1e;
            --muted: #6b5f54;
            --brown: #b28b6f;
            --light-brown: #d6ccc1;
            --border: #ece4db;
            --hover: #f5f0ea;
            --green: #2d7d46;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
        }

        body {
            background: var(--cream);
            color: var(--text);
            font-family: "Inter", "Helvetica Neue", sans-serif;
            line-height: 1.4;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
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
            max-width: none;
            min-height: 100vh;
            margin: 0;
            background: var(--cream);
        }

        /* ================================
           NAVBAR
        ================================= */

        .navbar {
            width: 100%;
            min-height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 0 32px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            box-shadow: 0 1px 0 rgba(0, 0, 0, 0.02);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 26px;
            flex-wrap: wrap;
        }

        .nav-links a {
            position: relative;
            color: var(--text);
            font-size: 0.7rem;
            font-weight: 450;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            transition: color 0.2s ease;
        }

        .nav-links a:hover {
            color: var(--brown);
        }

        .nav-links .home-link {
            font-weight: 600;
        }

        .nav-links .home-link::after {
            content: "";
            position: absolute;
            left: 0;
            bottom: -5px;
            width: 100%;
            height: 1.5px;
            background: var(--dark);
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo img {
            width: 78px;
            height: auto;
            display: block;
            transition: transform 0.2s ease;
        }

        .logo:hover img {
            transform: scale(1.05);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 16px;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            background: #f4f1ec;
            border: 1px solid #e5dfd8;
            border-radius: 30px;
            transition: border-color 0.2s ease;
        }

        .search-wrapper:focus-within {
            border-color: var(--brown);
        }

        .search-icon {
            color: #4a4037;
            font-size: 0.95rem;
        }

        .search-wrapper input {
            width: 90px;
            padding: 6px 0;
            border: none;
            outline: none;
            background: transparent;
            color: var(--text);
            font-size: 0.7rem;
        }

        .search-wrapper input::placeholder {
            color: #a89b8c;
        }

        .icon-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .icon-group a {
            position: relative;
            color: var(--text);
            transition: color 0.2s ease;
        }

        .icon-group a:hover {
            color: var(--brown);
        }

        .icon-group i {
            color: #2c241e;
            font-size: 1.1rem;
        }

        .badge-count {
            position: absolute;
            top: -9px;
            right: -9px;
            min-width: 18px;
            padding: 2px 5px;
            border-radius: 50%;
            background: var(--brown);
            color: white;
            font-size: 0.55rem;
            font-weight: 600;
            text-align: center;
        }

        .login-text {
            padding-left: 16px;
            border-left: 1px solid #d8d0c8;
            color: var(--text) !important;
            font-size: 0.7rem !important;
            font-weight: 500;
            letter-spacing: 0.04em;
        }

        /* ================================
           DASHBOARD LAYOUT
        ================================= */

        .dashboard-wrapper {
            display: flex;
            width: 100%;
            min-height: calc(100vh - 68px);
            background: var(--cream);
        }

        /* ================================
           SIDEBAR
        ================================= */

        .sidebar {
            width: 240px;
            flex-shrink: 0;
            min-height: calc(100vh - 68px);
            padding: 24px 0;
            background: var(--white);
            border-right: 1px solid var(--border);
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 0 16px;
            list-style: none;
        }

        .sidebar-menu li {
            width: 100%;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            width: 100%;
            padding: 11px 14px;
            border-radius: 0;
            color: var(--muted);
            font-size: 0.75rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: var(--hover);
            color: var(--text);
        }

        .sidebar-menu a.active {
            background: var(--dark);
            color: white;
        }

        .sidebar-menu a i {
            width: 18px;
            font-size: 0.85rem;
            text-align: center;
        }

        .sidebar-menu .badge {
            margin-left: auto;
            padding: 2px 8px;
            border-radius: 10px;
            background: var(--brown);
            color: white;
            font-size: 0.5rem;
            font-weight: 600;
        }

        .sidebar-divider {
            height: 1px;
            margin: 12px 0;
            background: var(--border);
        }

        /* ================================
           CHAT LAYOUT - FULL SCREEN
        ================================= */

        .chat-layout {
            display: grid;
            grid-template-columns: 340px minmax(0, 1fr);
            flex: 1;
            width: 100%;
            min-width: 0;
            min-height: calc(100vh - 68px);
            background: var(--white);
        }

        /* ================================
           CONVERSATIONS SIDEBAR
        ================================= */

        .chat-sidebar {
            min-width: 0;
            overflow-y: auto;
            background: var(--cream);
            border-right: 1px solid var(--border);
        }

        .chat-sidebar-header {
            position: sticky;
            top: 0;
            z-index: 2;
            padding: 20px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .chat-sidebar-header h3 {
            margin-bottom: 10px;
            color: var(--dark);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .search-chat {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            outline: none;
            background: white;
            color: var(--text);
            font-size: 0.72rem;
        }

        .search-chat:focus {
            border-color: var(--dark);
        }

        .conversation-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            border-bottom: 1px solid #f5f0ea;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .conversation-item:hover {
            background: var(--hover);
        }

        .conversation-item.active {
            background: var(--border);
        }

        .avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 44px;
            height: 44px;
            overflow: hidden;
            border-radius: 50%;
            background: var(--light-brown);
            color: var(--dark);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-info .name {
            overflow: hidden;
            color: var(--dark);
            font-size: 0.78rem;
            font-weight: 500;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .conversation-info .last-message {
            overflow: hidden;
            color: var(--muted);
            font-size: 0.68rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .conversation-meta {
            flex-shrink: 0;
            text-align: right;
        }

        .conversation-meta .time {
            color: var(--muted);
            font-size: 0.6rem;
        }

        .conversation-meta .unread {
            display: inline-block;
            min-width: 18px;
            margin-top: 4px;
            padding: 2px 7px;
            border-radius: 10px;
            background: var(--brown);
            color: white;
            font-size: 0.55rem;
            font-weight: 600;
        }

        .empty-conversations {
            padding: 40px 20px;
            color: var(--muted);
            font-size: 0.8rem;
            text-align: center;
        }

        .empty-conversations i {
            display: block;
            margin-bottom: 10px;
            color: var(--brown);
            font-size: 1.5rem;
        }

        /* ================================
           CHAT MAIN
        ================================= */

        .chat-main {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: calc(100vh - 68px);
            background: var(--white);
        }

        .chat-main-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 28px;
            background: white;
            border-bottom: 1px solid var(--border);
        }

        .chat-main-header .avatar {
            width: 38px;
            height: 38px;
            font-size: 0.75rem;
        }

        .chat-name {
            color: var(--dark);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .chat-status {
            color: var(--green);
            font-size: 0.65rem;
        }

        .chat-status.offline {
            color: var(--muted);
        }

        .chat-actions {
            display: flex;
            gap: 16px;
            margin-left: auto;
        }

        .chat-actions button {
            padding: 4px;
            border: none;
            background: transparent;
            color: var(--muted);
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }

        .chat-actions button:hover {
            color: var(--dark);
        }

        .chat-messages {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 10px;
            min-height: 0;
            padding: 28px;
            overflow-y: auto;
            background: white;
        }

        .message {
            max-width: min(75%, 620px);
            padding: 11px 16px;
            border-radius: 12px;
            font-size: 0.8rem;
            line-height: 1.5;
            overflow-wrap: anywhere;
            animation: messageIn 0.2s ease forwards;
        }

        @keyframes messageIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.sent {
            align-self: flex-end;
            border-radius: 12px 12px 4px 12px;
            background: var(--dark);
            color: white;
        }

        .message.received {
            align-self: flex-start;
            border-radius: 12px 12px 12px 4px;
            background: var(--hover);
            color: var(--dark);
        }

        .message-time {
            display: block;
            margin-top: 5px;
            font-size: 0.55rem;
            opacity: 0.6;
        }

        .message.sent .message-time {
            text-align: right;
        }

        .no-conversation {
            display: flex;
            flex: 1;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            padding: 40px;
            color: var(--muted);
            text-align: center;
        }

        .no-conversation i {
            margin-bottom: 18px;
            color: var(--brown);
            font-size: 3.5rem;
        }

        .no-conversation h3 {
            margin-bottom: 6px;
            color: var(--dark);
            font-family: "Playfair Display", serif;
            font-size: 1.4rem;
        }

        .no-conversation p {
            font-size: 0.8rem;
        }

        /* ================================
           CHAT INPUT
        ================================= */

        .chat-input {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 28px;
            background: white;
            border-top: 1px solid var(--border);
        }

        .chat-input input {
            flex: 1;
            min-width: 0;
            padding: 12px 16px;
            border: 1px solid var(--border);
            outline: none;
            background: var(--cream);
            color: var(--text);
            font-size: 0.8rem;
        }

        .chat-input input:focus {
            border-color: var(--dark);
            background: white;
        }

        .btn-send,
        .btn-attach {
            flex-shrink: 0;
            padding: 12px 18px;
            border: 1px solid var(--border);
            font-size: 0.72rem;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .btn-send {
            padding-right: 26px;
            padding-left: 26px;
            border-color: var(--dark);
            background: var(--dark);
            color: white;
            font-weight: 500;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .btn-send:hover {
            border-color: var(--brown);
            background: var(--brown);
        }

        .btn-attach {
            background: transparent;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .btn-attach:hover {
            border-color: var(--dark);
            color: var(--dark);
        }

        /* ================================
           NOTIFICATION
        ================================= */

        .sable-notification {
            position: fixed;
            right: 24px;
            bottom: 24px;
            z-index: 9999;
            max-width: calc(100vw - 48px);
            padding: 14px 24px;
            background: var(--dark);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            color: white;
            font-size: 0.8rem;
            animation: slideUp 0.3s ease;
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
           RESPONSIVE
        ================================= */

        @media (max-width: 1200px) {
            .navbar {
                padding: 0 22px;
            }

            .nav-links {
                gap: 16px;
            }

            .sidebar {
                width: 220px;
            }

            .chat-layout {
                grid-template-columns: 300px minmax(0, 1fr);
            }
        }

        @media (max-width: 960px) {
            .navbar {
                flex-wrap: wrap;
                justify-content: center;
                padding: 12px 20px;
            }

            .nav-links,
            .nav-actions {
                justify-content: center;
            }

            .logo {
                order: -1;
                width: 100%;
                text-align: center;
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
                flex-direction: row;
                gap: 4px;
                padding: 0 16px;
                overflow-x: auto;
            }

            .sidebar-menu li {
                width: auto;
                flex-shrink: 0;
            }

            .sidebar-menu a {
                width: auto;
                padding: 9px 13px;
                white-space: nowrap;
            }

            .sidebar-menu .badge {
                display: none;
            }

            .sidebar-divider {
                display: none;
            }

            .chat-layout {
                grid-template-columns: 300px minmax(0, 1fr);
                min-height: calc(100vh - 180px);
            }

            .chat-main {
                min-height: calc(100vh - 180px);
            }
        }

        @media (max-width: 700px) {
            .chat-layout {
                display: flex;
                flex-direction: column;
                min-height: auto;
            }

            .chat-sidebar {
                max-height: 290px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .chat-main {
                min-height: 520px;
            }

            .chat-messages {
                min-height: 280px;
                padding: 20px 16px;
            }

            .chat-main-header {
                padding: 14px 16px;
            }

            .chat-input {
                flex-wrap: wrap;
                padding: 14px 16px;
            }

            .chat-input input {
                order: 1;
                flex-basis: 100%;
                width: 100%;
            }

            .btn-attach {
                order: 2;
            }

            .btn-send {
                order: 3;
                flex: 1;
            }

            .message {
                max-width: 88%;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                gap: 12px;
                padding: 12px;
            }

            .nav-links {
                gap: 10px;
            }

            .nav-links a {
                font-size: 0.6rem;
            }

            .nav-actions {
                gap: 10px;
            }

            .search-wrapper input {
                width: 65px;
            }

            .icon-group {
                gap: 12px;
            }

            .chat-sidebar-header {
                padding: 16px;
            }

            .conversation-item {
                padding: 12px 16px;
            }

            .conversation-item .avatar {
                width: 38px;
                height: 38px;
                font-size: 0.7rem;
            }

            .chat-main {
                min-height: 480px;
            }

            .chat-messages {
                min-height: 220px;
            }

            .message {
                max-width: 94%;
            }

            .sable-notification {
                right: 16px;
                bottom: 16px;
                left: 16px;
                max-width: none;
            }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/seller-sidebar.css') }}?v={{ filemtime(public_path('css/seller-sidebar.css')) }}">
</head>

<body class="seller-workspace">

    <div class="container">

        <!-- ================================
             HEADER
        ================================= -->

        <header class="navbar">

            <nav class="nav-links">
                <a href="{{ route('seller.dashboard') }}" class="home-link">Home</a>
                <a href="#">Clothing</a>
                <a href="#">Bags</a>
                <a href="#">Shoes</a>
                <a href="#">Accessories</a>
            </nav>

            <a href="{{ route('seller.dashboard') }}" class="logo" aria-label="ZAYLO seller dashboard">
                <img src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}" alt="ZAYLO">
            </a>

            <div class="nav-actions">

                <div class="search-wrapper">
                    <span class="search-icon">⌕</span>
                    <input type="text" placeholder="Search">
                </div>

                <div class="icon-group">

                    <a href="#" aria-label="Notifications">
                        <i class="far fa-bell"></i>
                        <span class="badge-count">5</span>
                    </a>

                    <a href="#" aria-label="Shopping bag">
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

        <!-- ================================
             DASHBOARD
        ================================= -->

        <div class="dashboard-wrapper">

            <!-- SIDEBAR -->

            @include('partials.seller-sidebar')

            <!-- CHAT AREA -->

            <div class="chat-layout">

                <!-- CONVERSATIONS LIST -->

                <div class="chat-sidebar" id="chatSidebar">

                    <div class="chat-sidebar-header">

                        <h3>Conversations</h3>

                        <input
                            type="text"
                            class="search-chat"
                            id="searchConversations"
                            placeholder="Search conversations..."
                            oninput="filterConversations(this.value)"
                        >

                    </div>

                    <div id="conversationList"></div>

                </div>

                <!-- CHAT MAIN -->

                <div class="chat-main" id="chatMain">

                    <!-- EMPTY STATE -->

                    <div class="no-conversation" id="noConversation">

                        <i class="fas fa-comment-dots"></i>

                        <h3>Select a conversation</h3>

                        <p>
                            Choose a conversation from the list to start messaging.
                        </p>

                    </div>

                    <!-- CHAT HEADER -->

                    <div
                        class="chat-main-header"
                        id="chatHeader"
                        style="display: none;"
                    >

                        <div class="avatar" id="chatAvatar">
                            JD
                        </div>

                        <div>
                            <div class="chat-name" id="chatName">
                                Buyer Name
                            </div>

                            <div class="chat-status" id="chatStatus">
                                Online
                            </div>
                        </div>

                        <div class="chat-actions">

                            <button
                                type="button"
                                aria-label="Call buyer"
                                onclick="showNotification('Call feature coming soon!')"
                            >
                                <i class="fas fa-phone"></i>
                            </button>

                            <button
                                type="button"
                                aria-label="Video call buyer"
                                onclick="showNotification('Video call feature coming soon!')"
                            >
                                <i class="fas fa-video"></i>
                            </button>

                        </div>

                    </div>

                    <!-- MESSAGES -->

                    <div
                        class="chat-messages"
                        id="chatMessages"
                        style="display: none;"
                    ></div>

                    <!-- CHAT INPUT -->

                    <div
                        class="chat-input"
                        id="chatInput"
                        style="display: none;"
                    >

                        <button
                            type="button"
                            class="btn-attach"
                            aria-label="Attach file"
                            onclick="attachFile()"
                        >
                            <i class="fas fa-paperclip"></i>
                        </button>

                        <input
                            type="text"
                            id="messageInput"
                            placeholder="Type a message..."
                            onkeydown="handleKeyPress(event)"
                        >

                        <button
                            type="button"
                            class="btn-send"
                            onclick="sendMessage()"
                        >
                            Send
                        </button>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script>
        /* ================================
           CHAT DATA
        ================================= */

        const conversations = [
            {
                id: 1,
                name: "Juan Dela Cruz",
                avatar: "JD",
                lastMessage: "When will my order be delivered?",
                time: "2:30 PM",
                unread: 2,
                online: true,
                messages: [
                    {
                        text: "Hello! I have a question about my order.",
                        time: "2:15 PM",
                        type: "received"
                    },
                    {
                        text: "Hi Juan! What can I help you with?",
                        time: "2:18 PM",
                        type: "sent"
                    },
                    {
                        text: "When will my order be delivered?",
                        time: "2:20 PM",
                        type: "received"
                    },
                    {
                        text: "Your order is being prepared for shipping. You will get a tracking number soon.",
                        time: "2:25 PM",
                        type: "sent"
                    },
                    {
                        text: "Thanks for the update!",
                        time: "2:28 PM",
                        type: "received"
                    },
                    {
                        text: "You are welcome! Let me know if you need anything else.",
                        time: "2:30 PM",
                        type: "sent"
                    }
                ]
            },
            {
                id: 2,
                name: "Maria Reyes",
                avatar: "MR",
                lastMessage: "Can I return this item?",
                time: "11:45 AM",
                unread: 0,
                online: false,
                messages: [
                    {
                        text: "I received the bag today. It is beautiful!",
                        time: "11:30 AM",
                        type: "received"
                    },
                    {
                        text: "I am so glad you like it!",
                        time: "11:35 AM",
                        type: "sent"
                    },
                    {
                        text: "Actually, I think I need a different color. Can I return this item?",
                        time: "11:40 AM",
                        type: "received"
                    },
                    {
                        text: "Of course! You can initiate a return from your orders page.",
                        time: "11:45 AM",
                        type: "sent"
                    }
                ]
            },
            {
                id: 3,
                name: "Pedro Santos",
                avatar: "PS",
                lastMessage: "Do you have these in size 10?",
                time: "Yesterday",
                unread: 1,
                online: true,
                messages: [
                    {
                        text: "I love the sneakers! Do you have these in size 10?",
                        time: "Yesterday 3:00 PM",
                        type: "received"
                    },
                    {
                        text: "Let me check our inventory for you.",
                        time: "Yesterday 3:05 PM",
                        type: "sent"
                    },
                    {
                        text: "We have size 10 in stock! Would you like to place an order?",
                        time: "Yesterday 3:10 PM",
                        type: "sent"
                    },
                    {
                        text: "Do you have these in size 10?",
                        time: "Yesterday 3:15 PM",
                        type: "received"
                    }
                ]
            },
            {
                id: 4,
                name: "Ana Lopez",
                avatar: "AL",
                lastMessage: "Thank you for the fast shipping!",
                time: "Dec 14",
                unread: 0,
                online: false,
                messages: [
                    {
                        text: "I received my order today!",
                        time: "Dec 14 9:00 AM",
                        type: "received"
                    },
                    {
                        text: "Thank you for the fast shipping!",
                        time: "Dec 14 9:30 AM",
                        type: "received"
                    },
                    {
                        text: "You are very welcome! Enjoy your purchase!",
                        time: "Dec 14 9:45 AM",
                        type: "sent"
                    }
                ]
            }
        ];

        let currentConversationId = null;
        let filteredConversations = [...conversations];

        /* ================================
           ESCAPE HTML
        ================================= */

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        /* ================================
           RENDER CONVERSATIONS
        ================================= */

        function renderConversations() {
            const list = document.getElementById("conversationList");

            if (filteredConversations.length === 0) {
                list.innerHTML = `
                    <div class="empty-conversations">
                        <i class="fas fa-search"></i>
                        No conversations found
                    </div>
                `;

                return;
            }

            let html = "";

            filteredConversations.forEach((conversation) => {
                const isActive =
                    conversation.id === currentConversationId;

                const unreadBadge =
                    conversation.unread > 0
                        ? `<span class="unread">${conversation.unread}</span>`
                        : "";

                const onlineStatus = conversation.online ? "🟢" : "⚪";

                html += `
                    <div
                        class="conversation-item ${isActive ? "active" : ""}"
                        onclick="selectConversation(${conversation.id})"
                        role="button"
                        tabindex="0"
                        onkeydown="if (event.key === 'Enter') selectConversation(${conversation.id})"
                    >

                        <div class="avatar">
                            ${escapeHtml(conversation.avatar)}
                        </div>

                        <div class="conversation-info">

                            <div class="name">
                                ${escapeHtml(conversation.name)}
                                ${onlineStatus}
                            </div>

                            <div class="last-message">
                                ${escapeHtml(conversation.lastMessage)}
                            </div>

                        </div>

                        <div class="conversation-meta">

                            <div class="time">
                                ${escapeHtml(conversation.time)}
                            </div>

                            ${unreadBadge}

                        </div>

                    </div>
                `;
            });

            list.innerHTML = html;
        }

        /* ================================
           FILTER CONVERSATIONS
        ================================= */

        function filterConversations(query) {
            const search = query.toLowerCase().trim();

            if (!search) {
                filteredConversations = [...conversations];
            } else {
                filteredConversations = conversations.filter((conversation) => {
                    return (
                        conversation.name.toLowerCase().includes(search) ||
                        conversation.lastMessage.toLowerCase().includes(search)
                    );
                });
            }

            renderConversations();
        }

        /* ================================
           SELECT CONVERSATION
        ================================= */

        function selectConversation(id) {
            currentConversationId = id;

            const conversation = conversations.find(
                (item) => item.id === id
            );

            if (!conversation) {
                return;
            }

            conversation.unread = 0;

            renderConversations();

            document.getElementById("noConversation").style.display = "none";
            document.getElementById("chatHeader").style.display = "flex";
            document.getElementById("chatMessages").style.display = "flex";
            document.getElementById("chatInput").style.display = "flex";

            document.getElementById("chatAvatar").textContent =
                conversation.avatar;

            document.getElementById("chatName").textContent =
                conversation.name;

            const statusElement = document.getElementById("chatStatus");

            statusElement.textContent = conversation.online
                ? "Online"
                : "Offline";

            statusElement.className = conversation.online
                ? "chat-status"
                : "chat-status offline";

            renderMessages(conversation.messages);

            scrollMessagesToBottom();

            document.getElementById("messageInput").focus();
        }

        /* ================================
           RENDER MESSAGES
        ================================= */

        function renderMessages(messages) {
            const container = document.getElementById("chatMessages");

            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-conversations">
                        No messages yet. Start a conversation!
                    </div>
                `;

                return;
            }

            let html = "";

            messages.forEach((message, index) => {
                const isSent = message.type === "sent";

                html += `
                    <div
                        class="message ${isSent ? "sent" : "received"}"
                        style="animation-delay: ${index * 0.05}s"
                    >

                        ${escapeHtml(message.text)}

                        <span class="message-time">
                            ${escapeHtml(message.time)}
                        </span>

                    </div>
                `;
            });

            container.innerHTML = html;
        }

        /* ================================
           SEND MESSAGE
        ================================= */

        function sendMessage() {
            const input = document.getElementById("messageInput");
            const text = input.value.trim();

            if (!text || !currentConversationId) {
                return;
            }

            const conversation = conversations.find(
                (item) => item.id === currentConversationId
            );

            if (!conversation) {
                return;
            }

            const now = new Date();

            const time = now.toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit"
            });

            conversation.messages.push({
                text: text,
                time: time,
                type: "sent"
            });

            conversation.lastMessage = text;
            conversation.time = time;

            input.value = "";

            renderMessages(conversation.messages);
            renderConversations();
            scrollMessagesToBottom();

            setTimeout(() => {
                autoReply(conversation);
            }, 1000 + Math.random() * 2000);
        }

        /* ================================
           AUTO REPLY
        ================================= */

        function autoReply(conversation) {
            const replies = [
                "Thanks for your response! I appreciate the help.",
                "Got it! That makes sense.",
                "Perfect, thank you for clarifying!",
                "I see. Let me think about that and get back to you.",
                "Great! I will check that out.",
                "Awesome, thanks for letting me know!"
            ];

            const reply =
                replies[Math.floor(Math.random() * replies.length)];

            const now = new Date();

            const time = now.toLocaleTimeString("en-US", {
                hour: "2-digit",
                minute: "2-digit"
            });

            conversation.messages.push({
                text: reply,
                time: time,
                type: "received"
            });

            conversation.lastMessage = reply;
            conversation.time = time;

            renderMessages(conversation.messages);
            renderConversations();

            if (currentConversationId === conversation.id) {
                scrollMessagesToBottom();
            } else {
                conversation.unread += 1;
                renderConversations();
            }
        }

        /* ================================
           SCROLL MESSAGES
        ================================= */

        function scrollMessagesToBottom() {
            const messagesContainer =
                document.getElementById("chatMessages");

            messagesContainer.scrollTop =
                messagesContainer.scrollHeight;
        }

        /* ================================
           KEYBOARD HANDLER
        ================================= */

        function handleKeyPress(event) {
            if (event.key === "Enter" && !event.shiftKey) {
                event.preventDefault();
                sendMessage();
            }
        }

        /* ================================
           ATTACH FILE
        ================================= */

        function attachFile() {
            showNotification("File attachment feature coming soon!");
        }

        /* ================================
           NOTIFICATION
        ================================= */

        function showNotification(message) {
            const existing =
                document.querySelector(".sable-notification");

            if (existing) {
                existing.remove();
            }

            const notification = document.createElement("div");

            notification.className = "sable-notification";
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation =
                    "slideDown 0.3s ease";

                setTimeout(() => {
                    notification.remove();
                }, 300);

            }, 3000);
        }

        /* ================================
           INITIALIZE
        ================================= */

        document.addEventListener("DOMContentLoaded", () => {
            renderConversations();

            if (window.innerWidth > 960 && conversations.length > 0) {
                selectConversation(conversations[0].id);
            }
        });
    </script>

</body>

</html>
