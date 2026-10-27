{{--
    A storefront product tile in the mobile app's style (mobile_app/lib/features/buyer/catalog/product_card.dart):
    the photo fades into a soft card, with the save button, badge and rating over it, then the categories, name,
    description, price and a compact bag button. Expects $product; optional $heading ('h2' or 'h3', default 'h3')
    and $showStock (how many are in stock, or Sold out). $savedProductIds comes from AppServiceProvider.
--}}
@php
    $heading = ($heading ?? 'h3') === 'h2' ? 'h2' : 'h3';
    $url = route('products.show', $product);
    $viewer = auth()->user();
    $isBuyer = $viewer?->role === 'buyer';
    $soldOut = $product->is_sold_out;
    $onSale = (float) $product->original_price > (float) $product->price;
    $badge = $onSale ? '-'.$product->discount_percentage.'%' : $product->badge;
    $ratingCount = (int) $product->reviews_count;
    $ratingAverage = (float) $product->reviews_avg_rating;
    $saved = isset($savedProductIds[$product->id]);
@endphp
<article class="shop-card" data-product-url="{{ $url }}">
    <div class="shop-card__media">
        <a class="shop-card__photo" href="{{ $url }}" aria-label="View {{ $product->name }}">
            @include('partials.product-card-image', ['product' => $product])
        </a>
        @if($isBuyer)
            <form class="shop-card__save" data-wishlist-toggle method="POST" action="{{ route('buyer.wishlist.toggle', $product) }}">
                @csrf
                <button class="shop-card__button shop-card__button--light" aria-pressed="{{ $saved ? 'true' : 'false' }}" aria-label="{{ $saved ? 'Remove' : 'Save' }} {{ $product->name }} {{ $saved ? 'from' : 'to' }} wishlist" title="{{ $saved ? 'Remove from wishlist' : 'Save for later' }}"><i class="{{ $saved ? 'fas' : 'far' }} fa-heart" aria-hidden="true"></i></button>
            </form>
        @endif
        @if($badge)
            <span class="shop-card__pill shop-card__pill--badge">{{ $badge }}</span>
        @endif
        @if($ratingCount)
            <a class="shop-card__pill shop-card__pill--rating" href="{{ $url }}#customer-reviews" aria-label="{{ number_format($ratingAverage, 1) }} out of 5 stars, {{ $ratingCount }} {{ Str::plural('review', $ratingCount) }}. Read customer reviews"><i class="fas fa-star" aria-hidden="true"></i>{{ number_format($ratingAverage, 1) }}</a>
        @endif
    </div>
    <div class="shop-card__body">
        @include('partials.category-tags', ['href' => fn ($key) => route('products.index', ['category' => $key])])
        <{{ $heading }} class="shop-card__name"><a href="{{ $url }}">{{ $product->name }}</a></{{ $heading }}>
        @include('partials.product-card-description')
        <div class="shop-card__footer">
            <p class="shop-card__price">
                <strong>₱{{ number_format($product->price, 2) }}</strong>
                @if($onSale)<del aria-label="Was ₱{{ number_format($product->original_price, 2) }}">₱{{ number_format($product->original_price) }}</del>@endif
            </p>
            @unless($soldOut)
                @if($isBuyer && ! $product->active_variants_count)
                    <form data-cart-add method="POST" action="{{ route('buyer.cart.add', $product) }}">
                        @csrf
                        <input type="hidden" name="quantity" value="1">
                        <button class="shop-card__button shop-card__button--ink" aria-label="Add {{ $product->name }} to cart" title="Add to cart"><i class="fas fa-cart-shopping" aria-hidden="true"></i></button>
                    </form>
                @elseif($isBuyer)
                    <a class="shop-card__button shop-card__button--ink" href="{{ $url }}" aria-label="Choose options for {{ $product->name }}" title="Choose options"><i class="fas fa-cart-shopping" aria-hidden="true"></i></a>
                @elseif(! $viewer)
                    <a class="shop-card__button shop-card__button--ink" href="{{ route('login') }}" aria-label="Sign in to buy {{ $product->name }}" title="Sign in to buy"><i class="fas fa-cart-shopping" aria-hidden="true"></i></a>
                @endif
            @endunless
        </div>
        @if($product->isFlashSale())
            <p class="shop-card__flash"><i class="fas fa-bolt" aria-hidden="true"></i> Flash sale · {{ number_format($product->flashRemaining()) }} left at this price</p>
        @endif
        @if($showStock ?? false)
            <p class="catalog-stock {{ $soldOut ? 'is-sold-out' : '' }}">{{ $soldOut ? 'Currently sold out' : number_format($product->available_stock).' in stock' }}</p>
        @endif
    </div>
</article>
