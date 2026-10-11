<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample deliveries for the rider app: orders from the demo seller that are
 * packed and waiting for a rider (order "ready_for_pickup", shipment "ready").
 *
 *   php artisan db:seed --class=RiderDeliverySampleSeeder
 *
 * Safe to re-run: it tops up to SAMPLE_COUNT waiting deliveries and never
 * touches real orders. Sample orders use the ZAY-SAMPLE- order number prefix.
 */
class RiderDeliverySampleSeeder extends Seeder
{
    public const ORDER_PREFIX = 'ZAY-SAMPLE-';
    private const SAMPLE_COUNT = 3;

    /** Recipients, delivery addresses, and how each order is paid. */
    private const ORDERS = [
        ['Maria Santos', '09171110001', '12 Rizal St., Brgy. San Isidro, Cabuyao, Laguna, CALABARZON, 4025', 'cod', [[0, 1], [1, 2]]],
        ['Paolo Reyes', '09171110002', '45 Mabini Ave., Brgy. Parian, Calamba, Laguna, CALABARZON, 4027', 'gcash', [[2, 1]]],
        ['Andrea Cruz', '09171110003', 'Blk 7 Lot 3, Mabuhay Homes, Brgy. Real, Calamba, Laguna, CALABARZON, 4027', 'cod', [[0, 2], [2, 1]]],
    ];

    public function run(): void
    {
        $seller = User::where('email', 'seller-demo@zaylo.test')->first();
        $buyer = User::where('email', 'buyer.demo@zaylo.test')->first() ?? User::where('role', 'buyer')->first();
        if (! $seller || ! $buyer) {
            $this->command?->warn('Skipped: needs the demo seller (seller-demo@zaylo.test) and a buyer account.');

            return;
        }

        $products = DB::table('products')->where('seller_id', $seller->id)->orderBy('id')->limit(3)->get(['id', 'name', 'price']);
        if ($products->count() < 3) {
            $this->command?->warn('Skipped: the demo seller needs at least 3 products.');

            return;
        }

        $this->ensurePickupAddress($seller);

        $waiting = DB::table('orders')
            ->join('shipments', 'shipments.order_id', '=', 'orders.id')
            ->where('orders.order_number', 'like', self::ORDER_PREFIX.'%')
            ->where('shipments.status', 'ready')->whereNull('shipments.courier_id')
            ->count();

        $created = 0;
        foreach (array_slice(self::ORDERS, 0, max(0, self::SAMPLE_COUNT - $waiting)) as [$name, $phone, $address, $payment, $lines]) {
            DB::transaction(fn () => $this->createOrder($seller, $buyer, $products->all(), $name, $phone, $address, $payment, $lines));
            $created++;
        }

        $this->command?->info("Rider deliveries waiting for pickup: ".($waiting + $created)." ($created new).");
    }

    /** Riders collect from the seller, so the demo store needs an address. */
    private function ensurePickupAddress(User $seller): void
    {
        if (DB::table('addresses')->where('user_id', $seller->id)->exists()) {
            return;
        }

        DB::table('addresses')->insert([
            'user_id' => $seller->id, 'label' => 'Other', 'recipient_name' => 'Happy Heels (pickup counter)',
            'phone' => '09171110000', 'line1' => 'Unit 3, Halang Commercial Center, National Highway',
            'barangay' => 'Halang', 'city' => 'Calamba', 'province' => 'Laguna', 'region_name' => 'CALABARZON',
            'postal_code' => '4027', 'country_code' => 'PH', 'is_default' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function createOrder(User $seller, User $buyer, array $products, string $name, string $phone, string $address, string $payment, array $lines): void
    {
        $subtotal = collect($lines)->sum(fn ($line) => (float) $products[$line[0]]->price * $line[1]);
        $shippingFee = 49.0;
        $total = $subtotal + $shippingFee;
        $placedAt = now()->subMinutes(random_int(20, 90));

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => self::ORDER_PREFIX.Str::upper(Str::random(8)),
            'user_id' => $buyer->id, 'seller_id' => $seller->id, 'status' => 'ready_for_pickup',
            'subtotal' => $subtotal, 'shipping_fee' => $shippingFee, 'total' => $total, 'currency' => 'PHP',
            'payment_method' => $payment, 'recipient_name' => $name, 'phone' => $phone, 'shipping_address' => $address,
            'notes' => $payment === 'cod' ? 'Please call when you arrive.' : null,
            'placed_at' => $placedAt, 'created_at' => $placedAt, 'updated_at' => now(), 'shipping_discount' => 0,
        ]);

        foreach ($lines as [$index, $quantity]) {
            $product = $products[$index];
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'product_id' => $product->id, 'seller_id' => $seller->id,
                'product_name' => $product->name, 'sku' => 'ZAY-SAMPLE-'.$product->id,
                'unit_price' => $product->price, 'quantity' => $quantity, 'line_total' => (float) $product->price * $quantity,
                'created_at' => $placedAt, 'updated_at' => $placedAt,
            ]);
        }

        DB::table('payments')->insert([
            'order_id' => $orderId, 'provider' => $payment, 'status' => $payment === 'cod' ? 'pending' : 'paid',
            'amount' => $total, 'currency' => 'PHP', 'idempotency_key' => (string) Str::uuid(),
            'paid_at' => $payment === 'cod' ? null : $placedAt, 'created_at' => $placedAt, 'updated_at' => $placedAt,
        ]);

        foreach ([['placed', 'Order placed (sample).', $buyer->id], ['ready_for_pickup', 'Packed and ready for rider pickup (sample).', $seller->id]] as [$status, $note, $by]) {
            DB::table('order_status_histories')->insert([
                'order_id' => $orderId, 'changed_by' => $by, 'status' => $status, 'note' => $note,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('shipments')->insert([
            'order_id' => $orderId, 'tracking_number' => 'TRK-'.Str::upper(Str::random(12)), 'status' => 'ready',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
