<?php

namespace App\Http\Controllers;

use App\Models\WalletCashout;
use App\Services\Wallet;
use Illuminate\Http\Request;

/** Logistics partners request cash-outs from Earnings; Admin records the actual transfer. */
class WalletController extends Controller
{
    public function __construct(private readonly Wallet $wallet) {}

    public function cashOut(Request $request)
    {
        $data = $request->validate(Wallet::rules(), Wallet::messages());
        $cashout = $this->wallet->cashOut($request->user(), (float) $data['amount'], $data['gcash_number'], trim($data['gcash_name']));

        return back()->with('status', $this->sentMessage($cashout));
    }

    private function sentMessage(\App\Models\SellerWithdrawal $cashout): string
    {
        return 'PHP '.number_format($cashout->amount, 2).' requested for GCash '.$cashout->masked_number.'. The payout stays pending until Admin records the transfer.';
    }
}
