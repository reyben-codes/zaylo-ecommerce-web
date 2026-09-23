<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DeliveryEvent;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MilestoneFourSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_checkpoint_tables_and_structural_decisions_exist(): void
    {
        foreach ([
            'users', 'roles', 'role_user', 'addresses', 'sellers', 'logistics_providers',
            'riders', 'categories', 'products', 'product_variants', 'product_images',
            'carts', 'cart_items', 'orders', 'seller_orders', 'order_items', 'payments',
            'shipments', 'delivery_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumns('role_user', ['role_id', 'user_id']));
        $this->assertFalse(Schema::hasColumn('order_items', 'order_id'));
        $this->assertTrue(Schema::hasColumn('order_items', 'seller_order_id'));
        $this->assertFalse(Schema::hasColumn('orders', 'address_id'));
        $this->assertTrue(Schema::hasColumn('orders', 'shipping_address'));

        foreach ([
            ['product_variants', 'price_minor'],
            ['orders', 'total_minor'],
            ['seller_orders', 'subtotal_minor'],
            ['seller_orders', 'shipping_fee_minor'],
            ['seller_orders', 'commission_minor'],
            ['order_items', 'unit_price_minor'],
            ['payments', 'amount_minor'],
            ['shipments', 'fee_minor'],
            ['shipments', 'cod_amount_minor'],
        ] as [$table, $column]) {
            $this->assertSame('integer', Schema::getColumnType($table, $column));
        }
    }

    public function test_required_unique_keys_and_filter_indexes_exist(): void
    {
        $this->assertUniqueIndex('sellers', ['slug']);
        $this->assertUniqueIndex('orders', ['reference']);
        $this->assertUniqueIndex('shipments', ['tracking_code']);
        $this->assertUniqueIndex('seller_orders', ['order_id', 'seller_id']);
        $this->assertIndex('seller_orders', ['seller_id', 'status']);
        $this->assertIndex('products', ['seller_id', 'is_active']);
        $this->assertIndex('products', ['category_id', 'is_active']);
    }

    public function test_domain_foreign_keys_are_database_constraints(): void
    {
        $expected = [
            'role_user' => ['role_id', 'user_id'],
            'addresses' => ['user_id'],
            'sellers' => ['user_id', 'pickup_address_id'],
            'logistics_providers' => ['user_id'],
            'riders' => ['user_id', 'logistics_provider_id'],
            'categories' => ['parent_id'],
            'products' => ['seller_id', 'category_id'],
            'product_variants' => ['product_id'],
            'product_images' => ['product_id'],
            'carts' => ['user_id'],
            'cart_items' => ['cart_id', 'product_variant_id'],
            'orders' => ['buyer_id'],
            'seller_orders' => ['order_id', 'seller_id', 'logistics_provider_id'],
            'order_items' => ['seller_order_id', 'product_id', 'product_variant_id'],
            'payments' => ['order_id'],
            'shipments' => ['seller_order_id', 'logistics_provider_id', 'rider_id'],
            'delivery_events' => ['shipment_id', 'user_id'],
        ];

        foreach ($expected as $table => $columns) {
            $actual = collect(Schema::getForeignKeys($table))->pluck('columns')->flatten()->all();
            foreach ($columns as $column) {
                $this->assertContains($column, $actual, "Missing constrained foreign key {$table}.{$column}");
            }
        }
    }

    public function test_relationship_graph_can_be_walked(): void
    {
        $sellerUser = User::factory()->create(['role' => 'seller']);
        $seller = $sellerUser->sellers()->create([
            'name' => 'Happy Heels', 'slug' => 'happy-heels', 'status' => 'approved',
        ]);
        $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);
        $product = $seller->products()->create([
            'category_id' => $category->id, 'name' => 'Classic Heel',
            'slug' => 'classic-heel', 'description' => 'A classic heel.',
        ]);
        $variant = $product->variants()->create([
            'sku' => 'HH-001', 'name' => 'Black / 38',
            'options' => ['color' => 'Black', 'size' => '38'],
            'price_minor' => 219900, 'stock' => 10,
        ]);
        $product->images()->create(['path' => '/images/heel.webp', 'position' => 0]);

        $logisticsUser = User::factory()->create(['role' => 'logistics']);
        $provider = LogisticsProvider::create([
            'user_id' => $logisticsUser->id, 'name' => 'ZAYLO Express',
            'slug' => 'zaylo-express', 'status' => 'approved',
        ]);
        $riderUser = User::factory()->create(['role' => 'rider']);
        $rider = Rider::create([
            'user_id' => $riderUser->id, 'logistics_provider_id' => $provider->id,
            'vehicle_type' => 'Motorcycle', 'plate_no' => 'ABC-123',
        ]);

        $buyer = User::factory()->create(['role' => 'buyer']);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'reference' => 'ZAY-ORDER-001',
            'total_minor' => 229900, 'payment_method' => 'cod',
            'shipping_address' => ['recipient' => 'Buyer', 'line1' => '123 Test St'],
        ]);
        $sellerOrder = SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $seller->id,
            'logistics_provider_id' => $provider->id, 'subtotal_minor' => 219900,
            'shipping_fee_minor' => 10000, 'commission_minor' => 17592,
        ]);
        $sellerOrder->items()->create([
            'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'product_name' => $product->name, 'variant_name' => $variant->name,
            'unit_price_minor' => 219900, 'quantity' => 1,
        ]);
        $shipment = Shipment::create([
            'seller_order_id' => $sellerOrder->id,
            'logistics_provider_id' => $provider->id, 'rider_id' => $rider->id,
            'tracking_code' => 'ZX-TRACK-001',
        ]);
        DeliveryEvent::create([
            'shipment_id' => $shipment->id, 'status' => 'assigned', 'attempt' => 1,
            'user_id' => $riderUser->id, 'occurred_at' => now(),
        ]);

        $this->assertSame('Classic Heel', $sellerUser->sellers->first()->products->first()->name);
        $this->assertSame('Shoes', $product->category->name);
        $this->assertSame('HH-001', $product->variants->first()->sku);
        $this->assertSame('Happy Heels', $order->sellerOrders->first()->seller->name);
        $this->assertSame('ZX-TRACK-001', $sellerOrder->shipment->tracking_code);
        $this->assertSame('assigned', $shipment->events->first()->status);
        $this->assertSame($riderUser->name, $shipment->rider->user->name);
    }

    private function assertUniqueIndex(string $table, array $columns): void
    {
        $this->assertTrue(collect(Schema::getIndexes($table))->contains(
            fn (array $index) => $index['unique'] && $index['columns'] === $columns
        ), "Missing unique index on {$table} (".implode(', ', $columns).')');
    }

    private function assertIndex(string $table, array $columns): void
    {
        $this->assertTrue(collect(Schema::getIndexes($table))->contains(
            fn (array $index) => $index['columns'] === $columns
        ), "Missing index on {$table} (".implode(', ', $columns).')');
    }
}
