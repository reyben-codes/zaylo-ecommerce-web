<aside class="sidebar seller-sidebar" id="sidebar" aria-label="Seller navigation">
    <p class="seller-sidebar__title">Seller Center</p>

    <nav class="seller-sidebar__nav">
        @php
            $sellerNavigation = [
                ['route' => 'seller.dashboard', 'label' => 'Dashboard', 'icon' => 'fa-home'],
                ['route' => 'seller.notifications', 'label' => 'Notifications', 'icon' => 'fa-bell', 'count' => 'notifications'],
                ['route' => 'seller.orders', 'label' => 'Orders', 'icon' => 'fa-box', 'count' => 'orders'],
                ['route' => 'seller.orders.history', 'label' => 'Order history', 'icon' => 'fa-clock-rotate-left'],
                ['route' => 'seller.pickups', 'label' => 'Pickups', 'icon' => 'fa-truck-ramp-box'],
                ['route' => 'seller.handover', 'label' => 'Delivery', 'icon' => 'fa-truck'],
                ['route' => 'seller.returns', 'label' => 'Returns', 'icon' => 'fa-rotate-left', 'count' => 'returns'],
                ['route' => 'seller.inventory', 'label' => 'Inventory', 'icon' => 'fa-warehouse'],
                ['route' => 'seller.products', 'label' => 'Products', 'icon' => 'fa-shopping-bag', 'count' => 'products'],
                ['route' => 'seller.vouchers', 'label' => 'Vouchers', 'icon' => 'fa-ticket', 'count' => 'vouchers'],
                ['route' => 'seller.settlement', 'label' => 'Settlement', 'icon' => 'fa-scale-balanced'],
                ['route' => 'seller.reports', 'label' => 'Reports', 'icon' => 'fa-chart-bar'],
                ['route' => 'seller.chat', 'label' => 'Messages', 'icon' => 'fa-comment', 'count' => 'chat'],
                ['route' => 'seller.logistics-messages', 'label' => 'Logistics chat', 'icon' => 'fa-truck-fast', 'count' => 'logistics_chat'],
                ['route' => 'support', 'label' => 'Help & support', 'icon' => 'fa-life-ring'],
                ['route' => 'seller.activity', 'label' => 'Activity log', 'icon' => 'fa-clock-rotate-left'],
                ['route' => 'seller.account', 'label' => 'Account', 'icon' => 'fa-user'],
            ];
        @endphp


        @foreach($sellerNavigation as $item)
            @php
                // Sub-pages (e.g. seller.products.edit) keep their section highlighted.
                $active = request()->routeIs($item['route'], $item['route'].'.*');
                if ($item['route'] === 'seller.orders' && request()->routeIs('seller.orders.history')) $active = false;
            @endphp
            <a
                href="{{ route($item['route']) }}"
                class="seller-sidebar__link{{ $active ? ' is-active' : '' }}"
                @if($active) aria-current="page" @endif
            >
                <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                <span>{{ $item['label'] }}</span>
                @include('partials.sidebar-count', ['key' => $item['count'] ?? null])
            </a>
        @endforeach

        <div class="seller-sidebar__divider" aria-hidden="true"></div>

        <button type="button" class="seller-sidebar__link" data-theme-toggle data-theme-toggle-label aria-label="Switch theme"></button>
        @include('partials.faq-bot-restore', ['variant' => 'sidebar'])

        <form method="POST" action="{{ route('logout') }}" data-logout-confirm class="seller-sidebar__logout-form">
            @csrf
            <button type="submit" class="seller-sidebar__link seller-sidebar__logout">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                <span>Logout</span>
            </button>
        </form>
    </nav>
</aside>
