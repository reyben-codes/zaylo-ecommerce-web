<?php

/*
| Answers for the help bot (FaqController, public/js/faq-bot.js, and the app's help screen). Each account type sees its
| own questions plus the "all" ones. "keywords" are words or phrases people might type; "link" is an optional route
| name with a label, shown on the website only.
*/

return [
    'all' => [
        [
            'q' => 'How do I change my password or profile photo?',
            'a' => 'Open your Account page. Profile details, your photo and your password each have their own section there.',
            'keywords' => ['password', 'profile', 'photo', 'picture', 'name', 'phone', 'email', 'account settings'],
        ],
        [
            'q' => 'How do I talk to a person at ZAYLO?',
            'a' => 'Open Help & support and send a message (you can attach a photo). The ZAYLO team answers there, usually within a day.',
            'keywords' => ['support', 'human', 'person', 'agent', 'admin', 'contact', 'help', 'talk', 'call'],
        ],
        [
            'q' => 'Why is my account pending or suspended?',
            'a' => 'New sellers, logistics partners and riders are reviewed before they can work on ZAYLO; you get an email once it is decided. A suspended account was stopped by the ZAYLO team: contact Help & support to find out why.',
            'keywords' => ['pending', 'suspended', 'approved', 'approval', 'rejected', 'blocked', 'banned', 'review'],
        ],
        [
            'q' => 'I did not get my verification code.',
            'a' => 'Check your spam or promotions folder, wait a minute, then use "Resend code". Codes expire after a short time, so always use the newest one.',
            'keywords' => ['code', 'verification', 'verify', 'otp', 'email not received', 'resend'],
        ],
    ],

    'buyer' => [
        [
            'q' => 'How do I place an order?',
            'a' => 'Add products to your cart, open the cart, pick the items to buy and check out. Choose a saved address, a courier and Cash on Delivery or GCash. Your ID must be approved first (Profile Settings → ID verification).',
            'keywords' => ['order', 'buy', 'checkout', 'purchase', 'cart', 'place order'],
            'link' => ['route' => 'buyer.cart', 'label' => 'Open my cart'],
        ],
        [
            'q' => 'Why can\'t I check out?',
            'a' => 'Usually your ID is not approved yet, or the saved address needs its Philippine locations picked again. Upload your ID in Profile Settings and edit the address if checkout asks you to.',
            'keywords' => ['cannot checkout', 'can\'t checkout', 'checkout error', 'identity', 'id', 'verify id', 'address'],
            'link' => ['route' => 'buyer.account', 'label' => 'Open Profile Settings'],
        ],
        [
            'q' => 'How much is shipping?',
            'a' => 'Shipping is ₱120 for each shop in your order. A free-shipping voucher removes it.',
            'keywords' => ['shipping', 'delivery fee', 'shipping fee', 'voucher', 'free shipping'],
        ],
        [
            'q' => 'How do I pay?',
            'a' => 'Choose Cash on Delivery (pay the rider when the parcel arrives) or GCash at checkout.',
            'keywords' => ['pay', 'payment', 'gcash', 'cod', 'cash on delivery', 'cash'],
        ],
        [
            'q' => 'Where is my order?',
            'a' => 'Open My orders to see each step, from the seller preparing it to the rider delivering it. You can chat with the rider while the parcel is out for delivery.',
            'keywords' => ['track', 'tracking', 'where', 'status', 'my order', 'parcel', 'shipped', 'late', 'delay'],
            'link' => ['route' => 'buyer.orders', 'label' => 'Open My orders'],
        ],
        [
            'q' => 'Can I cancel my order?',
            'a' => 'Yes, while it is still Placed or Confirmed: open the order in My orders and cancel it. Once the seller is preparing it, it can no longer be cancelled.',
            'keywords' => ['cancel', 'cancellation', 'change order'],
            'link' => ['route' => 'buyer.orders', 'label' => 'Open My orders'],
        ],
        [
            'q' => 'What do I do when my parcel arrives?',
            'a' => 'Check it, then press "Order received" in My orders. The rider\'s delivery photo is there too. If you do nothing, the order completes by itself a few days after delivery.',
            'keywords' => ['received', 'arrived', 'confirm receipt', 'delivered', 'delivery photo', 'proof'],
        ],
        [
            'q' => 'How do I return an item or get a refund?',
            'a' => 'In My orders, open the order and file a complaint: choose Return and Refund, Refund Only or Replacement, add photos, and pick a home pickup or a hub drop-off. Keep the return slip (barcode) for the rider or the hub. You can follow every step in the case until the seller refunds you.',
            'keywords' => ['return', 'refund', 'replace', 'exchange', 'damaged', 'wrong item', 'broken', 'defective', 'money back'],
            'link' => ['route' => 'disputes.index', 'label' => 'Open my complaints'],
        ],
        [
            'q' => 'When will I get my refund?',
            'a' => 'For a return, after the item reaches the seller and they check it; for a refund-only request, after the seller reviews it. The case shows each step, and you get an email when the refund is issued.',
            'keywords' => ['when refund', 'refund status', 'refund time', 'how long refund'],
            'link' => ['route' => 'disputes.index', 'label' => 'Open my complaints'],
        ],
        [
            'q' => 'How do I message a seller?',
            'a' => 'Open the product or the shop and press Chat. Your conversations are under Messages.',
            'keywords' => ['message seller', 'chat seller', 'ask seller', 'contact seller'],
            'link' => ['route' => 'buyer.chat', 'label' => 'Open Messages'],
        ],
    ],

    'seller' => [
        [
            'q' => 'How do I add a product?',
            'a' => 'Go to Products → Add product. Add photos, categories, sizes or variations and stock. New products are checked by ZAYLO before buyers see them.',
            'keywords' => ['add product', 'new product', 'list', 'listing', 'upload product', 'create product'],
            'link' => ['route' => 'seller.products.create', 'label' => 'Add a product'],
        ],
        [
            'q' => 'How do I ship an order?',
            'a' => 'In Orders: confirm the order, start processing, then press Ready to ship and choose your logistics partner. Print the waybill and attach it. After the partner scans it at pickup, press "Confirm handed over".',
            'keywords' => ['ship', 'shipping', 'ready to ship', 'waybill', 'pickup', 'courier', 'handover', 'fulfil', 'fulfill'],
            'link' => ['route' => 'seller.orders', 'label' => 'Open Orders'],
        ],
        [
            'q' => 'Where is the proof of delivery?',
            'a' => 'Riders take a photo when they hand over a parcel. Open the order in Orders or Delivery and press "Delivery photo".',
            'keywords' => ['proof', 'delivery photo', 'delivered', 'photo'],
            'link' => ['route' => 'seller.handover', 'label' => 'Open Delivery'],
        ],
        [
            'q' => 'How do returns and refunds work?',
            'a' => 'The logistics partner brings the returned item back to you (a rider collects it from the buyer, or the buyer drops it at a hub). Follow it on the Returns page, press "Return received" when it arrives, check it, then record the refund.',
            'keywords' => ['return', 'refund', 'returned item', 'complaint', 'exchange', 'replacement'],
            'link' => ['route' => 'seller.returns', 'label' => 'Open Returns'],
        ],
        [
            'q' => 'How do I run a sale or flash sale?',
            'a' => 'Edit a product and open "Manage sale". A regular sale runs between the dates you set; a flash sale lasts up to 24 hours on a limited number of units and shows in Flash Sales with a countdown.',
            'keywords' => ['sale', 'flash sale', 'discount', 'promo', 'promotion', 'price off'],
            'link' => ['route' => 'seller.products', 'label' => 'Open Products'],
        ],
        [
            'q' => 'How do I update stock?',
            'a' => 'Use Inventory to restock quickly, or edit the product to change the stock of each size or variation.',
            'keywords' => ['stock', 'inventory', 'restock', 'quantity', 'sold out', 'out of stock'],
            'link' => ['route' => 'seller.inventory', 'label' => 'Open Inventory'],
        ],
        [
            'q' => 'How much commission does ZAYLO take?',
            'a' => 'ZAYLO keeps a 10% commission on each delivered order. Reports shows your sales, the commission and what you earn.',
            'keywords' => ['commission', 'fee', 'earnings', 'income', 'net', 'payout', 'sales report'],
            'link' => ['route' => 'seller.reports', 'label' => 'Open Reports'],
        ],
        [
            'q' => 'Why is my product hidden or rejected?',
            'a' => 'ZAYLO reviews products and can hide one that breaks the rules. The reason is on the product and in your notifications; fix it and save to send it for review again.',
            'keywords' => ['hidden', 'rejected', 'review', 'warning', 'compliance', 'not showing'],
            'link' => ['route' => 'seller.notifications', 'label' => 'Open Notifications'],
        ],
    ],

    'rider' => [
        [
            'q' => 'How do I get deliveries?',
            'a' => 'Your logistics partner assigns parcels to you at the hub. Accept or decline each new assignment (with a reason) on your dashboard.',
            'keywords' => ['assignment', 'get deliveries', 'new delivery', 'accept', 'decline', 'job'],
        ],
        [
            'q' => 'How do I start and finish a delivery?',
            'a' => 'Scan the waybill to go out for delivery. At the buyer\'s door, scan it again, confirm the hand-over (and the cash for COD), then take a photo of the parcel as proof of delivery.',
            'keywords' => ['scan', 'deliver', 'start', 'finish', 'waybill', 'qr', 'barcode', 'proof', 'photo'],
        ],
        [
            'q' => 'What if I cannot deliver?',
            'a' => 'Choose "Could not deliver" and pick the reason. The buyer and seller are told, and your logistics partner arranges another try or a return.',
            'keywords' => ['failed', 'cannot deliver', 'not home', 'refused', 'unreachable', 'no answer'],
        ],
        [
            'q' => 'How do I collect a returned item?',
            'a' => 'Returns assigned to you are on your dashboard. At the buyer\'s door, scan their return slip and take a photo of the item, then bring it to your logistics hub.',
            'keywords' => ['return', 'collect', 'return slip', 'pickup return', 'ret-'],
        ],
        [
            'q' => 'How are my earnings counted?',
            'a' => 'Earnings show the delivery fee of each parcel you delivered. Your logistics partner sets how riders are paid.',
            'keywords' => ['earnings', 'pay', 'salary', 'fee', 'income', 'money'],
        ],
    ],

    'logistics' => [
        [
            'q' => 'How do I handle a pickup request?',
            'a' => 'In Pickups, confirm the request with a pickup date (or decline it with a reason). When you collect the parcel, scan its waybill to mark it picked up.',
            'keywords' => ['pickup', 'pick up', 'request', 'confirm pickup', 'seller'],
            'link' => ['route' => 'logistics.pickups', 'label' => 'Open Pickups'],
        ],
        [
            'q' => 'How do I sort parcels and assign riders?',
            'a' => 'Receive parcels at the hub in Incoming, sort them by area, then give them to riders in Assign riders, one by one or a whole area at once.',
            'keywords' => ['sort', 'sorting', 'assign', 'rider', 'area', 'incoming', 'hub'],
            'link' => ['route' => 'logistics.assignments', 'label' => 'Open Assign riders'],
        ],
        [
            'q' => 'Where are the delivery photos?',
            'a' => 'Open Deliveries: delivered parcels show the rider\'s delivery photo. Click it to see it full size.',
            'keywords' => ['proof', 'delivery photo', 'photo', 'delivered'],
            'link' => ['route' => 'logistics.deliveries', 'label' => 'Open Deliveries'],
        ],
        [
            'q' => 'How do I handle a returned item?',
            'a' => 'In Returns, give each home pickup to a rider, who scans the slip and photographs the item. When it reaches the hub, press "Received at the hub" and take a photo, then send it back and hand it to the seller. Each step and photo is tracked.',
            'keywords' => ['return', 'returned', 'ret-', 'refund', 'return photo', 'proof'],
            'link' => ['route' => 'logistics.returns', 'label' => 'Open Returns'],
        ],
        [
            'q' => 'How do I add or approve riders?',
            'a' => 'Riders apply in the ZAYLO app and choose your company. Review their documents in Riders, then approve or reject them.',
            'keywords' => ['rider application', 'approve rider', 'new rider', 'riders', 'documents', 'license'],
            'link' => ['route' => 'logistics.riders', 'label' => 'Open Riders'],
        ],
    ],

    'admin' => [
        [
            'q' => 'How do I approve new accounts?',
            'a' => 'Approvals lists sellers, logistics partners and riders waiting for review. Open one to check the documents, then approve or reject with a reason; they are notified.',
            'keywords' => ['approve', 'approval', 'registration', 'pending', 'reject', 'seller application'],
            'link' => ['route' => 'admin.registrations', 'label' => 'Open Approvals'],
        ],
        [
            'q' => 'How do I review buyer IDs?',
            'a' => 'ID checks shows uploaded IDs. Approve one so the buyer can order, or reject it with a reason.',
            'keywords' => ['id', 'identity', 'verification', 'id check'],
            'link' => ['route' => 'admin.identity', 'label' => 'Open ID checks'],
        ],
        [
            'q' => 'How do I handle a dispute?',
            'a' => 'Disputes lists every complaint and return. Open a case to read the evidence, reply to the buyer, seller and rider, and mark it resolved or rejected.',
            'keywords' => ['dispute', 'complaint', 'case', 'return', 'refund'],
            'link' => ['route' => 'admin.disputes', 'label' => 'Open Disputes'],
        ],
        [
            'q' => 'How do I download a sales report?',
            'a' => 'In Reports, use "Download report": choose all sellers or one seller and the dates.',
            'keywords' => ['report', 'download', 'sales', 'commission', 'pdf', 'export'],
            'link' => ['route' => 'admin.reports', 'label' => 'Open Reports'],
        ],
        [
            'q' => 'How do I post an announcement?',
            'a' => 'In Settings → Announcements, write it and choose who sees it (everyone, buyers, sellers, riders or logistics).',
            'keywords' => ['announcement', 'announce', 'banner', 'notice', 'broadcast'],
            'link' => ['route' => 'admin.settings', 'label' => 'Open Settings'],
        ],
    ],
];
