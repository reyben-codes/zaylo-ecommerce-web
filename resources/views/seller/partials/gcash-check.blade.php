{{-- A GCash order the buyer paid straight to the seller: check the reference in GCash before confirming the order. --}}
@php
    $payment = $order->payment;
    $status = $payment?->status;
    $noteError = $errors->has('note') && (int) old('gcash_order') === $order->id;
@endphp
<div class="gcash-check gcash-check--{{ str_replace('_', '-', (string) $status) }}">
    <strong><i class="fas fa-mobile-screen" aria-hidden="true"></i> GCash &#8369;{{ number_format($order->total, 2) }}</strong>
    @if($status === \App\Services\GcashPayments::CHECKING)
        <span>Reference <b>{{ $payment->gcash_reference }}</b>. Find it in your GCash app before confirming.</span>
        <div class="gcash-check__actions">
            <form method="POST" action="{{ route('seller.orders.gcash', $order) }}"
                  data-confirm="You found GCash reference {{ $payment->gcash_reference }} for &#8369;{{ number_format($order->total, 2) }} in your GCash app?" data-confirm-title="Payment received?" data-confirm-button="Yes, received">
                @csrf
                @method('PATCH')
                <input type="hidden" name="decision" value="received">
                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-check" aria-hidden="true"></i> Payment received</button>
            </form>
            <details class="gcash-check__reject" data-smooth-disclosure @if($noteError) open @endif>
                <summary class="seller-button seller-button--small seller-button--secondary">Not found</summary>
                <div data-disclosure-content>
                    <form method="POST" action="{{ route('seller.orders.gcash', $order) }}" class="gcash-check__form">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="not_found">
                        <input type="hidden" name="gcash_order" value="{{ $order->id }}">
                        <label for="gcash-note-{{ $order->id }}">What did you find?</label>
                        <textarea id="gcash-note-{{ $order->id }}" name="note" rows="2" minlength="5" maxlength="255" required placeholder="e.g. No payment with this reference, or a different amount.">{{ $noteError ? old('note') : '' }}</textarea>
                        @if($noteError)<p class="seller-field__error" role="alert">{{ $errors->first('note') }}</p>@endif
                        <button type="submit" class="seller-button seller-button--small seller-button--danger">Ask buyer to check</button>
                    </form>
                </div>
            </details>
        </div>
    @elseif($status === \App\Services\GcashPayments::PAID)
        <span><i class="fas fa-circle-check" aria-hidden="true"></i> Received · ref {{ $payment->gcash_reference }}</span>
    @elseif($status === \App\Services\GcashPayments::REJECTED)
        <span>You could not find ref {{ $payment->gcash_reference }}. Waiting for the buyer to check it.</span>
    @else
        <span>Waiting for the buyer's GCash reference.</span>
    @endif
</div>
