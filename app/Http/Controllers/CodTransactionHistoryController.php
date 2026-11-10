<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Services\CodFinancialHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read-only, role-authorized audit trail for a COD parcel's financial lifecycle. */
class CodTransactionHistoryController extends Controller
{
    public function show(Request $request, Shipment $shipment, CodFinancialHistory $history): JsonResponse
    {
        $entries = $history->forShipment($shipment, $request->user());

        return response()->json(['transactions' => $entries->map(fn ($entry) => [
            'type' => $entry->type,
            'status' => $entry->status,
            'amount' => $entry->amount,
            'from' => $entry->fromUser?->name,
            'to' => $entry->toUser?->name,
            'recorded_by' => $entry->recorder?->name,
            'occurred_at' => $entry->occurred_at?->toIso8601String(),
            'metadata' => $entry->metadata,
        ])->values()]);
    }
}
