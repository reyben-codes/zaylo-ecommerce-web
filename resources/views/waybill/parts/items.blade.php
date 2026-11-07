<table class="wb-items">
    <thead>
        <tr><th>Contents</th><th class="wb-qty">Qty</th></tr>
    </thead>
    <tbody>
        @forelse($shownItems as $item)
            <tr>
                <td>{{ \Illuminate\Support\Str::limit($item['name'], 40) }}@if($item['variant']) <span class="wb-variant">({{ $item['variant'] }})</span>@endif</td>
                <td class="wb-qty">{{ $item['quantity'] }}</td>
            </tr>
        @empty
            <tr><td colspan="2">Items not listed</td></tr>
        @endforelse
        @if($moreItems > 0)
            <tr><td colspan="2" class="wb-more">+ {{ $moreItems }} more {{ \Illuminate\Support\Str::plural('item', $moreItems) }} (see order {{ $waybill['order'] }})</td></tr>
        @endif
    </tbody>
</table>
