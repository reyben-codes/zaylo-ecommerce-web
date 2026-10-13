@php
    $cardImages = collect([$product->image_url])
        ->concat($product->images->pluck('path'))
        ->filter()
        ->unique()
        ->values();
    $soldOut = $product->is_sold_out;
@endphp
<img
    src="{{ $cardImages->first() ?: asset('images/ZAYLO_ICON_DARK.png') }}"
    alt="{{ $product->name }}{{ $soldOut ? ' (sold out)' : '' }}"
    loading="lazy"
    @if($soldOut) class="is-sold-out" @endif
    @if($cardImages->count() > 1) data-card-slides="{{ $cardImages->toJson() }}" @endif
>
@if($soldOut)
    <span class="product-sold-out-label" aria-hidden="true">Sold out</span>
@endif
@if($cardImages->count() > 1)
    <span class="product-photo-count" data-photo-count aria-hidden="true">1 / {{ $cardImages->count() }}</span>
@endif
