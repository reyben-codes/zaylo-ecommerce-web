<aside class="sidebar seller-sidebar" id="sidebar" aria-label="Seller navigation">
    <p class="seller-sidebar__title">Seller Center</p>

    <nav class="seller-sidebar__nav">
        @php
            $sellerNavigation = [
                ['route' => 'seller.dashboard', 'label' => 'Dashboard', 'icon' => 'fa-home'],
                ['route' => 'seller.orders', 'label' => 'Orders', 'icon' => 'fa-box'],
                ['route' => 'seller.handover', 'label' => 'Handover', 'icon' => 'fa-truck'],
                ['route' => 'seller.inventory', 'label' => 'Inventory', 'icon' => 'fa-warehouse'],
                ['route' => 'seller.products', 'label' => 'Products', 'icon' => 'fa-shopping-bag'],
                ['route' => 'seller.reports', 'label' => 'Reports', 'icon' => 'fa-chart-bar'],
                ['route' => 'seller.chat', 'label' => 'Messages', 'icon' => 'fa-comment'],
                ['route' => 'seller.account', 'label' => 'Account', 'icon' => 'fa-user'],
            ];
        @endphp

        @foreach($sellerNavigation as $item)
            <a
                href="{{ route($item['route']) }}"
                class="seller-sidebar__link{{ request()->routeIs($item['route']) ? ' is-active' : '' }}"
                @if(request()->routeIs($item['route'])) aria-current="page" @endif
            >
                <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach

        <div class="seller-sidebar__divider" aria-hidden="true"></div>

        <form method="POST" action="{{ route('logout') }}" class="seller-sidebar__logout-form">
            @csrf
            <button type="submit" class="seller-sidebar__link seller-sidebar__logout">
                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                <span>Logout</span>
            </button>
        </form>
    </nav>
</aside>
