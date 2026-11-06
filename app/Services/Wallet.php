<?php

namespace App\Services;

use App\Models\LogisticsPayout;
use App\Models\User;
use App\Models\WalletCashout;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logistics partners' wallet, and cashing it out to GCash. (Riders have their own wallet: App\Services\RiderWallet.)
 *
 * Each payout ZAYLO records for a partner credits the partner's own share of it, that is, the payout less its riders'
 * commissions, which the partner sends to its riders when they withdraw. Credits minus what was already cashed out is
 * the balance. The GCash transfer is a stand-in for the real GCash API (which needs a merchant account): it is
 * recorded at once with a demo reference and no money moves.
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
        return self::ready()
            ? WalletCashout::where('user_id', $user->id)->latest()->latest('id')->limit($limit)->get()
            : collect();
    }

    /** Sends part of the balance to a GCash number (simulated). The balance is read again under a lock, so a double submit cannot overdraw it. */
    public function cashOut(User $user, float $amount, string $number, string $name): WalletCashout
    {
        abort_unless(self::ready() && self::supports($user), 404);

        return DB::transaction(function () use ($user, $amount, $number, $name) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $balance = $this->summary($user)['balance'];
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'You can cash out up to ₱'.number_format($balance, 2).', your wallet balance.']);
            }

            return WalletCashout::create([
                'user_id' => $user->id,
                'amount' => round($amount, 2),
                'gcash_number' => $number,
                'gcash_name' => $name,
                'reference' => $this->reference(),
                'status' => 'sent',
            ]);
        });
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

        return max(0.0, round((float) $payouts->sum('amount') - $riders, 2));
    }

    private function reference(): string
    {
        do {
            $reference = 'GCASH-DEMO-'.Str::upper(Str::random(10));
        } while (WalletCashout::where('reference', $reference)->exists());

        return $reference;
    }
}
