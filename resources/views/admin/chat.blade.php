<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>ZAYLO · Admin Chat </title>

    <!-- Google Fonts -->
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
            background-color: #faf7f2;
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

        /* ========================================
           CENTER LOGO
           ======================================== */

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

        /* ========================================
           HEADER SEARCH
           ======================================== */

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

            box-shadow:
                0 2px 8px rgba(0, 0, 0, 0.04);
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

            transition:
                background 0.15s ease,
                color 0.15s ease;

            border-radius: 0;
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

        .sidebar-menu a .badge {
            margin-left: auto;

            background: #b28b6f;

            color: #ffffff;

            font-size: 0.5rem;

            padding: 2px 8px;

            border-radius: 10px;

            font-weight: 500;
        }

        .sidebar-divider {
            height: 1px;

            background: #ece4db;

            margin: 12px 16px;
        }

        /* ========================================
           CHAT AREA
           ======================================== */

        .chat-layout {
            flex: 1;

            display: grid;

            grid-template-columns: 320px 1fr;

            gap: 0;

            background: #ffffff;

            border: 1px solid #ece4db;

            min-height: 500px;

            height: calc(100vh - 96px);

            max-height: 700px;

            margin: 16px;
        }

        /* ========================================
           CONVERSATIONS SIDEBAR
           ======================================== */

        .chat-sidebar {
            border-right: 1px solid #ece4db;

            overflow-y: auto;

            background: #faf7f2;
        }

        .chat-sidebar-header {
            padding: 16px 20px;

            background: #ffffff;

            border-bottom: 1px solid #ece4db;

            position: sticky;

            top: 0;

            z-index: 2;
        }

        .chat-sidebar-header h3 {
            font-size: 0.85rem;

            font-weight: 600;

            color: #1a1714;
        }

        .chat-sidebar-header .search-chat {
            width: 100%;

            padding: 8px 12px;

            border: 1px solid #ece4db;

            font-size: 0.7rem;

            font-family: 'Inter', sans-serif;

            outline: none;

            margin-top: 8px;

            background: #ffffff;

            color: #1e1e1e;
        }

        .chat-sidebar-header .search-chat:focus {
            border-color: #1a1714;
        }

        /* ========================================
           CONVERSATION ITEM
           ======================================== */

        .conversation-item {
            display: flex;

            align-items: center;

            gap: 12px;

            padding: 14px 20px;

            cursor: pointer;

            transition: background 0.15s ease;

            border-bottom: 1px solid #f5f0ea;
        }

        .conversation-item:hover {
            background: #f5f0ea;
        }

        .conversation-item.active {
            background: #ece4db;
        }

        .conversation-item .avatar {
            width: 44px;
            height: 44px;

            border-radius: 50%;

            background: #d6ccc1;

            flex-shrink: 0;

            display: flex;

            align-items: center;
            justify-content: center;

            font-weight: 600;

            font-size: 0.9rem;

            color: #1a1714;

            overflow: hidden;
        }

        .conversation-item .avatar img {
            width: 100%;
            height: 100%;

            object-fit: cover;
        }

        .conversation-info {
            flex: 1;

            min-width: 0;
        }

        .conversation-info .name {
            font-weight: 500;

            font-size: 0.8rem;

            color: #1a1714;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }

        .conversation-info .last-message {
            font-size: 0.7rem;

            color: #6b5f54;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;
        }

        .conversation-meta {
            text-align: right;

            flex-shrink: 0;
        }

        .conversation-meta .time {
            font-size: 0.6rem;

            color: #6b5f54;
        }

        .conversation-meta .unread {
            background: #b28b6f;

            color: #ffffff;

            font-size: 0.55rem;

            font-weight: 600;

            padding: 2px 7px;

            border-radius: 10px;

            margin-top: 4px;

            display: inline-block;

            min-width: 18px;
        }

        /* ========================================
           CHAT MAIN
           ======================================== */

        .chat-main {
            display: flex;

            flex-direction: column;

            background: #ffffff;

            min-width: 0;
        }

        /* ========================================
           CHAT HEADER
           ======================================== */

        .chat-main-header {
            padding: 16px 24px;

            border-bottom: 1px solid #ece4db;

            display: flex;

            align-items: center;

            gap: 12px;

            background: #ffffff;
        }

        .chat-main-header .avatar {
            width: 36px;
            height: 36px;

            border-radius: 50%;

            background: #d6ccc1;

            display: flex;

            align-items: center;
            justify-content: center;

            font-weight: 600;

            font-size: 0.8rem;

            color: #1a1714;

            overflow: hidden;
        }

        .chat-main-header .avatar img {
            width: 100%;
            height: 100%;

            object-fit: cover;
        }

        .chat-name {
            font-weight: 500;

            font-size: 0.85rem;

            color: #1a1714;
        }

        .chat-status {
            font-size: 0.65rem;

            color: #2d7d46;
        }

        .chat-status.offline {
            color: #6b5f54;
        }

        .chat-actions {
            margin-left: auto;

            display: flex;

            gap: 12px;
        }

        .chat-actions button {
            background: none;

            border: none;

            color: #6b5f54;

            cursor: pointer;

            transition: color 0.15s ease;

            font-size: 0.9rem;
        }

        .chat-actions button:hover {
            color: #1a1714;
        }

        /* ========================================
           MESSAGES
           ======================================== */

        .chat-messages {
            flex: 1;

            padding: 20px 24px;

            overflow-y: auto;

            display: flex;

            flex-direction: column;

            gap: 8px;

            min-height: 300px;
        }

        .message {
            max-width: 75%;

            padding: 10px 16px;

            font-size: 0.8rem;

            line-height: 1.5;

            animation: messageIn 0.2s ease forwards;

            opacity: 0;
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

            background: #1a1714;

            color: #ffffff;

            border-radius: 12px 12px 4px 12px;
        }

        .message.received {
            align-self: flex-start;

            background: #f5f0ea;

            color: #1a1714;

            border-radius: 12px 12px 12px 4px;
        }

        .message-time {
            font-size: 0.55rem;

            opacity: 0.6;

            margin-top: 4px;

            display: block;
        }

        .message.sent .message-time {
            text-align: right;
        }

        /* ========================================
           CHAT INPUT
           ======================================== */

        .chat-input {
            padding: 16px 24px;

            border-top: 1px solid #ece4db;

            display: flex;

            gap: 12px;

            background: #ffffff;
        }

        .chat-input input {
            flex: 1;

            padding: 10px 16px;

            border: 1px solid #ece4db;

            font-size: 0.8rem;

            font-family: 'Inter', sans-serif;

            outline: none;

            transition:
                border-color 0.15s ease,
                background 0.15s ease;

            background: #faf7f2;

            color: #1e1e1e;
        }

        .chat-input input:focus {
            border-color: #1a1714;

            background: #ffffff;
        }

        .btn-send {
            padding: 10px 24px;

            background: #1a1714;

            color: #ffffff;

            border: none;

            font-size: 0.7rem;

            font-weight: 500;

            text-transform: uppercase;

            letter-spacing: 0.04em;

            cursor: pointer;

            transition: background 0.15s ease;

            font-family: 'Inter', sans-serif;

            white-space: nowrap;
        }

        .btn-send:hover {
            background: #b28b6f;
        }

        .btn-attach {
            padding: 10px 14px;

            background: transparent;

            border: 1px solid #ece4db;

            font-size: 0.9rem;

            cursor: pointer;

            transition:
                border-color 0.15s ease,
                color 0.15s ease;

            color: #6b5f54;
        }

        .btn-attach:hover {
            border-color: #1a1714;

            color: #1a1714;
        }

        /* ========================================
           NO CONVERSATION
           ======================================== */

        .no-conversation {
            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            height: 100%;

            color: #6b5f54;

            padding: 40px;

            text-align: center;
        }

        .no-conversation i {
            font-size: 3rem;

            color: #b28b6f;

            margin-bottom: 16px;
        }

        .no-conversation h3 {
            font-family: 'Playfair Display', serif;

            font-size: 1.3rem;

            color: #1a1714;

            margin-bottom: 4px;
        }

        .no-conversation p {
            font-size: 0.75rem;

            color: #6b5f54;
        }

        /* ========================================
           NOTIFICATION
           ======================================== */

        .zaylo-notification {
            position: fixed;

            bottom: 24px;

            right: 24px;

            background: #1a1714;

            color: #ffffff;

            padding: 14px 24px;

            font-family: 'Inter', sans-serif;

            font-size: 0.8rem;

            z-index: 9999;

            border: none;

            box-shadow: 0 4px 16px rgba(0,0,0,0.1);

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

            .chat-layout {
                grid-template-columns: 280px 1fr;
            }

        }

        @media (max-width: 900px) {

            .sidebar {
                width: 220px;
            }

            .chat-layout {
                margin: 12px;

                grid-template-columns: 260px 1fr;
            }

        }

        @media (max-width: 820px) {

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

            .chat-layout {
                grid-template-columns: 1fr;

                height: auto;

                max-height: none;

                margin: 12px;
            }

            .chat-sidebar {
                max-height: 300px;

                border-right: none;

                border-bottom: 1px solid #ece4db;
            }

            .chat-messages {
                min-height: 250px;

                max-height: 400px;
            }

        }

        @media (max-width: 600px) {

            .navbar {
                height: 62px;

                padding: 0 16px;
            }

            .logo img {
                width: 70px;
            }

            .search-wrapper {
                right: 16px;

                width: 240px;

                height: 38px;
            }

            .search-wrapper input {
                font-size: 0.68rem;
            }

            .chat-layout {
                margin: 8px;
            }

            .chat-main-header {
                padding: 12px 16px;
            }

            .chat-messages {
                padding: 12px 16px;

                min-height: 200px;

                max-height: 320px;
            }

            .message {
                max-width: 88%;

                font-size: 0.75rem;

                padding: 8px 12px;
            }

            .chat-input {
                padding: 12px 16px;

                flex-wrap: wrap;
            }

            .chat-input input {
                order: 1;

                width: 100%;
            }

            .btn-attach {
                order: 2;
            }

            .btn-send {
                order: 3;

                flex: 1;
            }

        }

        @media (max-width: 450px) {

            .search-wrapper {
                width: 42px;

                padding: 0 12px;

                overflow: hidden;
            }

            .search-wrapper input {
                display: none;
            }

            .search-icon {
                margin-right: 0;
            }

            .logo img {
                width: 66px;
            }

            .chat-sidebar {
                max-height: 250px;
            }

            .chat-messages {
                max-height: 250px;
            }

            .chat-actions {
                gap: 8px;
            }

            .chat-actions button {
                font-size: 0.8rem;
            }

        }

    </style>
<link rel="stylesheet" href="{{ asset('css/admin-shell.css') }}">
</head>

<body>

<div class="container">

    <!-- ========================================
         HEADER
         ONLY LOGO + SEARCH BAR
         ======================================== -->

    @include('admin.partials.header', ['searchId' => 'adminSearch', 'searchPlaceholder' => 'Search admin pages...', 'searchOnInput' => ''])


    <!-- ========================================
         DASHBOARD WRAPPER
         ======================================== -->

    <div class="dashboard-wrapper">

        <!-- ========================================
             SIDEBAR
             ======================================== -->

        @include('admin.partials.sidebar')


        <!-- ========================================
             CHAT LAYOUT
             ======================================== -->

        <div class="chat-layout">

            <!-- ========================================
                 CONVERSATIONS
                 ======================================== -->

            <div
                class="chat-sidebar"
                id="chatSidebar"
            >

                <div class="chat-sidebar-header">

                    <h3>
                        Conversations
                    </h3>

                    <input
                        type="text"
                        class="search-chat"
                        placeholder="Search conversations..."
                        id="searchConversations"
                        oninput="filterConversations(this.value)"
                    >

                </div>


                <div id="conversationList">
                    <!-- Conversations loaded by JavaScript -->
                </div>

            </div>


            <!-- ========================================
                 CHAT MAIN
                 ======================================== -->

            <div
                class="chat-main"
                id="chatMain"
            >

                <!-- No conversation selected -->

                <div
                    class="no-conversation"
                    id="noConversation"
                >

                    <i class="fas fa-comment-dots"></i>

                    <h3>
                        Select a conversation
                    </h3>

                    <p>
                        Choose a conversation from the list to start messaging
                    </p>

                </div>


                <!-- Chat Header -->

                <div
                    class="chat-main-header"
                    id="chatHeader"
                    style="display:none;"
                >

                    <div
                        class="avatar"
                        id="chatAvatar"
                    >
                        B
                    </div>


                    <div>

                        <div
                            class="chat-name"
                            id="chatName"
                        >
                            Buyer Name
                        </div>


                        <div
                            class="chat-status"
                            id="chatStatus"
                        >
                            Online
                        </div>

                    </div>


                    <div class="chat-actions">

                        <button
                            type="button"
                            onclick="showNotification('Call feature coming soon!')"
                            title="Call"
                        >
                            <i class="fas fa-phone"></i>
                        </button>


                        <button
                            type="button"
                            onclick="showNotification('Video call feature coming soon!')"
                            title="Video Call"
                        >
                            <i class="fas fa-video"></i>
                        </button>

                    </div>

                </div>


                <!-- Chat Messages -->

                <div
                    class="chat-messages"
                    id="chatMessages"
                    style="display:none;"
                >
                </div>


                <!-- Chat Input -->

                <div
                    class="chat-input"
                    id="chatInput"
                    style="display:none;"
                >

                    <button
                        type="button"
                        class="btn-attach"
                        onclick="attachFile()"
                        title="Attach file"
                    >
                        <i class="fas fa-paperclip"></i>
                    </button>


                    <input
                        type="text"
                        placeholder="Type a message..."
                        id="messageInput"
                        onkeypress="handleKeyPress(event)"
                        autocomplete="off"
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

    /* ========================================
       CHAT DATA
       ======================================== */

    const conversations = [

        {
            id: 1,

            name: 'Juan Dela Cruz',

            avatar: 'JD',

            lastMessage:
                'When will my order be delivered?',

            time: '2:30 PM',

            unread: 2,

            online: true,

            messages: [

                {
                    text:
                        'Hello! I have a question about my order.',

                    time: '2:15 PM',

                    type: 'received'
                },

                {
                    text:
                        'Hi Juan! What can I help you with?',

                    time: '2:18 PM',

                    type: 'sent'
                },

                {
                    text:
                        'When will my order be delivered?',

                    time: '2:20 PM',

                    type: 'received'
                },

                {
                    text:
                        "Your order is being prepared for shipping. You'll get a tracking number soon.",

                    time: '2:25 PM',

                    type: 'sent'
                },

                {
                    text:
                        'Thanks for the update!',

                    time: '2:28 PM',

                    type: 'received'
                },

                {
                    text:
                        "You're welcome! Let me know if you need anything else.",

                    time: '2:30 PM',

                    type: 'sent'
                }

            ]
        },


        {
            id: 2,

            name: 'Maria Reyes',

            avatar: 'MR',

            lastMessage:
                'Can I return this item?',

            time: '11:45 AM',

            unread: 0,

            online: false,

            messages: [

                {
                    text:
                        "I received the bag today. It's beautiful!",

                    time: '11:30 AM',

                    type: 'received'
                },

                {
                    text:
                        "I'm so glad you like it!",

                    time: '11:35 AM',

                    type: 'sent'
                },

                {
                    text:
                        'Actually, I think I need a different color. Can I return this item?',

                    time: '11:40 AM',

                    type: 'received'
                },

                {
                    text:
                        'Of course! You can initiate a return from your orders page.',

                    time: '11:45 AM',

                    type: 'sent'
                }

            ]
        },


        {
            id: 3,

            name: 'Pedro Santos',

            avatar: 'PS',

            lastMessage:
                'Do you have these in size 10?',

            time: 'Yesterday',

            unread: 1,

            online: true,

            messages: [

                {
                    text:
                        'I love the sneakers! Do you have these in size 10?',

                    time: 'Yesterday 3:00 PM',

                    type: 'received'
                },

                {
                    text:
                        'Let me check our inventory for you.',

                    time: 'Yesterday 3:05 PM',

                    type: 'sent'
                },

                {
                    text:
                        'We have size 10 in stock! Would you like to place an order?',

                    time: 'Yesterday 3:10 PM',

                    type: 'sent'
                },

                {
                    text:
                        'Do you have these in size 10?',

                    time: 'Yesterday 3:15 PM',

                    type: 'received'
                }

            ]
        },


        {
            id: 4,

            name: 'Ana Lopez',

            avatar: 'AL',

            lastMessage:
                'Thank you for the fast shipping!',

            time: 'Dec 14',

            unread: 0,

            online: false,

            messages: [

                {
                    text:
                        'I received my order today!',

                    time: 'Dec 14 9:00 AM',

                    type: 'received'
                },

                {
                    text:
                        'Thank you for the fast shipping!',

                    time: 'Dec 14 9:30 AM',

                    type: 'received'
                },

                {
                    text:
                        "You're very welcome! Enjoy your purchase!",

                    time: 'Dec 14 9:45 AM',

                    type: 'sent'
                }

            ]
        }

    ];


    let currentConversationId = null;

    let filteredConversations =
        [...conversations];


    /* ========================================
       RENDER CONVERSATIONS
       ======================================== */

    function renderConversations() {

        const list =
            document.getElementById(
                'conversationList'
            );


        if (
            filteredConversations.length === 0
        ) {

            list.innerHTML = `

                <div
                    style="
                        padding:40px 20px;
                        text-align:center;
                        color:#6b5f54;
                        font-size:0.8rem;
                    "
                >

                    <i
                        class="fas fa-search"
                        style="
                            font-size:1.5rem;
                            display:block;
                            margin-bottom:8px;
                            color:#b28b6f;
                        "
                    ></i>

                    No conversations found

                </div>

            `;

            return;
        }


        let html = '';


        filteredConversations.forEach(
            function(conv) {

                const isActive =
                    conv.id === currentConversationId;


                const unreadBadge =
                    conv.unread > 0

                        ? `
                            <span class="unread">
                                ${conv.unread}
                            </span>
                          `

                        : '';


                const onlineStatus =
                    conv.online
                        ? '🟢'
                        : '⚪';


                html += `

                    <div
                        class="
                            conversation-item
                            ${isActive ? 'active' : ''}
                        "
                        onclick="selectConversation(${conv.id})"
                    >

                        <div class="avatar">
                            ${conv.avatar}
                        </div>


                        <div class="conversation-info">

                            <div class="name">
                                ${conv.name}
                                ${onlineStatus}
                            </div>


                            <div class="last-message">
                                ${conv.lastMessage}
                            </div>

                        </div>


                        <div class="conversation-meta">

                            <div class="time">
                                ${conv.time}
                            </div>

                            ${unreadBadge}

                        </div>

                    </div>

                `;

            }
        );


        list.innerHTML = html;

    }


    /* ========================================
       FILTER CONVERSATIONS
       ======================================== */

    function filterConversations(query) {

        const search =
            query.toLowerCase().trim();


        if (!search) {

            filteredConversations =
                [...conversations];

        } else {

            filteredConversations =
                conversations.filter(
                    function(conv) {

                        return (
                            conv.name
                                .toLowerCase()
                                .includes(search)

                            ||

                            conv.lastMessage
                                .toLowerCase()
                                .includes(search)
                        );

                    }
                );

        }


        renderConversations();

    }


    /* ========================================
       SELECT CONVERSATION
       ======================================== */

    function selectConversation(id) {

        currentConversationId = id;


        const conversation =
            conversations.find(
                function(c) {
                    return c.id === id;
                }
            );


        if (!conversation) {
            return;
        }


        /* Mark as read */

        conversation.unread = 0;


        /* Update conversation list */

        renderConversations();


        /* Show chat */

        document
            .getElementById('noConversation')
            .style.display = 'none';


        document
            .getElementById('chatHeader')
            .style.display = 'flex';


        document
            .getElementById('chatMessages')
            .style.display = 'flex';


        document
            .getElementById('chatInput')
            .style.display = 'flex';


        /* Update chat header */

        document
            .getElementById('chatAvatar')
            .textContent =
                conversation.avatar;


        document
            .getElementById('chatName')
            .textContent =
                conversation.name;


        const statusElement =
            document.getElementById(
                'chatStatus'
            );


        statusElement.textContent =
            conversation.online
                ? 'Online'
                : 'Offline';


        statusElement.className =
            conversation.online
                ? 'chat-status'
                : 'chat-status offline';


        /* Render messages */

        renderMessages(
            conversation.messages
        );


        /* Scroll to bottom */

        const messagesContainer =
            document.getElementById(
                'chatMessages'
            );


        messagesContainer.scrollTop =
            messagesContainer.scrollHeight;


        /* Focus input */

        document
            .getElementById('messageInput')
            .focus();

    }


    /* ========================================
       RENDER MESSAGES
       ======================================== */

    function renderMessages(messages) {

        const container =
            document.getElementById(
                'chatMessages'
            );


        if (messages.length === 0) {

            container.innerHTML = `

                <div
                    style="
                        text-align:center;
                        color:#6b5f54;
                        font-size:0.8rem;
                        padding:40px 0;
                    "
                >
                    No messages yet.
                    Start a conversation!
                </div>

            `;

            return;
        }


        let html = '';


        messages.forEach(
            function(msg, index) {

                const isSent =
                    msg.type === 'sent';


                html += `

                    <div
                        class="
                            message
                            ${isSent ? 'sent' : 'received'}
                        "
                        style="
                            animation-delay:${index * 0.05}s;
                        "
                    >

                        ${msg.text}

                        <span class="message-time">
                            ${msg.time}
                        </span>

                    </div>

                `;

            }
        );


        container.innerHTML = html;

    }


    /* ========================================
       SEND MESSAGE
       ======================================== */

    function sendMessage() {

        const input =
            document.getElementById(
                'messageInput'
            );


        const text =
            input.value.trim();


        if (
            !text ||
            !currentConversationId
        ) {
            return;
        }


        const conversation =
            conversations.find(
                function(c) {
                    return c.id ===
                        currentConversationId;
                }
            );


        if (!conversation) {
            return;
        }


        const now =
            new Date();


        const time =
            now.toLocaleTimeString(
                'en-US',
                {
                    hour: '2-digit',
                    minute: '2-digit'
                }
            );


        /* Add message */

        conversation.messages.push({

            text: text,

            time: time,

            type: 'sent'

        });


        /* Update conversation */

        conversation.lastMessage =
            text;

        conversation.time =
            time;


        /* Update interface */

        renderMessages(
            conversation.messages
        );


        renderConversations();


        /* Scroll */

        const messagesContainer =
            document.getElementById(
                'chatMessages'
            );


        messagesContainer.scrollTop =
            messagesContainer.scrollHeight;


        /* Clear input */

        input.value = '';


        /* Simulated buyer reply */

        setTimeout(
            function() {

                autoReply(
                    conversation
                );

            },
            1000 +
            Math.random() * 2000
        );

    }


    /* ========================================
       AUTO REPLY
       ======================================== */

    function autoReply(conversation) {

        const replies = [

            'Thanks for your response! I appreciate the help.',

            'Got it! That makes sense.',

            'Perfect, thank you for clarifying!',

            'I see. Let me think about that and get back to you.',

            "Great! I'll check that out.",

            'Awesome, thanks for letting me know!'

        ];


        const reply =
            replies[
                Math.floor(
                    Math.random() *
                    replies.length
                )
            ];


        const now =
            new Date();


        const time =
            now.toLocaleTimeString(
                'en-US',
                {
                    hour: '2-digit',
                    minute: '2-digit'
                }
            );


        conversation.messages.push({

            text: reply,

            time: time,

            type: 'received'

        });


        conversation.lastMessage =
            reply;


        conversation.time =
            time;


        renderMessages(
            conversation.messages
        );


        renderConversations();


        const messagesContainer =
            document.getElementById(
                'chatMessages'
            );


        messagesContainer.scrollTop =
            messagesContainer.scrollHeight;

    }


    /* ========================================
       ENTER KEY
       ======================================== */

    function handleKeyPress(event) {

        if (
            event.key === 'Enter' &&
            !event.shiftKey
        ) {

            event.preventDefault();

            sendMessage();

        }

    }


    /* ========================================
       ATTACH FILE
       ======================================== */

    function attachFile() {

        showNotification(
            'File attachment feature coming soon!'
        );

    }


    /* ========================================
       NOTIFICATION
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
            document.createElement(
                'div'
            );


        notification.className =
            'zaylo-notification';


        notification.textContent =
            message;


        document.body.appendChild(
            notification
        );


        setTimeout(
            function() {

                notification.style.animation =
                    'slideDown 0.3s ease';


                setTimeout(
                    function() {

                        notification.remove();

                    },
                    300
                );

            },
            3000
        );

    }


    /* ========================================
       HEADER SEARCH
       ======================================== */

    const adminSearch =
        document.getElementById(
            'adminSearch'
        );


    if (adminSearch) {

        adminSearch.addEventListener(
            'keydown',
            function(event) {

                if (
                    event.key !== 'Enter'
                ) {
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
       INITIALIZE
       ======================================== */

    renderConversations();


    /* Automatically open first
       conversation on desktop */

    if (
        window.innerWidth > 820 &&
        conversations.length > 0
    ) {

        selectConversation(
            conversations[0].id
        );

    }

</script>

<script src="{{ asset('js/admin-shell.js') }}"></script>
</body>
</html>
