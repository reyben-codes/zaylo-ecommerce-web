@if(filled($product->description))
    <p class="product-card-description">{{ $product->description }}</p>
@else
    <p class="product-card-description">No Description</p>
@endif
