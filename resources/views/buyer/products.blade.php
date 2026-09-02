@extends('layouts.app')

@section('title', 'ZAYLO · Products')

@section('nav-links')
<div class="nav-links">
    <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'active-link' : '' }}">Home</a>
    <a href="{{ route('buyer.products') }}" class="{{ request()->routeIs('buyer.products') ? 'active-link' : '' }}">Clothing</a>
    <a href="{{ route('buyer.products') }}" class="{{ request()->routeIs('buyer.products') ? 'active-link' : '' }}">Bags</a>
    <a href="{{ route('buyer.products') }}" class="{{ request()->routeIs('buyer.products') ? 'active-link' : '' }}">Shoes</a>
    <a href="{{ route('buyer.products') }}" class="{{ request()->routeIs('buyer.products') ? 'active-link' : '' }}">Accessories</a>
</div>
@endsection
@section('nav-icons')
<a href="{{ route('buyer.wishlist') }}" style="position:relative;"><i class="far fa-heart"></i></a>
<a href="{{ route('buyer.cart') }}" style="position:relative;"><i class="fas fa-shopping-bag"></i></a>
<a href="{{ route('buyer.account') }}"><i class="far fa-user"></i></a>
@endsection


@section('content')
<div class="page-hero">
    <div class="page-hero-inner">
        <i class="fas fa-tags"></i>
        <div>
            <h1>{{ $category ? ucfirst($category) : 'All Products' }}</h1>
            <p>Browse our curated collection of luxury fashion.</p>
        </div>
    </div>
</div>
<div class="page-content">
    <!-- Filters -->
    <form method="GET" action="{{ route('buyer.products') }}" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;align-items:center;">
        <select name="category" onchange="this.form.submit()" style="padding:10px 16px;border:1px solid #ece4db;background:white;font-family:Inter,sans-serif;font-size:0.8rem;color:#1a1714;cursor:pointer;outline:none;">
            <option value="">All Categories</option>
            @foreach(['clothing','bags','shoes','watches','accessories'] as $cat)
                <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
            @endforeach
        </select>
        <select name="gender" onchange="this.form.submit()" style="padding:10px 16px;border:1px solid #ece4db;background:white;font-family:Inter,sans-serif;font-size:0.8rem;color:#1a1714;cursor:pointer;outline:none;">
            <option value="">All Genders</option>
            <option value="men"    {{ request('gender') === 'men'    ? 'selected' : '' }}>Men</option>
            <option value="women"  {{ request('gender') === 'women'  ? 'selected' : '' }}>Women</option>
            <option value="unisex" {{ request('gender') === 'unisex' ? 'selected' : '' }}>Unisex</option>
        </select>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..."
               style="padding:10px 16px;border:1px solid #ece4db;font-family:Inter,sans-serif;font-size:0.8rem;outline:none;min-width:200px;" />
        <button type="submit" style="padding:10px 24px;background:#1a1714;color:white;border:none;font-family:Inter,sans-serif;font-size:0.75rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;cursor:pointer;">Search</button>
        @if(request()->hasAny(['category','gender','search']))
            <a href="{{ route('buyer.products') }}" style="font-size:0.75rem;color:#6b5f54;text-decoration:none;">Clear filters</a>
        @endif
    </form>

    <!-- Product Grid -->
    @if($products->count())
        <div class="product-grid" style="margin-bottom:40px;">
            @foreach($products as $product)
            <div class="product-card">
                <div class="product-image">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" />
                    <button class="product-wishlist" onclick="toggleWishlist(this)"><i class="far fa-heart"></i></button>
                    @if($product->badge)
                        <span class="product-badge {{ $product->badge === 'Sale' ? 'sale' : ($product->badge === 'Best Seller' ? 'best' : '') }}">{{ $product->badge }}</span>
                    @endif
                </div>
                <div class="product-info">
                    <h3 class="product-name">{{ $product->name }}</h3>
                    <p class="product-category">{{ ucfirst($product->category) }}</p>
                    <div class="product-price">
                        <span class="current-price">₱{{ number_format($product->price, 2) }}</span>
                        @if($product->original_price)
                            <span class="original-price">₱{{ number_format($product->original_price, 2) }}</span>
                        @endif
                    </div>
                    <button class="btn-add-cart" onclick="addToCart(this, {{ $product->id }})"><i class="fas fa-shopping-bag"></i> Add to Cart</button>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div style="display:flex;justify-content:center;gap:8px;flex-wrap:wrap;">
            {{ $products->withQueryString()->links('vendor.pagination.simple') }}
        </div>
    @else
        <div class="placeholder-card">
            <i class="fas fa-search"></i>
            <h2>No Products Found</h2>
            <p>Try adjusting your filters or search term.</p>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function toggleWishlist(btn) {
    btn.classList.toggle('active');
    btn.querySelector('i').className = btn.classList.contains('active') ? 'fas fa-heart' : 'far fa-heart';
}
function addToCart(btn, id) {
    btn.classList.toggle('added');
    btn.innerHTML = btn.classList.contains('added') ? '✓ Added' : '<i class="fas fa-shopping-bag"></i> Add to Cart';
}
</script>
@endpush
