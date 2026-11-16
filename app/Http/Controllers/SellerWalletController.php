<?php

namespace App\Http\Controllers;

use App\Models\SellerWithdrawal;
use App\Services\SellerWallet;
use Illuminate\Http\Request;

class SellerWalletController extends Controller
{
    public function index(Request $request, SellerWallet $wallet)
    {
        $admin = $request->user()->hasRole('admin');
        abort_unless($admin || $request->user()->hasRole('seller'), 403);
        return view('seller.wallet', ['admin' => $admin, 'balance' => $admin ? 0 : $wallet->balance($request->user()),
            'escrows' => $admin ? \App\Models\EscrowPayment::with('order')->latest('id')->limit(50)->get() : collect(),
            'withdrawals' => SellerWithdrawal::with('user')->when(! $admin, fn ($q) => $q->where('user_id', $request->user()->id))->latest('id')->paginate(30)]);
    }

    public function request(Request $request, SellerWallet $wallet)
    {
        $data = $request->validate(SellerWallet::requestRules(), SellerWallet::requestMessages());
        $wallet->request($request->user(), $data);
        return back()->with('status', 'Withdrawal requested. It stays pending until the transfer is recorded.');
    }

    public function sent(Request $request, SellerWithdrawal $withdrawal, SellerWallet $wallet)
    {
        $data = $request->validate(['reference' => \App\Support\TransferReference::rules(), 'transfer_sent' => ['accepted']]);
        $wallet->sent($withdrawal, $request->user(), $data['reference']);
        return back()->with('status', 'Seller payout recorded.');
    }
}
