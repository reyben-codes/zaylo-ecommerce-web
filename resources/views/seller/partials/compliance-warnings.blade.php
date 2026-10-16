{{-- Policy warnings from the ZAYLO admin that the seller has not acknowledged yet. --}}
@php
    $openWarnings = \Illuminate\Support\Facades\Schema::hasTable('compliance_warnings')
        ? \App\Models\ComplianceWarning::with('product')->where('seller_id', auth()->id())->whereNull('acknowledged_at')->latest()->get()
        : collect();
@endphp
@foreach($openWarnings as $warning)
    <aside class="seller-notice seller-notice--danger compliance-notice" role="alert">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <div>
            <strong>Policy warning: {{ $warning->reason_label }}{{ $warning->product ? ' · '.$warning->product->name : '' }}</strong>
            <p>{{ $warning->details }}</p>
            <p class="seller-cell-note">{{ $warning->action === 'product_hidden' ? 'This product is hidden from buyers until it is fixed and reviewed.' : 'Please correct this. Repeated violations can lead to hidden products or a suspended shop.' }} Questions? <a href="{{ route('support') }}" class="seller-link">Message ZAYLO support</a>.</p>
            <form method="POST" action="{{ route('seller.warnings.acknowledge', $warning) }}">
                @csrf
                <button type="submit" class="seller-button seller-button--small seller-button--secondary">I understand</button>
            </form>
        </div>
    </aside>
@endforeach
