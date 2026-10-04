@php
    $retrySale = (string) old('sale_product_id') === (string) $product->id;
    $saleInput = fn ($key, $default = null) => $retrySale ? old($key, $default) : $default;
@endphp
<details class="product-sale" @if($retrySale) open @endif>
    <summary>Manage sale <span>{{ $product->sale_status }}</span></summary>
    <form method="POST" action="{{ route('seller.products.sale', $product) }}">
        @csrf @method('PUT')
        <input type="hidden" name="sale_product_id" value="{{ $product->id }}">
        <p>Regular price: ₱{{ number_format($product->regular_price, 2) }}. Sale times use Philippine time (UTC+8).</p>
        <label>Discount type
            <select name="sale_type">
                <option value="">No sale / cancel sale</option>
                <option value="price" @selected($saleInput('sale_type', $product->sale_type) === 'price')>Sale price (₱)</option>
                <option value="percent" @selected($saleInput('sale_type', $product->sale_type) === 'percent')>Percentage off (%)</option>
            </select>
        </label>
        <label>Sale price or percentage<input name="sale_value" type="number" min="0.01" step="0.01" max="99999999.99" value="{{ $saleInput('sale_value', $product->sale_value) }}"></label>
        <label>Apply to<select name="sale_variant_id">
            <option value="">Entire product / all variations</option>
            @foreach($product->variants as $saleVariant)
                <option value="{{ $saleVariant->id }}" @selected((string) $saleInput('sale_variant_id', $product->sale_variant_id) === (string) $saleVariant->id)>{{ $saleVariant->name }} — ₱{{ number_format($saleVariant->regular_price, 2) }}</option>
            @endforeach
        </select></label>
        <label>Starts at<input name="sale_starts_at" type="datetime-local" value="{{ $saleInput('sale_starts_at', $product->sale_starts_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i')) }}"></label>
        <label>Ends at<input name="sale_ends_at" type="datetime-local" value="{{ $saleInput('sale_ends_at', $product->sale_ends_at?->copy()->timezone('Asia/Manila')->format('Y-m-d\TH:i')) }}"></label>
        <p>Regular prices stay unchanged. One sale per product; saving replaces its schedule. Use genuine regular prices, not inflated comparison prices.</p>
        @if($retrySale && $errors->any())<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        <button type="submit" class="action-button">Save sale settings</button>
    </form>
</details>
