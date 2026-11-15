<?php

namespace Tests\Unit;

use App\Services\EscrowPayments;
use App\Services\RiderLegEarnings;
use App\Support\TransferReference;
use PHPUnit\Framework\TestCase;

class SettlementRevisionPolicyTest extends TestCase
{
    public function test_handover_references_are_exactly_thirteen_uppercase_alphanumerics(): void
    {
        $this->assertSame(1, preg_match(TransferReference::PATTERN, 'REF20261115AB'));
        foreach (['ref20261115AB', 'REF-20261115A', 'REF20261115A', 'REF20261115ABC', 'REF20261115AB ', "REF20261115AB\n"] as $reference) {
            $this->assertSame(0, preg_match(TransferReference::PATTERN, $reference), $reference);
        }
    }

    public function test_earnings_change_with_each_legs_distance_and_parcel_weight(): void
    {
        $rates = ['base_per_leg' => 10, 'per_km' => 2, 'per_kg' => 3, 'shipping_budget_percent' => 80];
        $quote = RiderLegEarnings::calculate(2, 5, 1, 120, $rates);
        $this->assertEquals(17, $quote['pickup_amount']);
        $this->assertEquals(23, $quote['delivery_amount']);
        $this->assertGreaterThan($quote['total'], RiderLegEarnings::calculate(2, 5, 3, 120, $rates)['total']);
        $capped = RiderLegEarnings::calculate(100, 300, 50, 120, $rates);
        $this->assertEquals(96, $capped['total']);
        $this->assertEquals($capped['total'], $capped['pickup_amount'] + $capped['delivery_amount']);
        $this->assertLessThan($capped['delivery_amount'], $capped['pickup_amount']);
    }

    public function test_unknown_or_negative_weight_cannot_become_a_fake_fixed_commission(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RiderLegEarnings::calculate(1, 2, 0, 120, ['base_per_leg' => 10, 'per_km' => 2, 'per_kg' => 3, 'shipping_budget_percent' => 80]);
    }

    public function test_webhook_requires_the_correct_mode_raw_body_secret_and_recent_timestamp(): void
    {
        $body = '{"data":{"type":"checkout_session.payment.paid"}}';
        $now = 1000;
        $header = 't=1000,te='.hash_hmac('sha256', '1000.'.$body, 'secret').',li=';
        $this->assertTrue(EscrowPayments::validSignature($body, $header, 'secret', false, $now));
        $this->assertFalse(EscrowPayments::validSignature($body, $header, 'secret', true, $now));
        $this->assertFalse(EscrowPayments::validSignature($body.' ', $header, 'secret', false, $now));
        $this->assertFalse(EscrowPayments::validSignature($body, $header, 'other', false, $now));
        $this->assertFalse(EscrowPayments::validSignature($body, $header, 'secret', false, $now + 301));
    }
}
