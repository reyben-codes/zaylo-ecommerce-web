<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Server-side COD distribution and seller settlement.
 *
 * Amounts are converted to centavos before they are split, so floating point
 * math cannot introduce a cash discrepancy. A standard ₱120 shipment is split
 * ₱40 each to rider, logistics, and ZAYLO; product value is 10% logistics,
 * 10% ZAYLO, and the centavo remainder to the seller.
 */
class Settlement
{
    public const SHIPPING_FEE_MINOR = 12000;

    public const RIDER_SHIPPING_MINOR = 4000;

    public const LOGISTICS_SHIPPING_MINOR = 4000;

    public const PRODUCT_COMMISSION_BPS = 1000;

    /** Orders that count once the buyer has them. */
    public const COMPLETE = ['delivered', 'completed'];

    /**
     * Builds the full financial split from trusted persisted prices only.
     *
     * The residual product centavo is assigned to the seller. This guarantees
     * that the shares add up to the collected total for every valid amount.
     *
     * @return array<string, float>
     */
    public static function breakdown(float $productPrice, float $shippingFee, float $platformShippingSubsidy = 0): array
    {
        $product = max(0, self::centavos($productPrice));
        $shippingPaid = max(0, self::centavos($shippingFee));
        $shippingSubsidy = max(0, self::centavos($platformShippingSubsidy));
        $shipping = $shippingPaid + $shippingSubsidy;

        $adminProduct = self::percentage($product, self::PRODUCT_COMMISSION_BPS);
        $logisticsProduct = self::percentage($product, self::PRODUCT_COMMISSION_BPS);
        $seller = $product - $adminProduct - $logisticsProduct;

        // The normal charge is ₱120. The residual branch keeps old orders with
        // a different stored shipping amount balanced instead of inventing cash.
        $rider = min(self::RIDER_SHIPPING_MINOR, $shipping);
        $logisticsShipping = min(self::LOGISTICS_SHIPPING_MINOR, max(0, $shipping - $rider));
        $adminShipping = $shipping - $rider - $logisticsShipping;

        $admin = $adminProduct + $adminShipping;
        $logistics = $logisticsProduct + $logisticsShipping;

        return [
            'product_price' => self::pesos($product),
            'shipping' => self::pesos($shipping),
            'shipping_paid_by_buyer' => self::pesos($shippingPaid),
            'free_shipping_by_zaylo' => self::pesos($shippingSubsidy),
            'total_distribution' => self::pesos($product + $shipping),
            'rider_earnings' => self::pesos($rider),
            'logistics_product_commission' => self::pesos($logisticsProduct),
            'logistics_shipping_earnings' => self::pesos($logisticsShipping),
            'logistics_earnings' => self::pesos($logistics),
            'admin_product_commission' => self::pesos($adminProduct),
            'admin_shipping_earnings' => self::pesos($adminShipping),
            'admin_earnings' => self::pesos($admin),
            'seller_earnings' => self::pesos($seller),
        ];
    }

    /** @return array<string, float|string|bool> */
    public function forOrder(Order $order): array
    {
        $items = round((float) $order->subtotal, 2);
        $paidShipping = round((float) $order->shipping_fee, 2);
        $subsidy = round((float) ($order->shipping_discount ?? 0), 2);
        $voucher = Schema::hasColumn('orders', 'voucher_discount') ? round((float) $order->voucher_discount, 2) : 0.0;
        // A seller-funded voucher reduces the product amount being distributed.
        $split = self::breakdown(max(0, $items - $voucher), $paidShipping, $subsidy);
        $buyerPaid = round($items + $paidShipping - $voucher, 2);
        $gcash = $order->payment_method === 'gcash';
        $complete = in_array($order->status, self::COMPLETE, true);

        return [
            'method' => $gcash ? 'gcash' : 'cod',
            'complete' => $complete,
            'items' => $items,
            'voucher_by_seller' => $voucher,
            'buyer_paid' => $buyerPaid,
            ...$split,
            // Existing view/controller compatibility fields.
            'commission' => $split['admin_earnings'],
            'partner_share' => $split['logistics_earnings'],
            'seller_keeps' => $split['seller_earnings'],
            'zaylo_keeps' => $split['admin_earnings'],
            'collected_by' => $gcash ? 'seller' : 'rider',
            'zaylo_owes_seller' => $complete && ! $gcash ? $split['seller_earnings'] : 0.0,
            'seller_owes_zaylo' => $complete && $gcash ? round($buyerPaid - $split['seller_earnings'], 2) : 0.0,
        ];
    }

    /** The same split for a delivered parcel on either schema supported by the project. */
    public function forShipment(Shipment $shipment): array
    {
        if ($shipment->sellerOrder) {
            return self::breakdown(
                (int) $shipment->sellerOrder->subtotal_minor / 100,
                (int) $shipment->sellerOrder->shipping_fee_minor / 100,
            );
        }

        return $this->forOrder($shipment->order);
    }

    /** @return array{orders: Collection, owed_to_seller: float, owed_by_seller: float, balance: float} */
    public function forSeller(User $seller, int $limit = 100): array
    {
        $shopId = Shop::idForSeller($seller);
        $orders = $shopId && Order::usesLegacySchema()
            ? Order::where('seller_id', $shopId)->whereIn('status', self::COMPLETE)->latest('placed_at')->limit($limit)->get()
            : collect();
        $rows = $orders->map(fn (Order $order) => ['order' => $order] + $this->forOrder($order));
        $toSeller = round($rows->sum('zaylo_owes_seller'), 2);
        $bySeller = round($rows->sum('seller_owes_zaylo'), 2);

        return ['orders' => $rows, 'owed_to_seller' => $toSeller, 'owed_by_seller' => $bySeller, 'balance' => round($toSeller - $bySeller, 2)];
    }

    private static function centavos(float $amount): int
    {
        return (int) round($amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    private static function percentage(int $amount, int $basisPoints): int
    {
        return (int) round($amount * $basisPoints / 10000, 0, PHP_ROUND_HALF_UP);
    }

    private static function pesos(int $centavos): float
    {
        return $centavos / 100;
    }
}
