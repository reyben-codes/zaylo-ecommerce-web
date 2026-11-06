{{-- "18+" badge for a product its seller marked for adults only; ordering it needs an approved ID. Expects $product. --}}
@if($product->is_adult_only)
    <span class="adult-badge" title="For adults only. An approved ID is needed to order it."><i class="fas fa-id-card" aria-hidden="true"></i> 18+<span class="sr-only"> for adults only</span></span>
@endif
