<?php

namespace App\Services;

use App\Models\SellerWithdrawal;
use App\Models\User;
use App\Support\TransferReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SellerWallet
{
    public function balance(User $seller): float
    {
        if (! Schema::hasTable('settlement_wallet_entries')) return 0;
        $earned = DB::table('settlement_wallet_entries')->join('escrow_payments', 'escrow_payments.id', '=', 'settlement_wallet_entries.escrow_payment_id')
            ->where('user_id', $seller->id)->where('kind', 'seller')->where('escrow_payments.livemode', (bool) config('escrow.live'))->sum('settlement_wallet_entries.amount_minor');
        $reserved = SellerWithdrawal::where('user_id', $seller->id)->where('livemode', (bool) config('escrow.live'))->where('status', '!=', 'rejected')->sum('amount_minor');
        return round(($earned - $reserved) / 100, 2);
    }

    public function request(User $seller, array $data): SellerWithdrawal
    {
        abort_unless($seller->hasRole('seller') || $seller->hasRole('logistics'), 403);
        return DB::transaction(function () use ($seller, $data) {
            User::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $minor = (int) round($data['amount'] * 100);
            $balance = $seller->hasRole('logistics') ? app(Wallet::class)->summary($seller)['balance'] : $this->balance($seller);
            if ($minor < 1 || $minor > (int) round($balance * 100)) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount within your available seller balance.']);
            }
            $withdrawal = SellerWithdrawal::create(['user_id' => $seller->id, 'amount_minor' => $minor,
                'method' => $data['method'], 'account_number' => $data['account_number'], 'account_name' => $data['account_name'], 'livemode' => (bool) config('escrow.live')]);
            app(AccountUpdates::class)->admins('seller-withdrawal-'.$withdrawal->id, 'Seller withdrawal requested',
                $seller->name.' requested PHP '.number_format($minor / 100, 2).' from their seller wallet.');
            return $withdrawal;
        });
    }

    public function sent(SellerWithdrawal $withdrawal, User $admin, string $reference): void
    {
        abort_unless($admin->hasRole('admin'), 403);
        abort_if(DemoPayments::enabled() && $withdrawal->livemode, 409, 'Live payouts are unavailable in this environment.');
        TransferReference::check($reference);
        DB::transaction(function () use ($withdrawal, $admin, $reference) {
            $locked = SellerWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') throw ValidationException::withMessages(['withdrawal' => 'This request has already been processed.']);
            if (SellerWithdrawal::where('reference', $reference)->exists()) throw ValidationException::withMessages(['reference' => 'This reference is already recorded for another transfer.']);
            // This action records a verified manual transfer. It never simulates a gateway payout.
            $locked->update(['status' => 'paid', 'reference' => $reference, 'processed_by' => $admin->id, 'processed_at' => now()]);
            $body = ($locked->livemode || DemoPayments::enabled() ? '' : 'TEST payout record; no real funds moved. ').'PHP '.number_format($locked->amount_minor / 100, 2).' allocated to your '.$locked->method.' account. Ref. '.$reference.'.';
            app(AccountUpdates::class)->send($locked->user_id, 'seller-withdrawal-paid-'.$locked->id, 'wallet', 'Seller payout settled', $body);
            app(AccountUpdates::class)->admins('seller-withdrawal-paid-'.$locked->id, 'Seller payout settled', $body);
            app(ActivityLogger::class)->record('admin.seller_withdrawal_sent', 'settlement', 'Recorded seller wallet transfer.', $admin, $locked, ['reference' => $reference, 'amount_minor' => $locked->amount_minor]);
        });
    }
}
