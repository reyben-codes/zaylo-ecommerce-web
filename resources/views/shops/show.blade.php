@extends('layouts.app')

@section('title', $shop->name.' · ZAYLO')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/shop-page.css') }}?v={{ filemtime(public_path('css/shop-page.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/shop-page.js') }}?v={{ filemtime(public_path('js/shop-page.js')) }}" defer></script>
@endpush

@section('content')
@php
    $viewer = auth()->user();
    $canFollow = $viewer?->role === 'buyer';
    $filtered = $filters['q'] || $filters['category'];
@endphp
<div class="page-content shop-page">
    <section class="shop-hero" aria-labelledby="shop-name">
        <div class="shop-hero__identity">
            @if($shop->owner->avatar_url)
                <img src="{{ $shop->owner->avatar_url }}" alt="" class="shop-avatar">
            @else
                <span class="shop-avatar shop-avatar--initials" aria-hidden="true">{{ $shop->initials() }}</span>
            @endif
            <div class="shop-hero__text">
                <p class="shop-hero__eyebrow"><i class="fas fa-store" aria-hidden="true"></i> ZAYLO shop</p>
                <h1 id="shop-name">{{ $shop->name }}</h1>
                <p class="shop-hero__meta">
                    @if($shop->joined)<span><i class="far fa-calendar" aria-hidden="true"></i> Joined {{ $shop->joined->format('F Y') }}</span>@endif
                    @if($shop->location)<span><i class="fas fa-location-dot" aria-hidden="true"></i> {{ $shop->location }}</span>@endif
                </p>
            </div>
        </div>

        <dl class="shop-stats">
            <div class="shop-stat shop-stat--products">
                <span class="shop-stat__icon" aria-hidden="true"><i class="fas fa-box-open"></i></span>
                <dt class="shop-stat__label">Products</dt>
                <dd class="shop-stat__value">{{ number_format($productCount) }}</dd>
            </div>
            <div class="shop-stat shop-stat--followers">
                <span class="shop-stat__icon" aria-hidden="true"><i class="fas fa-user-group"></i></span>
                <dt class="shop-stat__label">Followers</dt>
                <dd class="shop-stat__value" data-follower-count>{{ number_format($followers) }}</dd>
            </div>
            <div class="shop-stat shop-stat--rating">
                <span class="shop-stat__icon" aria-hidden="true"><i class="fas fa-star"></i></span>
                @if($rating['average'] !== null)
                    <dt class="shop-stat__label">Rating · {{ number_format($rating['count']) }} {{ Str::plural('review', $rating['count']) }}</dt>
                    <dd class="shop-stat__value">{{ number_format($rating['average'], 1) }}<span class="shop-stat__out-of">/5</span></dd>
                @else
                    <dt class="shop-stat__label">No ratings yet</dt>
                    <dd class="shop-stat__value shop-stat__value--text">New</dd>
                @endif
            </div>
            <div class="shop-stat shop-stat--sold">
                <span class="shop-stat__icon" aria-hidden="true"><i class="fas fa-bag-shopping"></i></span>
                <dt class="shop-stat__label">Items sold</dt>
                <dd class="shop-stat__value">{{ number_format($sold) }}</dd>
            </div>
        </dl>

        <div class="shop-hero__actions">
            @if($isOwner)
                <a href="{{ route('seller.products') }}" class="market-button"><i class="fas fa-pen" aria-hidden="true"></i> Manage products</a>
                <span class="shop-hero__note">This is how buyers see your shop.</span>
            @elseif($canFollow)
                <form method="POST" action="{{ route('buyer.shops.follow', $shop->id) }}" data-follow-form>
                    @csrf
                    <button type="submit" class="market-button shop-follow{{ $following ? ' is-following' : '' }}" aria-pressed="{{ $following ? 'true' : 'false' }}" data-follow-button>
                        <i class="fas {{ $following ? 'fa-check' : 'fa-plus' }}" aria-hidden="true"></i>
                        <span data-follow-label>{{ $following ? 'Following' : 'Follow' }}</span>
                    </button>
                </form>
                <form method="POST" action="{{ route('buyer.chat.shop', $shop->id) }}">
                    @csrf
                    <button type="submit" class="market-button market-button--secondary"><i class="far fa-comment-dots" aria-hidden="true"></i> Chat with seller</button>
                </form>
            @elseif(! $viewer)
                <a href="{{ route('login') }}" class="market-button"><i class="fas fa-plus" aria-hidden="true"></i> Sign in to follow</a>
            @endif
            <button type="button" class="market-button market-button--secondary" data-share-url="{{ $shop->url() }}" data-share-title="{{ $shop->name }} on ZAYLO" hidden>
                <i class="fas fa-share-nodes" aria-hidden="true"></i> Share
            </button>
            <p class="shop-hero__status" data-shop-status role="status" aria-live="polite"></p>
        </div>
    </section>

    @if($shop->description)
        <section class="shop-about" aria-labelledby="shop-about-heading">
            <h2 id="shop-about-heading">About {{ $shop->name }}</h2>
            <p>{{ $shop->description }}</p>
        </section>
    @endif

    <section class="shop-products" aria-labelledby="shop-products-heading">
        <div class="shop-products__head">
            <h2 id="shop-products-heading">Products <span>{{ number_format($products->total()) }}{{ $filtered ? ' found' : '' }}</span></h2>
            <form method="GET" action="{{ $shop->url() }}" class="shop-filters" role="search" aria-label="Search this shop">
                <label class="shop-filters__search">
                    <span class="sr-only">Search this shop</span>
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Search {{ $shop->name }}">
                </label>
                @if(count($categories) > 1)
                    <label>
                        <span class="sr-only">Category</span>
                        <select name="category">
                            <option value="">All categories</option>
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}" @selected($filters['category'] === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label>
                    <span class="sr-only">Sort by</span>
                    <select name="sort">
                        @foreach($sorts as $key => $label)
                            <option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="market-button">Apply</button>
                @if($filtered)
                    <a href="{{ $shop->url() }}" class="shop-filters__reset">Clear</a>
                @endif
            </form>
        </div>

        @if($products->count())
            <div class="product-grid">
                @foreach($products as $product)
                    @include('partials.shop-card', ['product' => $product, 'heading' => 'h3'])
                @endforeach
            </div>
            <div class="pagination-wrap">{{ $products->links() }}</div>
        @else
            <div class="placeholder-card shop-empty">
                <i class="fas fa-box-open" aria-hidden="true"></i>
                @if($filtered)
                    <h3>No products match</h3>
                    <p>Try another word or category.</p>
                    <a href="{{ $shop->url() }}" class="market-button">See all products</a>
                @else
                    <h3>No products yet</h3>
                    <p>{{ $shop->name }} hasn't listed anything yet. Follow the shop to hear when it does.</p>
                @endif
            </div>
        @endif
    </section>

    <section class="shop-reviews" aria-labelledby="shop-reviews-heading">
        <h2 id="shop-reviews-heading">Recent reviews</h2>
        @forelse($reviews as $review)
            <article class="shop-review">
                <div class="shop-review__head">
                    <span class="shop-review__stars" aria-label="{{ $review->rating }} out of 5 stars">
                        @for($star = 1; $star <= 5; $star++)<i class="{{ $star <= $review->rating ? 'fas' : 'far' }} fa-star" aria-hidden="true"></i>@endfor
                    </span>
                    <strong>{{ Str::before((string) $review->buyer?->name, ' ') ?: 'A buyer' }}</strong>
                    <time datetime="{{ $review->created_at?->toDateString() }}">{{ $review->created_at?->timezone('Asia/Manila')->format('M j, Y') }}</time>
                </div>
                @if($review->comment)<p>{{ $review->comment }}</p>@endif
                @if($review->product)
                    <a href="{{ $review->product->is_listed ? route('products.show', $review->product) : '#' }}" class="shop-review__product" @unless($review->product->is_listed) aria-disabled="true" @endunless>{{ $review->product->name }}</a>
                @endif
            </article>
        @empty
            <p class="empty-note">No reviews yet. Reviews from buyers who received their orders will show here.</p>
        @endforelse
    </section>
</div>
@endsection
