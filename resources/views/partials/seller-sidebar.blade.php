<aside class="sidebar seller-sidebar" id="sidebar" aria-label="Seller navigation">
    <p class="seller-sidebar__title">Seller Center</p>

    <nav class="seller-sidebar__nav">
        @php
            $sellerNavigation = [
                ['route' => 'seller.dashboard', 'label' => 'Dashboard', 'icon' => 'fa-home'],
                ['route' => 'seller.notifications', 'label' => 'Notifications', 'icon' => 'fa-bell', 'badge' => true],
                ['route' => 'seller.orders', 'label' => 'Orders', 'icon' => 'fa-box', 'count' => 'orders'],
                ['route' => 'seller.handover', 'label' => 'Delivery', 'icon' => 'fa-truck'],
                ['route' => 'seller.returns', 'label' => 'Returns', 'icon' => 'fa-rotate-left', 'count' => 'returns'],
                ['route' => 'seller.inventory', 'label' => 'Inventory', 'icon' => 'fa-warehouse'],
                ['route' => 'seller.products', 'label' => 'Products', 'icon' => 'fa-shopping-bag'],
                ['route' => 'seller.reports', 'label' => 'Reports', 'icon' => 'fa-chart-bar'],
                ['route' => 'seller.chat', 'label' => 'Messages', 'icon' => 'fa-comment'],
                ['route' => 'seller.logistics-messages', 'label' => 'Logistics chat', 'icon' => 'fa-truck-fast'],
                ['route' => 'support', 'label' => 'Help & support', 'icon' => 'fa-life-ring'],
                ['route' => 'seller.activity', 'label' => 'Activity log', 'icon' => 'fa-clock-rotate-left'],
                ['route' => 'seller.account', 'label' => 'Account', 'icon' => 'fa-user'],
            ];
        @endphp

        @php
            // Orders to prepare and returns to handle; seller-workspace.js keeps them current.
            $sidebarCounts = [
                'orders' => [$sidebarOrdersToPrepare ?? 0, 'to prepare'],
                'returns' => [$sidebarReturnsToHandle ?? 0, 'to handle'],
                'notifications' => [$sidebarUnreadNotifications, 'unread'],
            ];
        @endphp

        @foreach($sellerNavigation as $item)
            @php
                // Sub-pages (e.g. seller.products.edit) keep their section highlighted.
                $active = request()->routeIs($item['route'], $item['route'].'.*');
            @endphp
            <a
                href="{{ route($item['route']) }}"
                class="seller-sidebar__link{{ $active ? ' is-active' : '' }}"
                @if($active) aria-current="page" @endif
            >
                <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                <span>{{ $item['label'] }}</span>
                @php $count = $item['count'] ?? (! empty($item['badge']) ? 'notifications' : null); @endphp
                @if($count)
                    @php [$total, $meaning] = $sidebarCounts[$count]; @endphp
                    <span class="seller-sidebar__badge" data-sidebar-count="{{ $count }}" data-sidebar-count-label="{{ $meaning }}" @if($total < 1) hidden @endif>@if($total > 0){{ $total > 99 ? '99+' : $total }}<span class="sr-only"> {{ $meaning }}</span>@endif</span>
                @endif
            </a>
        @endforeach

        <div class="seller-sidebar__divider" aria-hidden="true"></div>

        <button type="button" class="seller-sidebar__link" data-theme-toggle data-theme-toggle-label aria-label="Switch theme"></button>

        <form method="POST" action="{{ route('logout') }}" data-logout-confirm class="seller-sidebar__logout-form">
            @csrf
            <button type="submit" class="seller-sidebar__link seller-sidebar__logout">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                <span>Logout</span>
            </button>
        </form>
    </nav>
</aside>
