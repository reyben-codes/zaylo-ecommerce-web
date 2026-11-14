<?php

namespace App\Http\Controllers;

use App\Models\LogisticsPayout;
use App\Models\LogisticsProvider;
use App\Services\LogisticsPayouts;
use App\Services\Wallet;
use App\Services\RiderWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping-fee payouts: the admin sees what each logistics partner is owed and records payments; each partner
 * sees its own earnings, payouts and the parcels behind them.
 */
class LogisticsPayoutController extends Controller
{
    public function __construct(private readonly LogisticsPayouts $payouts) {}

    public function admin()
    {
        $rows = $this->payouts->rows();

        return view('admin.logistics-payouts', [
            'ready' => LogisticsPayouts::ready(),
            'percent' => LogisticsPayouts::partnerPercent(),
            'riderCommission' => RiderWallet::COMMISSION,
            'summary' => $this->payouts->summary($rows),
            'byProvider' => $this->payouts->byProvider($rows),
            'history' => $this->history(),
        ]);
    }

    public function pay(Request $request, LogisticsProvider $provider)
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:100']]);
        $payout = $this->payouts->pay($provider, $request->user(), $data['reference'] ?? null);

        return back()->with('status', $payout
            ? "Recorded a payout of ₱".number_format($payout->amount, 2)." to {$provider->name} for {$payout->parcels} delivered ".str('parcel')->plural($payout->parcels).'.'
            : "{$provider->name} has no unpaid delivered parcels.");
    }

    public function partner(Request $request, Wallet $wallet)
    {
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider, 404, 'No logistics company is linked to this account.');
        $rows = $this->payouts->rows($provider);

        return view('logistics.earnings', [
            'provider' => $provider,
            'ready' => LogisticsPayouts::ready(),
            'percent' => LogisticsPayouts::partnerPercent(),
            'riderCommission' => $provider->riderRate(),
            'summary' => $this->payouts->summary($rows),
            'parcels' => $rows->take(100),
            'history' => $this->history($provider),
            'wallet' => $wallet->summary($request->user()),
            'cashouts' => $wallet->history($request->user()),
        ]);
    }

    private function history(?LogisticsProvider $provider = null)
    {
        if (! Schema::hasTable('logistics_payouts')) {
            return collect();
        }

        return LogisticsPayout::with(['provider', 'payer'])
            ->when($provider, fn ($query) => $query->where('logistics_provider_id', $provider->id))
            ->latest('paid_at')->latest('id')->limit(50)->get();
    }
}
