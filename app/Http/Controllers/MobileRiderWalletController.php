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
            'note' => 'Your logistics partner currently offers ₱'.number_format(RiderWallet::rateFor($rider), 2).' per delivery for new orders. Each order keeps its agreed rate. COD earnings are credited after cash collection is confirmed. Withdraw ₱'
                .number_format(RiderWallet::MIN_WITHDRAWAL, 2).' or more to GCash; your logistics partner sends the payment.',
        ];
    }
}
