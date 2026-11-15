@extends($admin ? 'layouts.workspace' : 'layouts.seller', $admin ? ['workspace' => 'admin'] : [])
@section('title', $admin ? 'Seller payouts' : 'Seller wallet')
@section('content')
<section class="seller-page-heading"><div><h1>{{ $admin ? 'Seller payouts' : 'Seller wallet' }}</h1><p>Escrow proceeds become available after delivery confirmation and fee deductions. Withdrawals stay pending until funds are transferred.</p></div></section>
@if(! config('escrow.live') && ! \App\Services\DemoPayments::enabled())<aside class="seller-notice"><p>Test payment mode. Sandbox balances do not represent real money. Do not send real transfers for test payments.</p></aside>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@if($admin)
<section class="seller-panel"><header class="seller-panel__header"><h2>Gateway payment holds</h2></header><div class="seller-table-wrap"><table class="seller-table"><thead><tr><th>Order</th><th>Amount</th><th>Gateway fee</th><th>State</th><th>Gateway reference</th></tr></thead><tbody>
@forelse($escrows as $escrow)<tr><td>{{ $escrow->order?->order_number }}<span class="seller-cell-note">{{ \App\Services\DemoPayments::isDemo($escrow) ? 'GCash' : ($escrow->livemode ? 'Live' : 'Test') }}</span></td><td>PHP {{ number_format($escrow->amount_minor / 100, 2) }}</td><td>PHP {{ number_format($escrow->gateway_fee_minor / 100, 2) }}</td><td>{{ str_replace('_', ' ', $escrow->status) }}</td><td>{{ $escrow->gateway_payment ?? 'Pending payment' }}</td></tr>@empty<tr><td colspan="5">No gateway payments yet.</td></tr>@endforelse
</tbody></table></div></section>
@endif
@unless($admin)
<section class="seller-panel"><div class="seller-panel__body"><h2>Available: PHP {{ number_format($balance, 2) }}</h2>
<form method="POST" action="{{ route('seller.wallet.request') }}" class="seller-form">@csrf
<div class="seller-field"><label for="wallet-amount">Amount</label><input id="wallet-amount" name="amount" type="number" min="0.01" max="{{ max(0.01, $balance) }}" step="0.01" required></div>
<div class="seller-field"><label for="wallet-method">Send to</label><select id="wallet-method" name="method"><option value="gcash">GCash</option><option value="bank">Bank</option></select></div>
<div class="seller-field"><label for="wallet-number">GCash number or bank and account number</label><input id="wallet-number" name="account_number" maxlength="50" required></div>
<div class="seller-field"><label for="wallet-name">Account name</label><input id="wallet-name" name="account_name" maxlength="100" required></div>
<button class="seller-button" @disabled($balance <= 0)>Request withdrawal</button>
</form></div></section>
@endunless
<section class="seller-panel"><div class="seller-table-wrap"><table class="seller-table"><thead><tr><th>Seller / date</th><th>Amount</th><th>Destination</th><th>Status</th><th>Reference</th></tr></thead><tbody>
@forelse($withdrawals as $withdrawal)<tr>
<td>{{ $withdrawal->user?->name }}<span class="seller-cell-note">{{ \App\Services\DemoPayments::enabled() && ! $withdrawal->livemode ? 'Wallet payout' : ($withdrawal->livemode ? 'Live payout' : 'TEST — no real funds') }} · {{ $withdrawal->created_at->timezone('Asia/Manila')->format('M j, g:i A') }}</span></td>
<td>PHP {{ number_format($withdrawal->amount_minor / 100, 2) }}</td><td>{{ strtoupper($withdrawal->method) }} {{ $withdrawal->account_number }}<span class="seller-cell-note">{{ $withdrawal->account_name }}</span></td><td>{{ ucfirst($withdrawal->status) }}</td>
<td>@if($admin && $withdrawal->status === 'pending')
<form method="POST" action="{{ route('admin.seller-withdrawals.sent', $withdrawal) }}" class="seller-form">@csrf
<label for="seller-transfer-{{ $withdrawal->id }}">Actual transfer reference</label><input id="seller-transfer-{{ $withdrawal->id }}" name="reference" pattern="[A-Z0-9]{13}" minlength="13" maxlength="13" placeholder="REF20261115AB" required>
<label><input type="checkbox" name="transfer_sent" value="1" required> I verified the transfer was sent</label><button class="seller-button">Record sent transfer</button></form>
@else{{ $withdrawal->reference ?? 'Pending transfer' }}@endif</td></tr>
@empty<tr><td colspan="5">No withdrawal requests yet.</td></tr>@endforelse
</tbody></table></div>{{ $withdrawals->links() }}</section>
@endsection
