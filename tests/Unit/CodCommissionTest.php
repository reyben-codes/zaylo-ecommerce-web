<?php

namespace Tests\Unit;

use App\Services\Settlement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CodCommissionTest extends TestCase
{
    #[DataProvider('standardCodProducts')]
    public function test_standard_shipping_distribution_is_exact_for_required_product_prices(
        float $product,
        float $logistics,
        float $admin,
        float $seller,
    ): void {
        $split = Settlement::breakdown($product, 120);

        $this->assertSame(round($product + 120, 2), $split['total_distribution']);
        $this->assertSame(40.0, $split['rider_earnings']);
        $this->assertSame($logistics, $split['logistics_earnings']);
        $this->assertSame($admin, $split['admin_earnings']);
        $this->assertSame($seller, $split['seller_earnings']);
        $this->assertSame(round($product + 80, 2), round($split['total_distribution'] - $split['rider_earnings'], 2));
        $this->assertSame(
            round($product + 80, 2),
            round($split['logistics_earnings'] + $split['admin_earnings'] + $split['seller_earnings'], 2),
        );
    }

    /** @return array<string, array{float, float, float, float}> */
    public static function standardCodProducts(): array
    {
        return [
            'five hundred pesos' => [500.0, 90.0, 90.0, 400.0],
            'one thousand pesos' => [1000.0, 140.0, 140.0, 800.0],
            'two thousand five hundred pesos' => [2500.0, 290.0, 290.0, 2000.0],
            'ten thousand pesos' => [10000.0, 1040.0, 1040.0, 8000.0],
        ];
    }

    public function test_standard_cod_distribution_matches_the_business_rule(): void
    {
        $split = Settlement::breakdown(1000, 120);

        $this->assertSame(1120.0, $split['total_distribution']);
        $this->assertSame(40.0, $split['rider_earnings']);
        $this->assertSame(140.0, $split['logistics_earnings']);
        $this->assertSame(140.0, $split['admin_earnings']);
        $this->assertSame(800.0, $split['seller_earnings']);
        $this->assertSame(1080.0, $split['total_distribution'] - $split['rider_earnings']);
        $this->assertSame(
            1080.0,
            $split['logistics_earnings'] + $split['admin_earnings'] + $split['seller_earnings'],
        );
        $this->assertSame(
            $split['total_distribution'],
            $split['rider_earnings'] + $split['logistics_earnings'] + $split['admin_earnings'] + $split['seller_earnings'],
        );
    }

    public function test_centavo_remainder_stays_with_the_seller_so_nothing_is_lost(): void
    {
        $split = Settlement::breakdown(1000.01, 120);

        $this->assertSame(
            1120.01,
            $split['rider_earnings'] + $split['logistics_earnings'] + $split['admin_earnings'] + $split['seller_earnings'],
        );
        $this->assertSame(800.01, $split['seller_earnings']);
    }

    public function test_half_up_rounding_keeps_the_distribution_balanced(): void
    {
        $split = Settlement::breakdown(1000.05, 120);

        $this->assertSame(140.01, $split['logistics_earnings']);
        $this->assertSame(140.01, $split['admin_earnings']);
        $this->assertSame(800.03, $split['seller_earnings']);
        $this->assertSame(1120.05, round($split['rider_earnings'] + $split['logistics_earnings'] + $split['admin_earnings'] + $split['seller_earnings'], 2));
    }
}
