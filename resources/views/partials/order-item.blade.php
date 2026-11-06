{{--
    One checked-out product in an order: its photo, name, option and quantity, linked to the product page while the
    product is still on sale. Used by the buyer's My Orders and the seller's Order fulfillment pages.
--}}
@php
    $product = $item->product;
    $photo = $product ? collect([$product->image_url])->concat($product->images->pluck('path'))->filter()->first() : null;
    $photoUrl = $photo ? (\Illuminate\Support\Str::startsWith($photo, ['http://', 'https://', '/']) ? $photo : asset($photo)) : null;
    $productUrl = $product && $product->is_listed && ! $product->is_archived ? route('products.show', $product) : null;
    $variant = $item->variant_name ?? null;
@endphp
<div class="order-item">
    @if($productUrl)
        <a href="{{ $productUrl }}" class="order-item__photo" tabindex="-1" aria-hidden="true">
    @else
        <span class="order-item__photo" aria-hidden="true">
    @endif
        @if($photoUrl)
            <img src="{{ $photoUrl }}" alt="" loading="lazy">
        @else
            <i class="fas fa-box" aria-hidden="true"></i>
        @endif
    @if($productUrl)</a>@else</span>@endif

    <div class="order-item__info">
        @if($productUrl)
            <a href="{{ $productUrl }}" class="order-item__name">{{ $item->product_name }}</a>
        @else
            <span class="order-item__name">{{ $item->product_name }}</span>
        @endif
        <span class="order-item__meta">
            @if($variant){{ $variant }} · @endif{{ $item->quantity }} &times; &#8369;{{ number_format($item->unit_price, 2) }}
            @unless($productUrl)<span class="order-item__gone">· No longer on sale</span>@endunless
        </span>
    </div>

    <strong class="order-item__total">&#8369;{{ number_format($item->line_total, 2) }}</strong>
</div>
