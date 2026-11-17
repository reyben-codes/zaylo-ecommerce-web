<section class="seller-stat-grid" aria-label="Shipping allocation">
    <div class="seller-stat">
        <span class="seller-stat__label">First-mile courier earnings</span>
        <strong class="seller-stat__value">&#8369;{{ number_format($summary['first_mile'], 2) }}</strong>
        <span class="seller-stat__note">Seller to origin hub; credited from funded shipping</span>
    </div>
    <div class="seller-stat">
        <span class="seller-stat__label">Last-mile rider earnings</span>
        <strong class="seller-stat__value">&#8369;{{ number_format($summary['last_mile'], 2) }}</strong>
        <span class="seller-stat__note">Destination hub to buyer; credited from funded shipping</span>
    </div>
    <div class="seller-stat">
        <span class="seller-stat__label">Shipping after courier and rider earnings</span>
        <strong class="seller-stat__value">&#8369;{{ number_format($summary['shipping_remaining'], 2) }}</strong>
        <span class="seller-stat__note">Shipping fees less credited leg earnings; product commission is separate</span>
    </div>
</section>
