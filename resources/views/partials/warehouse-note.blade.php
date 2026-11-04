{{-- Shown while the linked quantity input is above Zaylo's warehouse capacity; public/js/quantity-stepper.js keeps it in sync. --}}
<div class="warehouse-note" data-warehouse-note="{{ $quantityId }}" data-warehouse-capacity="{{ \App\Models\Product::WAREHOUSE_CAPACITY }}" role="status" @if(($quantity ?? 1) <= \App\Models\Product::WAREHOUSE_CAPACITY) hidden @endif>
    <p><i class="fas fa-warehouse" aria-hidden="true"></i> Zaylo's warehouse can only hold {{ \App\Models\Product::WAREHOUSE_CAPACITY }} of this item, so orders over {{ \App\Models\Product::WAREHOUSE_CAPACITY }} ship directly from the seller's warehouse instead.</p>
</div>
