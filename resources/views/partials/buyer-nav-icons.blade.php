<a href="{{ route('buyer.notifications') }}" class="buyer-nav-icon" aria-label="Notifications ({{ $navUnreadNotificationCount }} unread)" @if(request()->routeIs('buyer.notifications*')) aria-current="page" @endif>
    <i class="far fa-bell" aria-hidden="true"></i>
    @if($navUnreadNotificationCount > 0)
        <span class="nav-item-count" data-notification-count aria-hidden="true">{{ $navUnreadNotificationCount > 99 ? '99+' : $navUnreadNotificationCount }}</span>
    @endif
</a>
<a href="{{ route('buyer.wishlist') }}" class="buyer-nav-icon" aria-label="Wishlist ({{ $navWishlistCount }} saved products)" @if(request()->routeIs('buyer.wishlist')) aria-current="page" @endif>
    <i class="far fa-heart" aria-hidden="true"></i>
    @if($navWishlistCount > 0)
        <span class="nav-item-count" data-wishlist-count aria-hidden="true">{{ $navWishlistCount }}</span>
    @endif
</a>
<a href="{{ route('buyer.cart') }}" class="buyer-nav-icon" aria-label="Shopping cart ({{ $navCartQuantity }} items)" @if(request()->routeIs('buyer.cart')) aria-current="page" @endif>
    <i class="fas fa-shopping-bag" aria-hidden="true"></i>
    @if($navCartQuantity > 0)
        <span class="nav-item-count" data-cart-count aria-hidden="true">{{ $navCartQuantity }}</span>
    @endif
</a>
<a href="{{ route('buyer.orders') }}" class="buyer-nav-icon" aria-label="Order status and tracking" @if(request()->routeIs('buyer.orders')) aria-current="page" @endif>
    <i class="fas fa-box" aria-hidden="true"></i>
</a>
<a href="{{ route('buyer.account') }}" class="buyer-nav-icon" aria-label="Profile settings" @if(request()->routeIs('buyer.account')) aria-current="page" @endif>
    <i class="far fa-user" aria-hidden="true"></i>
</a>
