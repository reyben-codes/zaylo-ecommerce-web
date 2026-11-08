{{-- A GCash order on My Orders: where to send the money, the reference the buyer gave, and the seller's check. --}}
@php $gcash = \App\Services\GcashPayments::forBuyer($order); @endphp
@if($gcash && $order->status !== 'cancelled')
    @php $refError = $errors->has('gcash_reference') && (int) old('gcash_order') === $order->id; @endphp
    <section class="gcash-status gcash-status--{{ str_replace('_', '-', $gcash['status']) }}" aria-label="GCash payment">
        <div class="gcash-status__head">
            <i class="fas {{ $gcash['status'] === 'paid' ? 'fa-circle-check' : ($gcash['status'] === 'rejected' ? 'fa-circle-exclamation' : 'fa-mobile-screen') }}" aria-hidden="true"></i>
            <div>
                <strong>GCash · {{ $gcash['status_label'] }}</strong>
                @if($gcash['reference'])<span>Reference {{ $gcash['reference'] }}</span>@endif
            </div>
        </div>
        @if($gcash['status'] === 'rejected' && $gcash['rejection_note'])
            <p class="gcash-status__note"><strong>Seller's note:</strong> {{ $gcash['rejection_note'] }}</p>
        @endif
        @if($gcash['can_submit'])
            <p class="gcash-status__note">
                Send <strong>&#8369;{{ number_format($gcash['amount'], 2) }}</strong>
                @if($gcash['seller_account']) to GCash <strong>{{ $gcash['seller_account']['number'] }}</strong> ({{ $gcash['seller_account']['name'] }})@endif,
                then enter the reference number.
                @if($gcash['expires_at']) Unpaid orders are cancelled on {{ \Illuminate\Support\Carbon::parse($gcash['expires_at'])->timezone('Asia/Manila')->format('M j, g:i A') }}.@endif
            </p>
            <form method="POST" action="{{ route('buyer.orders.gcash', $order) }}" class="gcash-status__form">
                @csrf
                <input type="hidden" name="gcash_order" value="{{ $order->id }}">
                <label class="sr-only" for="gcash-ref-{{ $order->id }}">GCash reference number for {{ $order->order_number }}</label>
                <input id="gcash-ref-{{ $order->id }}" type="text" name="gcash_reference" inputmode="numeric" autocomplete="off" maxlength="13" minlength="13"
                       placeholder="13-digit reference no." value="{{ $refError ? old('gcash_reference') : '' }}" required pattern="[0-9]{13}" @if($refError) aria-invalid="true" @endif>
                <button class="market-button" type="submit">Send reference</button>
            </form>
            @if($refError)<p class="gcash-status__error" role="alert">{{ $errors->first('gcash_reference') }}</p>@endif
        @elseif($gcash['status'] === 'pending_verification')
            <p class="gcash-status__note">The seller confirms your order once they find this payment in their GCash.</p>
        @endif
    </section>
@endif
