<?php

namespace App\Http\Controllers;

use App\Models\EscrowPayment;
use App\Services\DemoPayments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class DemoPaymentController extends Controller
{
    public function show(EscrowPayment $escrow, DemoPayments $payments)
    {
        abort_unless(DemoPayments::enabled() && DemoPayments::isDemo($escrow), 404);
        abort_if($escrow->order->status === 'cancelled', 422, 'This order was cancelled.');
        return view('buyer.payment-authorization', ['payment' => $escrow,
            'authorizeUrl' => URL::temporarySignedRoute('payment.authorize', now()->addMinutes(10), ['escrow' => $escrow->id])]);
    }

    public function confirm(Request $request, EscrowPayment $escrow, DemoPayments $payments)
    {
        $decision = $request->validate(['decision' => ['required', 'in:approve,decline']])['decision'];
        $payments->authorize($escrow, $decision);
        return redirect()->to($payments->checkoutUrl($escrow))->with('payment_declined', $decision === 'decline');
    }
}
