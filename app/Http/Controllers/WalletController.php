<?php

namespace App\Http\Controllers;

use App\Models\WalletCashout;
use App\Services\Wallet;
use Illuminate\Http\Request;

/** Logistics partners cash out their wallet to GCash from Logistics Center > Earnings (simulated; see App\Services\Wallet). */
class WalletController extends Controller
{
    public function __construct(private readonly Wallet $wallet) {}

    public function cashOut(Request $request)
    {
        $data = $request->validate(Wallet::rules(), Wallet::messages());
        $cashout = $this->wallet->cashOut($request->user(), (float) $data['amount'], $data['gcash_number'], trim($data['gcash_name']));

        return back()->with('status', $this->sentMessage($cashout));
    }

    private function sentMessage(WalletCashout $cashout): string
    {
        return '₱'.number_format($cashout->amount, 2).' was sent to GCash '.$cashout->masked_number.' (demo transfer, reference '.$cashout->reference.').';
    }
}
