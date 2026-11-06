<?php

namespace App\Http\Controllers;

use App\Models\RiderCommission;
use App\Models\RiderWithdrawal;
use App\Models\User;
use App\Services\RiderDeliveries;
use App\Services\RiderWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The rider app's wallet: the commission earned for each delivered parcel, and withdrawals to GCash, which the
 * rider's logistics partner sends and records (App\Services\RiderWallet).
 */
class MobileRiderWalletController extends Controller
{
    public function __construct(private readonly RiderWallet $wallet) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user()));
    }

    public function withdraw(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2'],
            'gcash_number' => ['required', 'string', 'max:20'],
            'gcash_name' => ['required', 'string', 'max:100'],
        ]);
        $withdrawal = $this->wallet->withdraw($request->user(), (float) $data['amount'], $data['gcash_number'], $data['gcash_name']);

        return response()->json([
            'message' => 'Withdrawal requested. ₱'.number_format($withdrawal->amount, 2).' will be sent to GCash '.$withdrawal->formattedNumber().' once your logistics partner processes it.',
        ] + $this->payload($request->user()), 201);
    }

    private function payload(User $rider): array
    {
        return [
            'ready' => RiderWallet::ready(),
            ...$this->wallet->summary($rider),
            'commission_per_parcel' => RiderWallet::COMMISSION,
            'min_withdrawal' => RiderWallet::MIN_WITHDRAWAL,
            'gcash' => $this->wallet->lastAccount($rider),
            'commissions' => $this->wallet->commissions($rider)->map(fn (RiderCommission $commission) => [
                'id' => $commission->id,
                'amount' => $commission->amount,
                'tracking_number' => $commission->shipment ? RiderDeliveries::labelCode($commission->shipment) : null,
                'created_at' => $commission->created_at?->toIso8601String(),
            ])->values(),
            'withdrawals' => $this->wallet->withdrawals($rider)->map(fn (RiderWithdrawal $withdrawal) => [
                'id' => $withdrawal->id,
                'amount' => $withdrawal->amount,
                'gcash_number' => $withdrawal->formattedNumber(),
                'gcash_name' => $withdrawal->gcash_name,
                'status' => $withdrawal->status,
                'status_label' => $withdrawal->statusLabel(),
                'reference' => $withdrawal->reference,
                'rejection_reason' => $withdrawal->rejection_reason,
                'created_at' => $withdrawal->created_at?->toIso8601String(),
                'processed_at' => $withdrawal->processed_at?->toIso8601String(),
            ])->values(),
            'note' => 'You earn ₱'.number_format(RiderWallet::COMMISSION, 2).' for every parcel you deliver. Withdraw ₱'
                .number_format(RiderWallet::MIN_WITHDRAWAL, 2).' or more to GCash; your logistics partner sends it, usually within 1–2 business days.',
        ];
    }
}
