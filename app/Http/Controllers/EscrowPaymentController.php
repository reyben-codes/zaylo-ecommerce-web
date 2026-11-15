<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\EscrowPayments;
use App\Support\OrderParties;
use Illuminate\Http\Request;

class EscrowPaymentController extends Controller
{
    public function start(Request $request, Order $order)
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        $payment = EscrowPayments::forOrder($order);
        return view('buyer.gateway-checkout', ['order' => $order, 'testMode' => ! (bool) config('escrow.live') && ! ($payment && \App\Services\DemoPayments::isDemo($payment))]);
    }
    public function pay(Request $request, Order $order, EscrowPayments $payments)
    {
        abort_unless(OrderParties::for($order)['buyer'] === (int) $request->user()->id, 404);
        $url = $payments->checkout($order);
        return $request->expectsJson() ? response()->json(['checkout_url' => $url]) : redirect()->away($url);
    }

    public function returned(Order $order)
    {
        // No status mutations: only the signed webhook can mark a payment successful.
        return view('buyer.payment-return');
    }

    public function webhook(Request $request, EscrowPayments $payments)
    {
        abort_unless(EscrowPayments::validSignature($request->getContent(), $request->header('Paymongo-Signature', ''),
            (string) config('escrow.webhook_secret'), (bool) config('escrow.live')), 401);
        $payments->capture($request->json()->all());
        return response()->json(['received' => true]);
    }
}
