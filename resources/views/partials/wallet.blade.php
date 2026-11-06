{{--
    Wallet credits and cash-outs to GCash, for riders (Earnings) and logistics partners (Logistics Center > Earnings).
    Expects $wallet (App\Services\Wallet::summary), $cashouts and $cashOutRoute. The transfer is simulated.
--}}
@php
    $canCashOut = \App\Services\Wallet::ready() && $wallet['balance'] >= \App\Services\Wallet::MINIMUM;
    $maxCashOut = min($wallet['balance'], \App\Services\Wallet::MAXIMUM);
@endphp
<section class="wallet-panel" aria-labelledby="wallet-title">
    <header class="wallet-panel__header">
        <div>
            <h2 id="wallet-title"><i class="fas fa-wallet" aria-hidden="true"></i> Wallet</h2>
            <p>{{ $wallet['source'] }} are added to your wallet. Cash them out to GCash anytime.</p>
        </div>
        <span class="wallet-demo"><i class="fas fa-flask" aria-hidden="true"></i> Demo GCash: no real money is sent</span>
    </header>

    <div class="wallet-panel__body">
        <dl class="wallet-totals">
            <div class="wallet-totals__balance">
                <dt>Balance</dt>
                <dd>&#8369;{{ number_format($wallet['balance'], 2) }}</dd>
            </div>
            <div>
                <dt>Total credits</dt>
                <dd>&#8369;{{ number_format($wallet['credits'], 2) }}</dd>
            </div>
            <div>
                <dt>Cashed out</dt>
                <dd>&#8369;{{ number_format($wallet['cashed_out'], 2) }}</dd>
            </div>
        </dl>

        @if(! \App\Services\Wallet::ready())
            <p class="wallet-note">Cash-outs turn on after the latest database update (php artisan migrate).</p>
        @else
            <form method="POST" action="{{ route($cashOutRoute) }}" class="wallet-form" novalidate
                  data-confirm="Send this amount from your wallet to the GCash number you entered? This is a demo transfer: no real money is sent." data-confirm-title="Cash out to GCash?" data-confirm-button="Cash out">
                @csrf
                <div class="wallet-field">
                    <label for="wallet-amount">Amount (&#8369;)</label>
                    <input id="wallet-amount" name="amount" type="number" inputmode="decimal" step="0.01"
                           min="{{ \App\Services\Wallet::MINIMUM }}" max="{{ $maxCashOut }}" value="{{ old('amount') }}"
                           placeholder="{{ \App\Services\Wallet::MINIMUM }}" required @disabled(! $canCashOut)
                           aria-describedby="wallet-amount-help" @error('amount') aria-invalid="true" @enderror>
                    <span id="wallet-amount-help" class="wallet-help">&#8369;{{ number_format(\App\Services\Wallet::MINIMUM) }} to &#8369;{{ number_format($maxCashOut, 2) }}</span>
                    @error('amount')<span class="wallet-error" role="alert">{{ $message }}</span>@enderror
                </div>
                <div class="wallet-field">
                    <label for="wallet-number">GCash number</label>
                    <input id="wallet-number" name="gcash_number" type="tel" inputmode="numeric" autocomplete="tel-national"
                           maxlength="11" pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" value="{{ old('gcash_number', auth()->user()->phone) }}" required @disabled(! $canCashOut)
                           @error('gcash_number') aria-invalid="true" @enderror>
                    @error('gcash_number')<span class="wallet-error" role="alert">{{ $message }}</span>@enderror
                </div>
                <div class="wallet-field">
                    <label for="wallet-name">Name on the GCash account</label>
                    <input id="wallet-name" name="gcash_name" type="text" autocomplete="name" maxlength="100"
                           value="{{ old('gcash_name', auth()->user()->name) }}" required @disabled(! $canCashOut)
                           @error('gcash_name') aria-invalid="true" @enderror>
                    @error('gcash_name')<span class="wallet-error" role="alert">{{ $message }}</span>@enderror
                </div>
                <button type="submit" class="wallet-submit" @disabled(! $canCashOut)>
                    <i class="fas fa-paper-plane" aria-hidden="true"></i> Cash out to GCash
                </button>
                @unless($canCashOut)
                    <p class="wallet-note">You can cash out once your balance reaches &#8369;{{ number_format(\App\Services\Wallet::MINIMUM) }}.</p>
                @endunless
            </form>
        @endif
    </div>

    <div class="wallet-history">
        <h3>Cash-outs</h3>
        @forelse($cashouts as $cashout)
            <div class="wallet-history__row">
                <div>
                    <strong>&#8369;{{ number_format($cashout->amount, 2) }} to GCash {{ $cashout->masked_number }}</strong>
                    <span>{{ $cashout->gcash_name }} · {{ $cashout->reference }}</span>
                </div>
                <div class="wallet-history__meta">
                    <span class="wallet-status"><i class="fas fa-circle-check" aria-hidden="true"></i> Sent</span>
                    <time datetime="{{ $cashout->created_at?->toIso8601String() }}">{{ $cashout->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</time>
                </div>
            </div>
        @empty
            <p class="wallet-note">No cash-outs yet.</p>
        @endforelse
    </div>
</section>
