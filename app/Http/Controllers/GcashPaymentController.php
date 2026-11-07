<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\GcashPayments;
use App\Support\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GCash paid straight to the seller (see App\Services\GcashPayments): the buyer enters the reference number, the
 * seller checks it before confirming the order, and the seller keeps the GCash number buyers pay.
 */
class GcashPaymentController extends Controller
{
    public function __construct(private readonly GcashPayments $gcash) {}

    public function submit(Request $request, Order $order)
    {
        $data = $request->validate(['gcash_reference' => ['required', 'string', 'max:25']], ['gcash_reference.required' => 'Enter the reference number from your GCash receipt.']);
        $this->gcash->submit($order, $request->user(), $data['gcash_reference']);

        return back()->with('status', 'Reference sent. The seller confirms your order once they find the payment in their GCash.');
    }

    public function mobileSubmit(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate(['gcash_reference' => ['required', 'string', 'max:25']], ['gcash_reference.required' => 'Enter the reference number from your GCash receipt.']);
        $this->gcash->submit($order, $request->user(), $data['gcash_reference']);

        return response()->json([
            'message' => 'Reference sent. The seller confirms your order once they find the payment in their GCash.',
            'gcash' => GcashPayments::forBuyer($order->fresh(['payment'])),
        ]);
    }

    /** The seller found the payment in their GCash app, or could not (the buyer is told why and can enter it again). */
    public function verify(Request $request, Order $order)
    {
        abort_unless((int) $order->seller_id === (int) Shop::idForSeller($request->user()), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['received', 'not_found'])],
            'note' => ['nullable', 'required_if:decision,not_found', 'string', 'min:5', 'max:255'],
        ], ['note.required_if' => 'Tell the buyer what you found (for example, a different amount or no payment).']);
        $received = $data['decision'] === 'received';
        $this->gcash->verify($order->loadMissing('payment'), $request->user(), $received, $data['note'] ?? null);

        return back()->with('status', $received
            ? "GCash payment for {$order->order_number} marked received. You can confirm the order now."
            : "The buyer was asked to check the reference for {$order->order_number}.");
    }

    /** The seller's GCash account, shown to buyers who pay by GCash. */
    public function account(Request $request)
    {
        abort_unless(GcashPayments::ready(), 404);
        $data = $request->validate([
            'gcash_number' => ['required', 'string', 'regex:/^09[0-9]{9}$/D'],
            'gcash_name' => ['required', 'string', 'min:2', 'max:100'],
        ], ['gcash_number.regex' => 'Enter the 11-digit GCash mobile number, starting with 09.']);
        $request->user()->forceFill(['gcash_number' => $data['gcash_number'], 'gcash_name' => trim($data['gcash_name'])])->save();

        return back()->with('status', 'GCash account saved. Buyers who pay by GCash send the money there.');
    }
}
