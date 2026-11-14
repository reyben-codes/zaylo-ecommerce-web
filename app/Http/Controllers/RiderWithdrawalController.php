<?php

namespace App\Http\Controllers;

use App\Models\LogisticsProvider;
use App\Models\RiderWithdrawal;
use App\Services\RiderWallet;
use Illuminate\Http\Request;

/**
 * The logistics partner's riders' GCash withdrawals: the partner sends each pending request by GCash, then records
 * its reference number, or rejects it with a reason, which returns the amount to the rider's wallet. The commissions
 * come out of the share of the shipping fee ZAYLO pays the partner.
 */
class RiderWithdrawalController extends Controller
{
    public function __construct(private readonly RiderWallet $wallet) {}

    public function index()
    {
        $ready = RiderWallet::ready();
        $provider = $this->provider();
        $query = fn () => RiderWithdrawal::with(['rider', 'processor'])->where('logistics_provider_id', $provider->id);

        return view('logistics.rider-withdrawals', [
            'provider' => $provider,
            'ready' => $ready,
            'commission' => $provider->riderRate(),
            'minimum' => RiderWallet::MIN_WITHDRAWAL,
            'pending' => $ready ? $query()->where('status', RiderWithdrawal::PENDING)->oldest('id')->get() : collect(),
            'history' => $ready ? $query()->where('status', '!=', RiderWithdrawal::PENDING)->latest('processed_at')->latest('id')->limit(50)->get() : collect(),
            'pendingTotal' => $ready ? (float) $query()->where('status', RiderWithdrawal::PENDING)->sum('amount') : 0.0,
            'paidTotal' => $ready ? (float) $query()->where('status', RiderWithdrawal::PAID)->sum('amount') : 0.0,
        ]);
    }

    public function pay(Request $request, RiderWithdrawal $withdrawal)
    {
        $this->ownWithdrawal($withdrawal);
        $data = $request->validate(['reference' => ['required', 'string', 'max:100']], [
            'reference.required' => 'Enter the GCash reference number of the transfer.',
        ]);
        $this->wallet->markPaid($withdrawal, $request->user(), $data['reference']);

        return back()->with('status', 'Recorded ₱'.number_format($withdrawal->amount, 2).' sent to '.($withdrawal->rider?->name ?? 'the rider').' on GCash.');
    }

    public function reject(Request $request, RiderWithdrawal $withdrawal)
    {
        $this->ownWithdrawal($withdrawal);
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:255']], [
            'rejection_reason.required' => 'Tell the rider why the withdrawal was rejected.',
        ]);
        $this->wallet->reject($withdrawal, $request->user(), $data['rejection_reason']);

        return back()->with('status', 'Rejected the withdrawal; ₱'.number_format($withdrawal->amount, 2).' is back in '.($withdrawal->rider?->name ?? 'the rider')."'s wallet.");
    }

    private function provider(): LogisticsProvider
    {
        $provider = auth()->user()->logisticsProvider;
        abort_unless($provider, 404, 'No logistics company is linked to this account.');

        return $provider;
    }

    /** Only the rider's own logistics partner handles a withdrawal. */
    private function ownWithdrawal(RiderWithdrawal $withdrawal): void
    {
        abort_unless((int) $withdrawal->logistics_provider_id === (int) $this->provider()->id, 403);
    }
}
