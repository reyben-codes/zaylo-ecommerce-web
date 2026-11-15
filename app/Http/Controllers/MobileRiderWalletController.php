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
        $updates = app(\App\Services\AccountUpdates::class);
        $seen = $updates->payload($request->user())['through'];
        $payload = $this->payload($request->user());
        $updates->read($request->user(), 'wallet', $seen);
        return response()->json($payload);
    }

    public function received(Request $request, RiderWithdrawal $withdrawal): JsonResponse
    {
        $this->wallet->confirmReceived($withdrawal, $request->user());
        return response()->json(['message' => 'Salary receipt confirmed and logged.'] + $this->payload($request->user()));
    }

    public function withdraw(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'decimal:0,2'],
            // Older app builds type the account each time; newer ones send none and use the saved account.
            'gcash_number' => ['nullable', 'string', 'max:20'],
            'gcash_name' => ['nullable', 'string', 'max:100'],
        ]);
        $withdrawal = $this->wallet->withdraw($request->user(), (float) $data['amount'], $data['gcash_number'] ?? null, $data['gcash_name'] ?? null);

        return response()->json([
            'message' => 'Withdrawal requested. ₱'.number_format($withdrawal->amount, 2).' will be sent to GCash '.$withdrawal->formattedNumber().' once your logistics partner processes it.',
        ] + $this->payload($request->user()), 201);
    }

    /** Saves the GCash number and name withdrawals are sent to. */
    public function updateAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gcash_number' => ['required', 'string', 'max:20'],
            'gcash_name' => ['required', 'string', 'max:100'],
        ], [
            'gcash_number.required' => 'Enter your 11-digit GCash number starting with 09.',
            'gcash_name.required' => 'Enter the name on your GCash account.',
        ]);
        $this->wallet->saveAccount($request->user(), $data['gcash_number'], $data['gcash_name']);

        return response()->json(['message' => 'GCash account saved.'] + $this->payload($request->user()->fresh()));
    }

    private function payload(User $rider): array
    {
        return [
            'ready' => RiderWallet::ready(),
            ...$this->wallet->summary($rider),
            'commission_per_parcel' => RiderWallet::rateFor($rider),
            'min_withdrawal' => RiderWallet::MIN_WITHDRAWAL,
            // Where withdrawals go; "saved" is false when it is only the account used last.
            'gcash' => $this->wallet->account($rider),
            'commissions' => $this->wallet->commissions($rider)->map(fn ($commission) => [
                'id' => $commission->id,
                'amount' => $commission->amount,
                'leg' => $commission->leg ?? 'delivery',
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
            'note' => 'New parcel earnings use each leg’s road distance and parcel weight. Pickup earnings credit after scanned hub handover and logistics receipt; delivery earnings credit after proof of delivery. Request your daily salary from your available balance. Logistics releases it first, then you confirm Salary Received. Historical parcels retain their recorded rates.',
        ];
    }
}
