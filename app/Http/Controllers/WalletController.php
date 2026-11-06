<?php

namespace App\Http\Controllers;

use App\Models\WalletCashout;
use App\Services\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Riders' and logistics partners' wallet: the balance and cash-outs to GCash, on the website (Logistics Center and
 * rider Earnings) and in the rider app. The GCash transfer is simulated; see App\Services\Wallet.
 */
class WalletController extends Controller
{
    public function __construct(private readonly Wallet $wallet) {}

    public function cashOut(Request $request)
    {
        $data = $request->validate(Wallet::rules(), Wallet::messages());
        $cashout = $this->wallet->cashOut($request->user(), (float) $data['amount'], $data['gcash_number'], trim($data['gcash_name']));

        return back()->with('status', $this->sentMessage($cashout));
    }

    /** The rider app's wallet card: balance, limits and recent cash-outs. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($this->payload($user) + ['history' => $this->historyRows($user)]);
    }

    public function mobileCashOut(Request $request): JsonResponse
    {
        $data = $request->validate(Wallet::rules(), Wallet::messages());
        $user = $request->user();
        $cashout = $this->wallet->cashOut($user, (float) $data['amount'], $data['gcash_number'], trim($data['gcash_name']));

        return response()->json([
            'message' => $this->sentMessage($cashout),
            'cashout' => $this->row($cashout),
            'history' => $this->historyRows($user),
        ] + $this->payload($user), 201);
    }

    private function historyRows($user): array
    {
        return $this->wallet->history($user)->map(fn (WalletCashout $cashout) => $this->row($cashout))->values()->all();
    }

    private function payload($user): array
    {
        $summary = $this->wallet->summary($user);

        return [
            'available' => Wallet::ready(),
            'balance' => $summary['balance'],
            'credits' => $summary['credits'],
            'cashed_out' => $summary['cashed_out'],
            'source' => $summary['source'],
            'minimum' => Wallet::MINIMUM,
            'maximum' => Wallet::MAXIMUM,
            'demo_note' => 'GCash transfers are simulated in this version: no real money is sent.',
        ];
    }

    private function row(WalletCashout $cashout): array
    {
        return [
            'id' => $cashout->id,
            'amount' => $cashout->amount,
            'gcash_number' => $cashout->masked_number,
            'gcash_name' => $cashout->gcash_name,
            'reference' => $cashout->reference,
            'status' => $cashout->status,
            'created_at' => $cashout->created_at?->toIso8601String(),
        ];
    }

    private function sentMessage(WalletCashout $cashout): string
    {
        return '₱'.number_format($cashout->amount, 2).' was sent to GCash '.$cashout->masked_number.' (demo transfer, reference '.$cashout->reference.').';
    }
}
