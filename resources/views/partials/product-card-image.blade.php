@php
    $cardImages = collect([$product->image_url])
        ->concat($product->images->pluck('path'))
        ->filter()
        ->unique()
        ->values();
@endphp
<img
    src="{{ $cardImages->first() ?: asset('images/ZAYLO_ICON_DARK.png') }}"
    alt="{{ $product->name }}"
    loading="lazy"
    @if($cardImages->count() > 1) data-card-slides="{{ $cardImages->toJson() }}" @endif
>
@if($cardImages->count() > 1)
    <span class="product-photo-count" data-photo-count aria-hidden="true">1 / {{ $cardImages->count() }}</span>
@endif
