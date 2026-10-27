{{-- Gross | Net | Commission | Total Orders, in that order, for the Reports and Commission pages.
     Expects $summary (MarketplaceLedger::summary) and $percent (commission rate, e.g. "10"). --}}
<section class="seller-stat-grid" aria-label="Sales and commission summary">
    <div class="seller-stat">
        <span class="seller-stat__label"><i class="fas fa-peso-sign" aria-hidden="true"></i> Gross</span>
        <strong class="seller-stat__value">&#8369;{{ number_format($summary['gross'], 2) }}</strong>
        <span class="seller-stat__note">Merchandise in non-cancelled orders · avg &#8369;{{ number_format($summary['average'], 2) }} per order with shipping</span>
    </div>
    <div class="seller-stat">
        <span class="seller-stat__label"><i class="fas fa-wallet" aria-hidden="true"></i> Net</span>
        <strong class="seller-stat__value">&#8369;{{ number_format($summary['net'], 2) }}</strong>
        <span class="seller-stat__note">What sellers keep after commission</span>
    </div>
    <div class="seller-stat">
        <span class="seller-stat__label"><i class="fas fa-percent" aria-hidden="true"></i> Commission</span>
        <strong class="seller-stat__value">&#8369;{{ number_format($summary['commission'], 2) }}</strong>
        <span class="seller-stat__note">{{ $percent }}% of gross · &#8369;{{ number_format($summary['commissionEarned'], 2) }} earned on completed orders</span>
    </div>
    <div class="seller-stat">
        <span class="seller-stat__label"><i class="fas fa-receipt" aria-hidden="true"></i> Total orders</span>
        <strong class="seller-stat__value">{{ number_format($summary['orders']) }}</strong>
        <span class="seller-stat__note">{{ $summary['completedOrders'] }} completed · {{ $summary['cancelled'] }} cancelled</span>
    </div>
</section>
