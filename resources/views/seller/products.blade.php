@extends('layouts.seller')

@section('title', 'Products')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/seller-products.css') }}?v={{ filemtime(public_path('css/seller-products.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/category-tags.css') }}?v={{ filemtime(public_path('css/category-tags.css')) }}">
@endpush

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">{{ $storeName }}</p>
            <h1>Products</h1>
            <p>Edit what buyers see: names, descriptions, SKUs, prices, photos, sales, and stock.</p>
        </div>
        <div class="seller-page-heading__actions">
            <a href="{{ route('seller.products.create') }}" class="seller-button"><i class="fas fa-plus" aria-hidden="true"></i> Add product</a>
        </div>
    </section>

    <nav class="product-tabs" aria-label="Product lists">
        <a href="{{ route('seller.products') }}" class="product-tab{{ $filters['view'] === 'archived' ? '' : ' is-active' }}" @unless($filters['view'] === 'archived') aria-current="page" @endunless>
            Products <span>{{ $counts['active'] }}</span>
        </a>
        <a href="{{ route('seller.products', ['view' => 'archived']) }}" class="product-tab{{ $filters['view'] === 'archived' ? ' is-active' : '' }}" @if($filters['view'] === 'archived') aria-current="page" @endif>
            <i class="fas fa-box-archive" aria-hidden="true"></i> Archived <span>{{ $counts['archived'] }}</span>
        </a>
    </nav>

    @if($filters['view'] === 'archived')
        <p class="product-tabs__note">Archived products are hidden from buyers. Their reviews, photos and order history are kept, and you can restore them anytime.</p>
    @endif

    <section class="seller-panel products-filter">
        <form method="GET" action="{{ route('seller.products') }}" class="seller-filter-bar" role="search">
            @if($filters['view'] === 'archived')<input type="hidden" name="view" value="archived">@endif
            <div class="seller-field seller-field--search">
                <label for="product-search">Search</label>
                <input type="search" id="product-search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Product name or SKU">
            </div>
            <div class="seller-field">
                <label for="product-category">Category</label>
                <select id="product-category" name="category">
                    <option value="">All categories</option>
                    @foreach($categories as $value => $label)
                        <option value="{{ $value }}" @selected($filters['category'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="products-filter__actions">
                <button type="submit" class="seller-button"><i class="fas fa-filter" aria-hidden="true"></i> Apply</button>
                @if($filters['q'] || $filters['category'])
                    <a href="{{ route('seller.products', $filters['view'] === 'archived' ? ['view' => 'archived'] : []) }}" class="seller-button seller-button--secondary">Reset</a>
                @endif
            </div>
        </form>
        <p class="products-filter__count">
            {{ $products->total() }} of {{ $total }} {{ Str::plural('product', $total) }}
        </p>
    </section>

    <section class="products-grid" aria-label="Your products">
        @forelse($products as $product)
            @php
                $stock = $stockStates[$product->id];
            @endphp
            <article class="product-card">
                <a href="{{ route('seller.products.edit', $product) }}" class="product-card__image" tabindex="-1" aria-hidden="true">
                    <img src="{{ $product->image_url ?: asset('images/ZAYLO_LOGO_DARK.png') }}" alt="" loading="lazy" data-fallback-src="{{ asset('images/ZAYLO_LOGO_DARK.png') }}">
                    @php
                        [$visibilityClass, $visibilityLabel] = match (true) {
                            $product->is_archived => [' is-archived', 'Archived'],
                            $product->is_pending_review => [' is-pending', 'Pending review'],
                            $product->is_rejected => [' is-rejected', 'Rejected'],
                            $product->is_active => ['', 'Active'],
                            default => [' is-hidden', 'Hidden'],
                        };
                    @endphp
                    <span class="product-card__visibility{{ $visibilityClass }}">{{ $visibilityLabel }}</span>
                </a>
                <div class="product-card__body">
                    @include('partials.category-tags', ['href' => fn ($key) => route('seller.products', ['category' => $key])])
                    @if($product->gender_label)<p class="product-card__category">{{ $product->gender_label }}</p>@endif
                    <h2 class="product-card__name"><a href="{{ route('seller.products.edit', $product) }}">{{ $product->name }}</a></h2>
                    <p class="product-card__sku">SKU {{ $product->sku ?: '—' }}</p>
                    <p class="product-card__description">{{ $product->description ?: 'No description yet. Add one so buyers know what they are getting.' }}</p>
                    <div class="product-card__meta">
                        <span>
                            <strong>&#8369;{{ number_format((float) $product->price, 2) }}</strong>
                            @if($product->original_price)
                                <s>&#8369;{{ number_format((float) $product->original_price, 2) }}</s>
                            @endif
                        </span>
                        <span class="product-card__stock product-card__stock--{{ $stock['state'] }}">{{ $stock['total'] === 0 ? 'Out of stock' : $stock['total'].' in stock' }}</span>
                    </div>
                    @if($product->sale_type)
                        <p class="product-card__sale"><i class="fas fa-tag" aria-hidden="true"></i> {{ $product->sale_status }}</p>
                    @endif
                    <div class="product-card__actions">
                        @if($product->is_archived)
                            <form method="POST" action="{{ route('seller.products.restore', $product) }}"
                                  data-confirm="Put {{ $product->name }} back in your shop? Buyers will see it again." data-confirm-title="Restore product?" data-confirm-button="Restore">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-rotate-left" aria-hidden="true"></i> Restore</button>
                            </form>
                            <a href="{{ route('seller.products.edit', $product) }}" class="seller-button seller-button--small seller-button--secondary"><i class="fas fa-eye" aria-hidden="true"></i> View</a>
                        @else
                            <a href="{{ route('seller.products.edit', $product) }}" class="seller-button seller-button--small"><i class="fas fa-pen" aria-hidden="true"></i> Edit product</a>
                        @endif
                        @if($product->is_active)
                            <a href="{{ route('products.show', $product) }}" class="product-card__icon" target="_blank" rel="noopener" aria-label="View {{ $product->name }} in the shop (opens a new tab)"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="seller-empty products-empty">
                <i class="fas fa-box-open" aria-hidden="true"></i>
                @if($filters['view'] === 'archived' && $total === 0)
                    <strong>Nothing archived</strong>
                    <span>Products you archive show up here, ready to restore.</span>
                @elseif($total === 0)
                    <strong>No products yet</strong>
                    <span>Add the first product for {{ $storeName }}.</span>
                @else
                    <strong>No products match these filters</strong>
                    <span>Try another search or category.</span>
                @endif
            </div>
        @endforelse
    </section>

    @if($products->hasPages())
        <div class="seller-pagination products-pagination">{{ $products->links() }}</div>
    @endif
@endsection
