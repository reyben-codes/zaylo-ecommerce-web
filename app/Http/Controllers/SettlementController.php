<?php

namespace App\Http\Controllers;

use App\Models\LogisticsCodSettlement;
use App\Models\User;
use App\Services\CashHandovers;
use App\Services\LogisticsCodSettlements;
use App\Services\MarketplaceLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Money after delivery: riders hand their COD cash to the hub (Logistics Center), and each seller sees what ZAYLO owes
 * them for COD orders less what they owe ZAYLO for GCash orders (Seller Center). See App\Services\Settlement.
 */
class SettlementController extends Controller
{
    public function cash(Request $request, CashHandovers $handovers, LogisticsCodSettlements $settlements)
    {
        abort_unless(CashHandovers::ready(), 404);
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider, 404);

        return view('logistics.cash-handovers', [
            'owed' => $handovers->owedTo($provider),
            'history' => $handovers->history($provider),
            'settlements' => $settlements->forProvider($provider),
        ]);
    }

    public function settleCod(Request $request, LogisticsCodSettlement $settlement, LogisticsCodSettlements $settlements)
    {
        $data = $request->validate(['seller_reference' => ['required', 'string', 'max:100']]);
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider, 404);
        $settlements->settle($settlement, $provider, $request->user(), $data['seller_reference']);

        return back()->with('status', 'Seller transfer recorded. The seller allocation is paid.');
    }

    public function settleAdmin(Request $request, LogisticsCodSettlement $settlement, LogisticsCodSettlements $settlements)
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasColumn('logistics_cod_settlements', 'admin_reference'), 503, 'Run the latest payment migrations.');
        $data = $request->validate(['admin_reference' => ['required', 'string', 'max:100']]);
        $settlements->settleAdmin($settlement, $request->user(), $data['admin_reference']);

        return back()->with('status', 'Admin transfer recorded with its payment reference.');
    }

    public function recordCash(Request $request, User $rider, CashHandovers $handovers)
    {
        $data = $request->validate([
            'received' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['received.required' => 'Enter the cash you counted.']);
        $provider = $request->user()->logisticsProvider;
        abort_unless($provider, 404);
        $handover = $handovers->record($provider, $rider, (float) $data['received'], $data['note'] ?? null, $request->user());

        return back()->with('status', $handover->shortfall > 0
            ? 'Recorded ₱'.number_format($handover->received, 2)." from {$rider->name}: ₱".number_format($handover->shortfall, 2).' short, kept on record.'
            : 'Recorded ₱'.number_format($handover->received, 2)." from {$rider->name} for {$handover->parcels} COD ".str('parcel')->plural($handover->parcels).'.');
    }

    /** The seller's COD ledger; payments count as paid only after Logistics recorded the transfer. */
    public function seller(Request $request, LogisticsCodSettlements $settlements)
    {
        $input = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', Rule::in([LogisticsCodSettlement::AWAITING_SELLER_TRANSFER, LogisticsCodSettlement::SETTLED])],
        ]);
        $zone = MarketplaceLedger::TIMEZONE;
        $to = isset($input['to']) ? Carbon::createFromFormat('Y-m-d', $input['to'], $zone)->endOfDay() : now($zone)->endOfDay();
        $from = isset($input['from']) ? Carbon::createFromFormat('Y-m-d', $input['from'], $zone)->startOfDay() : $to->copy()->subDays(89)->startOfDay();
        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'The start date must be on or before the end date.']);
        }

        $rows = $settlements->forSeller($request->user(), $from->copy()->utc(), $to->copy()->utc(), $input['status'] ?? null);

        return view('seller.settlement', [
            'ready' => LogisticsCodSettlements::ready(),
            'rows' => $rows,
            'summary' => $settlements->sellerSummary($rows),
            'prepaid' => app(\App\Services\Settlement::class)->prepaidForSeller($request->user(), $from->copy()->utc(), $to->copy()->utc()),
            'status' => $input['status'] ?? null,
            'statuses' => [
                LogisticsCodSettlement::AWAITING_SELLER_TRANSFER => 'Pending settlement',
                LogisticsCodSettlement::SETTLED => 'Paid',
            ],
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $from->format('M j, Y').' – '.$to->format('M j, Y')],
        ]);
    }
}
