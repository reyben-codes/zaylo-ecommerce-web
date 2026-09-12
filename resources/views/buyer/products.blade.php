@extends('layouts.app')

@section('title', 'Shop · ZAYLO')
@section('nav-links')
@include('partials.store-nav')
@endsection
@section('nav-icons')
    @if(auth()->check() && auth()->user()->role === 'buyer')
        <a href="{{ route('buyer.wishlist') }}" aria-label="Wishlist"><i class="far fa-heart"></i></a>
        <a href="{{ route('buyer.cart') }}" aria-label="Shopping cart"><i class="fas fa-shopping-bag"></i></a>
    @endif
@endsection

@section('content')
<div class="page-hero"><div class="page-hero-inner"><i class="fas fa-tags"></i><div>
    <h1>{{ $category ? config('marketplace.categories.'.$category, str($category)->replace('_', ' ')->title()) : 'Shop All' }}</h1>
    <p>Explore products from approved sellers across the ZAYLO marketplace.</p>
</div></div></div>
<div class="page-content">
    <form method="GET" action="{{ route('products.index') }}" class="market-form filter-bar">
        <select name="category" aria-label="Category">
            <option value="">All categories</option>
            @foreach(config('marketplace.categories') as $cat => $label)
                <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="gender" aria-label="Gender">
            <option value="">All genders</option>
            @foreach(['men','women','unisex'] as $gender)
                <option value="{{ $gender }}" @selected(request('gender') === $gender)>{{ ucfirst($gender) }}</option>
            @endforeach
        </select>
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search products">
        <select name="sort" aria-label="Sort products">
            <option value="newest">Newest</option>
            <option value="price_low" @selected(request('sort') === 'price_low')>Price: low to high</option>
            <option value="price_high" @selected(request('sort') === 'price_high')>Price: high to low</option>
        </select>
        <button class="market-button" type="submit">Apply</button>
    </form>

    @if($products->count())
        <div class="product-grid">
            @foreach($products as $product)
            <article class="product-card">
                <a href="{{ route('products.show', $product) }}" class="product-image">
                    <img src="{{ $product->image_url ?: asset('images/ZAYLO_ICON_DARK.png') }}" alt="{{ $product->name }}" loading="lazy">
                    @if($product->badge)<span class="product-badge">{{ $product->badge }}</span>@endif
                </a>
                <div class="product-info">
                    <p class="product-category">{{ config('marketplace.categories.'.$product->category, str($product->category)->replace('_', ' ')->title()) }}</p>
                    <h2 class="product-name"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></h2>
                    <div class="product-price"><span class="current-price">₱{{ number_format($product->price, 2) }}</span></div>
                    <div class="card-actions">
                        @if(auth()->check() && auth()->user()->role === 'buyer')
                            <form method="POST" action="{{ route('buyer.cart.add', $product) }}">@csrf<input type="hidden" name="quantity" value="1"><button class="btn-add-cart">Add to cart</button></form>
                            <form method="POST" action="{{ route('buyer.wishlist.toggle', $product) }}">@csrf<button class="icon-button" aria-label="Save {{ $product->name }} to wishlist"><i class="far fa-heart"></i></button></form>
                        @else
                            <a class="btn-add-cart" href="{{ route('login') }}">Sign in to buy</a>
                        @endif
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        <div class="pagination-wrap">{{ $products->links() }}</div>
    @else
        <div class="placeholder-card"><i class="fas fa-search"></i><h2>No products found</h2><p>Try another filter or search term.</p></div>
    @endif
</div>
@endsection
