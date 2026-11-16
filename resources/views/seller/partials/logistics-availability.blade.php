<aside class="seller-notice" aria-label="Logistics partner availability">
    <i class="fas fa-truck" aria-hidden="true"></i>
    <div>
        <strong>{{ $partners->count() }} {{ $partners->count() === 1 ? 'logistics partner available' : 'logistics partners available' }}</strong>
        <p>Choose a partner for each parcel. The selected company's waybill design is used for pickup and delivery.</p>
        @if($partners->count() < 2)
            <p>Other logistics companies appear here after their Logistics accounts are registered, approved by Admin, and active.</p>
        @endif
    </div>
</aside>
