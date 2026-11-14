<?php

namespace Tests\Unit;

use App\Services\Settlement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeliveryPaymentPolicyTest extends TestCase
{
    public static function fundingCases(): array
    {
        return [
            'buyer pays' => [120.0, 0.0, 0.0, 40.0, 800.0, 100.0, 180.0, 1120.0, 'buyer'],
            'seller voucher' => [0.0, 0.0, 120.0, 40.0, 680.0, 100.0, 180.0, 1000.0, 'seller'],
            'admin voucher' => [0.0, 120.0, 0.0, 40.0, 800.0, -20.0, 180.0, 1000.0, 'admin'],
            'custom rider rate' => [120.0, 0.0, 0.0, 60.0, 800.0, 100.0, 160.0, 1120.0, 'buyer'],
        ];
    }

    #[DataProvider('fundingCases')]
    public function test_each_funding_case_balances_without_deducting_shipping_twice(
        float $buyerShipping, float $adminSubsidy, float $sellerSubsidy, float $rate,
        float $seller, float $admin, float $logistics, float $total, string $payer,
    ): void {
        $split = Settlement::deliveryBreakdown(1000, $buyerShipping, $adminSubsidy, $sellerSubsidy, $rate);
        $this->assertSame(100.0, $split['admin_product_commission']);
        $this->assertSame(100.0, $split['logistics_product_commission']);
        $this->assertSame($seller, $split['seller_earnings']);
        $this->assertSame($admin, $split['admin_earnings']);
        $this->assertSame($logistics, $split['logistics_earnings']);
        $this->assertSame(220.0, $split['logistics_gross_earnings']);
        $this->assertSame($payer, $split['shipping_funded_by']);
        $this->assertSame($total, $split['total_distribution']);
        $this->assertSame($total, round($seller + $admin + $logistics + $split['rider_earnings'], 2));
    }

    public function test_centavos_and_admin_subsidies_remain_balanced(): void
    {
        foreach ([0.0, 0.05, 1000.01, 1000.05, 10000.99] as $price) {
            $split = Settlement::deliveryBreakdown($price, 0, 120, riderRate: 40.55);
            $this->assertSame($price, round($split['seller_earnings'] + $split['admin_earnings'] + $split['logistics_earnings'] + $split['rider_earnings'], 2));
        }
    }
}
