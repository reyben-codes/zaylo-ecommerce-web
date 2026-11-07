@extends('layouts.seller')

@section('title', 'Vouchers')

@section('content')
    <section class="seller-page-heading">
        <div>
            <p class="seller-eyebrow">Seller Center</p>
            <h1>Vouchers</h1>
            <p>ZAYLO invites your shop to vouchers. If you join, buyers can use the voucher on your products and your shop pays the discount (it comes off your payout for those orders). If you decline or do not answer, it does not apply to your products. Free-shipping vouchers are paid by ZAYLO and need no answer.</p>
        </div>
    </section>

    <section class="seller-panel">
        <div class="seller-table-wrap">
            <table class="seller-table">
                <thead><tr><th>Voucher</th><th>Offer</th><th>Runs</th><th>Your answer</th></tr></thead>
                <tbody>
                    @forelse($invitations as $invitation)
                        @php $voucher = $invitation->voucher; @endphp
                        <tr>
                            <td><strong>{{ $voucher->code }}</strong>@if($voucher->name)<span class="seller-cell-note">{{ $voucher->name }}</span>@endif</td>
                            <td>{{ $voucher->describe() }}</td>
                            <td>{{ $voucher->starts_at?->timezone('Asia/Manila')->format('M j') ?? 'Now' }} – {{ $voucher->expires_at?->timezone('Asia/Manila')->format('M j, Y') ?? 'until ended' }}@unless($voucher->is_active)<span class="seller-cell-note">Turned off by ZAYLO</span>@endunless</td>
                            <td>
                                <div class="order-actions">
                                    @if($invitation->status !== 'pending')
                                        <span class="order-status order-status--{{ $invitation->status === 'accepted' ? 'completed' : 'cancelled' }}">{{ $invitation->status === 'accepted' ? 'Joined' : 'Declined' }}</span>
                                    @endif
                                    @if($voucher->is_active)
                                        @if($invitation->status !== 'accepted')
                                            <form method="POST" action="{{ route('seller.vouchers.respond', $voucher) }}"
                                                  data-confirm="Join {{ $voucher->code }} ({{ $voucher->describe() }})? Buyers can use it on your products and your shop pays the discount." data-confirm-title="Join voucher?" data-confirm-button="Join">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="decision" value="join">
                                                <button type="submit" class="seller-button seller-button--small"><i class="fas fa-check" aria-hidden="true"></i> Join</button>
                                            </form>
                                        @endif
                                        @if($invitation->status !== 'declined')
                                            <form method="POST" action="{{ route('seller.vouchers.respond', $voucher) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="decision" value="decline">
                                                <button type="submit" class="seller-button seller-button--small seller-button--secondary">{{ $invitation->status === 'accepted' ? 'Leave' : 'Decline' }}</button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="seller-empty"><i class="fas fa-ticket" aria-hidden="true"></i><strong>No voucher invitations yet</strong><span>When ZAYLO starts a voucher, it appears here and you get an email.</span></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
