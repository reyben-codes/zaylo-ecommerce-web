<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use App\Services\DisputeService;
use App\Services\SellerQueues;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Returns and refunds in Seller Center: the seller follows each returned item from pickup or drop-off back to the
 * shop, confirms it arrived, and records the refund. The buyer is emailed and sees each step in the case.
 */
class SellerReturnController extends Controller
{
    private const FILTERS = ['action', 'in_progress', 'closed', 'all'];

    public function __construct(private SellerQueues $queues, private DisputeService $disputes) {}

    public function index(Request $request)
    {
        $filter = $request->validate(['status' => ['nullable', Rule::in(self::FILTERS)]])['status'] ?? null;
        $cases = $this->queues->returns($request->user())->with('order')->latest()->get();
        $groups = [
            'action' => $cases->filter(fn (Dispute $case) => $this->needsAction($case)),
            'in_progress' => $cases->filter(fn (Dispute $case) => ! $case->isClosed() && ! $this->needsAction($case)),
            'closed' => $cases->filter(fn (Dispute $case) => $case->isClosed() && ! $this->needsAction($case)),
            'all' => $cases,
        ];

        // Open on what needs action, or on everything when nothing does.
        $filter ??= $groups['action']->isNotEmpty() ? 'action' : 'all';

        return view('seller.returns', [
            'filter' => $filter,
            'counts' => array_map('count', $groups),
            'cases' => $groups[$filter]->values(),
            'tracksRefunds' => Dispute::tracksRefunds(),
        ]);
    }

    /** The returned item reached the shop. */
    public function receive(Request $request, Dispute $dispute)
    {
        $this->authorizeSeller($request, $dispute);
        if (! $dispute->canConfirmReceipt()) {
            throw ValidationException::withMessages(['return' => 'You can confirm the return once the courier has collected the item from the buyer.']);
        }

        $this->disputes->sellerStep($dispute, $request->user(), 'return_received', [
            'return_status' => 'received_by_seller',
            'return_received_at' => now(),
        ], 'I received the returned item.');

        return back()->with('status', 'Return received. Issue the refund when you have checked the item.');
    }

    /** Records the refund sent to the buyer and closes the case. */
    public function refund(Request $request, Dispute $dispute)
    {
        $this->authorizeSeller($request, $dispute);
        $maximum = $this->orderTotal($dispute->order);
        $data = $request->validate([
            'refund_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.$maximum],
            'refund_note' => ['nullable', 'string', 'max:500'],
        ], [
            'refund_amount.max' => 'The refund cannot be more than the order total of ₱'.number_format($maximum, 2).'.',
        ]);
        if (! $dispute->canRefund()) {
            throw ValidationException::withMessages(['refund_amount' => $dispute->hasReturnParcel()
                ? 'Confirm you received the returned item before refunding.'
                : 'This case cannot be refunded.']);
        }

        $amount = round((float) $data['refund_amount'], 2);
        $note = trim('Refund of ₱'.number_format($amount, 2).' issued. '.($data['refund_note'] ?? ''));
        $this->disputes->sellerStep($dispute, $request->user(), 'refunded', [
            'refund_amount' => $amount,
            'refunded_at' => now(),
            'return_status' => $dispute->hasReturnParcel() ? 'refunded' : $dispute->return_status,
            'status' => 'resolved',
            'resolution' => $dispute->resolution ?: 'The seller refunded ₱'.number_format($amount, 2).'.',
            'resolved_at' => $dispute->resolved_at ?? now(),
        ], $note);

        return back()->with('status', 'Refund of ₱'.number_format($amount, 2).' recorded. The buyer was told.');
    }

    private function needsAction(Dispute $case): bool
    {
        return $case->status === 'open' || (Dispute::tracksRefunds() && ($case->canConfirmReceipt() || $case->canRefund()));
    }

    private function authorizeSeller(Request $request, Dispute $dispute): void
    {
        abort_unless(Dispute::tracksRefunds(), 503, 'Refunds are being set up. Please try again later.');
        abort_unless($this->queues->returns($request->user())->whereKey($dispute->id)->exists(), 403);
    }

    private function orderTotal(?Order $order): float
    {
        if (! $order) {
            return 0.0;
        }

        return Order::usesLegacySchema() ? (float) $order->total : (int) $order->total_minor / 100;
    }
}
