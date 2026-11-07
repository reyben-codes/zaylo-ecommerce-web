<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CashHandovers;
use App\Services\MarketplaceLedger;
use App\Services\Settlement;
use Illuminate\Http\Request;

/**
 * Money after delivery: riders hand their COD cash to the hub (Logistics Center), and each seller sees what ZAYLO owes
 * them for COD orders less what they owe ZAYLO for GCash orders (Seller Center). See App\Services\Settlement.
 */
class SettlementController extends Controller
{
    public function cash(Request $request, CashHandovers $handovers)
    {
        abort_unless(CashHandovers::ready(), 404);
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider, 404);

        return view('logistics.cash-handovers', [
            'owed' => $handovers->owedTo($provider),
            'history' => $handovers->history($provider),
        ]);
    }

    public function recordCash(Request $request, User $rider, CashHandovers $handovers)
    {
        $data = $request->validate([
            'received' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['received.required' => 'Enter the cash you counted.']);
        $handover = $handovers->record($request->user()->logisticsProvider, $rider, (float) $data['received'], $data['note'] ?? null, $request->user());

        return back()->with('status', $handover->shortfall > 0
            ? "Recorded ₱".number_format($handover->received, 2)." from {$rider->name}: ₱".number_format($handover->shortfall, 2).' short, kept on record.'
            : "Recorded ₱".number_format($handover->received, 2)." from {$rider->name} for {$handover->parcels} COD ".str('parcel')->plural($handover->parcels).'.');
    }

    public function seller(Request $request, Settlement $settlement)
    {
        return view('seller.settlement', $settlement->forSeller($request->user()) + [
            'commissionPercent' => (int) round(MarketplaceLedger::rate() * 100),
        ]);
    }
}
