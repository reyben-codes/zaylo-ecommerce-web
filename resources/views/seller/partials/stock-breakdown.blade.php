{{-- Stock per option on the Inventory page: a one-line summary that opens to each color and its sizes. Expects $product and $variants (2 or more). --}}
@php
    $state = fn ($variant) => $variant->stock <= 0 ? 'out' : ($variant->stock <= ($variant->low_stock_threshold ?? $product->low_stock_threshold) ? 'low' : 'in');
    $colors = $variants->groupBy(fn ($variant) => $variant->color_label ?? '');
    $sized = $variants->contains(fn ($variant) => $variant->size_label !== null);
    $soldOut = $variants->filter(fn ($variant) => $state($variant) === 'out')->count();
    $low = $variants->filter(fn ($variant) => $state($variant) === 'low')->count();
    $named = $colors->keys()->filter()->count();
    $parts = array_filter([
        $named > 1 ? $named.' colors' : null,
        $sized ? $variants->count().' sizes' : (! $named ? $variants->count().' options' : null),
    ]);
@endphp

<details class="stock-breakdown" data-smooth-disclosure>
    <summary class="stock-breakdown__summary">
        <span>
            {{ implode(' · ', $parts) ?: $variants->count().' options' }}
            @if($soldOut)<span class="stock-breakdown__flag is-out">{{ $soldOut }} sold out</span>@endif
            @if($low)<span class="stock-breakdown__flag is-low">{{ $low }} low</span>@endif
        </span>
        <i class="fas fa-chevron-down stock-breakdown__chevron" aria-hidden="true"></i>
    </summary>
    <div class="stock-breakdown__body" data-disclosure-content>
        @foreach($colors as $color => $options)
            @php $colorStock = (int) $options->sum('stock'); @endphp
            @if($sized)
                <div class="stock-breakdown__color">
                    <div class="stock-breakdown__color-head">
                        <span>{{ $color !== '' ? $color : 'Sizes' }}</span>
                        <span class="is-{{ $colorStock <= 0 ? 'out' : 'in' }}">{{ $colorStock }}</span>
                    </div>
                    <ul class="stock-breakdown__sizes" aria-label="Stock by size{{ $color !== '' ? ' for '.$color : '' }}">
                        @foreach($options as $variant)
                            <li class="is-{{ $state($variant) }}">{{ $variant->size_label ?? $variant->name }} <strong>{{ $variant->stock }}</strong></li>
                        @endforeach
                    </ul>
                </div>
            @else
                @foreach($options as $variant)
                    <div class="stock-breakdown__color-head">
                        <span>{{ $variant->name }}</span>
                        <span class="is-{{ $state($variant) }}">{{ $variant->stock }}</span>
                    </div>
                @endforeach
            @endif
        @endforeach
    </div>
</details>
