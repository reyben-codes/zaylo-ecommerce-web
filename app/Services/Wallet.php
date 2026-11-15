<?php

namespace App\Services;

use App\Models\LogisticsPayout;
use App\Models\User;
use App\Models\WalletCashout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics partners' wallet, and cashing it out to GCash. (Riders have their own wallet: App\Services\RiderWallet.)
 *
 * Each payout ZAYLO records for a partner credits the partner's own share of it, that is, the payout less its riders'
 * commissions, which the partner sends to its riders when they withdraw. Credits minus what was already cashed out is
 * the balance. Escrow allocations add the partner's share with rider obligations reserved. Cash-outs create
 * pending withdrawal requests; an actual transfer reference is required before Admin marks them paid.
 */
class Wallet
{
    public const MINIMUM = 100;

    public const MAXIMUM = 50000;

    public static function ready(): bool
    {
        return Schema::hasTable('wallet_cashouts');
    }

    /** Logistics partners with a company have this wallet. */
    public static function supports(?User $user): bool
    {
        return $user !== null && $user->hasRole('logistics') && $user->logisticsProvider !== null;
    }

    /** @return array{credits: float, cashed_out: float, balance: float, source: string} */
    public function summary(User $user): array
    {
        $credits = $this->credits($user);
        $cashedOut = self::ready() ? round((float) WalletCashout::where('user_id', $user->id)->sum('amount'), 2) : 0.0;
        if (Schema::hasTable('seller_withdrawals')) $cashedOut += \App\Models\SellerWithdrawal::where('user_id', $user->id)->where('livemode', (bool) config('escrow.live'))->where('status', '!=', 'rejected')->sum('amount_minor') / 100;

        return [
            'credits' => $credits,
            'cashed_out' => $cashedOut,
            'balance' => max(0.0, round($credits - $cashedOut, 2)),
            'source' => "Your company's share of each payout ZAYLO records (after your riders' commissions)",
        ];
    }

    /** The latest cash-outs, newest first. */
    public function history(User $user, int $limit = 20): Collection
    {
        $legacy = self::ready()
            ? WalletCashout::where('user_id', $user->id)->latest()->latest('id')->limit($limit)->get()
            : collect();
        $current = Schema::hasTable('seller_withdrawals') ? \App\Models\SellerWithdrawal::where('user_id', $user->id)->latest('id')->limit($limit)->get() : collect();
        return $legacy->concat($current)->sortByDesc('created_at')->take($limit)->values();
    }

    /** Reserves a requested GCash withdrawal; this method does not send money. */
    public function cashOut(User $user, float $amount, string $number, string $name): \App\Models\SellerWithdrawal
    {
        abort_unless(self::ready() && self::supports($user), 404);

        return app(SellerWallet::class)->request($user, ['amount' => $amount, 'method' => 'gcash', 'account_number' => $number, 'account_name' => $name]);
    }

    /** Validation for the cash-out form and the app. */
    public static function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:'.self::MINIMUM, 'max:'.self::MAXIMUM, 'decimal:0,2'],
            'gcash_number' => ['required', 'string', 'regex:/^09[0-9]{9}$/D'],
            'gcash_name' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    public static function messages(): array
    {
        return [
            'amount.min' => 'The smallest cash-out is ₱'.self::MINIMUM.'.',
            'amount.max' => 'You can cash out up to ₱'.number_format(self::MAXIMUM).' at a time.',
            'gcash_number.regex' => 'Enter the 11-digit GCash mobile number, starting with 09.',
            'gcash_name.required' => 'Enter the name on the GCash account.',
        ];
    }

    private function credits(User $user): float
    {
        $provider = $user->logisticsProvider;
        if (! $provider || ! Schema::hasTable('logistics_payouts')) {
            return 0.0;
        }
        $payouts = LogisticsPayout::where('logistics_provider_id', $provider->id);
        $riders = Schema::hasColumn('logistics_payouts', 'rider_commissions') ? (float) (clone $payouts)->sum('rider_commissions') : 0.0;

        $escrow = 0;
        $reserved = 0;
        if (Schema::hasTable('settlement_wallet_entries')) {
            $escrow = DB::table('settlement_wallet_entries')->join('escrow_payments', 'escrow_payments.id', '=', 'settlement_wallet_entries.escrow_payment_id')
                ->where('user_id', $user->id)->where('kind', 'logistics')->where('escrow_payments.livemode', (bool) config('escrow.live'))->sum('settlement_wallet_entries.amount_minor') / 100;
            $orders = \App\Models\EscrowPayment::where('status', 'released')->where('livemode', (bool) config('escrow.live'))->pluck('order_id');
            $parcels = \App\Models\Shipment::where('logistics_provider_id', $provider->id)
                ->when(\App\Models\Order::usesLegacySchema(), fn ($q) => $q->whereIn('order_id', $orders), fn ($q) => $q->whereHas('sellerOrder', fn ($so) => $so->whereIn('order_id', $orders)))->get();
            $reserved = $parcels->sum(fn ($s) => $s->rider_fee_quote['total'] ?? app(Settlement::class)->forShipment($s)['rider_earnings']);
        }
        return max(0.0, round((float) $payouts->sum('amount') - $riders + $escrow - $reserved, 2));
    }

}
