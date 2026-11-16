<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EscrowPayment;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\RiderWallet;
use App\Services\SellerWallet;
use App\Services\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialResetTest extends TestCase
{
    use RefreshDatabase, \Tests\Support\LegacyOrderTables;

    private array $existingBackups = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->existingBackups = glob(storage_path('app/private/financial-resets/*.jsonl')) ?: [];
    }

    protected function tearDown(): void
    {
        foreach ($this->newBackups() as $path) unlink($path);
        parent::tearDown();
    }

    private function newBackups(): array
    {
        return array_values(array_diff(glob(storage_path('app/private/financial-resets/*.jsonl')) ?: [], $this->existingBackups));
    }

    private function records(bool $legacy = false): array
    {
        if ($legacy) $this->useLegacyOrderTables();
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Reset Partner', 'slug' => 'reset-partner', 'status' => 'approved']);
        $category = Category::create(['name' => 'Preserved', 'slug' => 'preserved']);
        $product = Product::create(['seller_id' => $seller->sellerShop()->id, 'name' => 'Keep this product', 'category_id' => $category->id, 'price' => 500, 'stock' => 7]);
        $order = $legacy
            ? Order::create(['user_id' => $buyer->id, 'seller_id' => $seller->id, 'order_number' => 'ZAY-RESET', 'status' => 'completed', 'subtotal' => 500, 'shipping_fee' => 120,
                'total' => 620, 'payment_method' => 'gcash', 'recipient_name' => 'Buyer', 'phone' => '09171234567', 'shipping_address' => 'Calamba'])
            : Order::create(['buyer_id' => $buyer->id, 'reference' => 'ZAY-RESET', 'total_minor' => 62000, 'payment_method' => 'gcash', 'shipping_address' => ['city' => 'Calamba']]);
        if ($legacy) {
            $shipment = $order->shipment()->create(['tracking_number' => 'RESET-TRK', 'status' => 'delivered']);
            $item = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 500, 'line_total' => 500]);
            $order->payment()->create(['provider' => 'gcash', 'amount' => 620, 'status' => 'paid']);
        } else {
            $sellerOrder = $order->sellerOrders()->create(['seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 50000, 'shipping_fee_minor' => 12000, 'commission_minor' => 5000, 'status' => 'completed']);
            $shipment = $sellerOrder->shipment()->create(['logistics_provider_id' => $provider->id, 'tracking_code' => 'RESET-TRK', 'status' => 'delivered']);
            $item = $sellerOrder->items()->create(['product_id' => $product->id, 'product_variant_id' => $product->defaultVariant->id, 'product_name' => $product->name, 'variant_name' => 'Default', 'quantity' => 1, 'unit_price_minor' => 50000]);
            $order->payment()->create(['method' => 'gcash', 'provider' => 'gcash', 'amount_minor' => 62000, 'status' => 'paid']);
        }
        $escrow = EscrowPayment::create(['order_id' => $order->id, 'amount_minor' => 62000, 'status' => 'released', 'livemode' => false]);
        foreach ([[$seller->id, 'seller', 40000], [$owner->id, 'logistics', 8000]] as [$user, $kind, $amount]) {
            DB::table('settlement_wallet_entries')->insert(['escrow_payment_id' => $escrow->id, 'user_id' => $user, 'kind' => $kind, 'amount_minor' => $amount]);
        }
        DB::table('seller_withdrawals')->insert(['user_id' => $seller->id, 'amount_minor' => 10000, 'method' => 'gcash', 'account_number' => '09171234567', 'account_name' => 'Seller', 'status' => 'paid', 'livemode' => false]);
        DB::table('rider_commissions')->insert(['user_id' => $rider->id, 'shipment_id' => $shipment->id, 'amount' => 40]);
        DB::table('rider_leg_commissions')->insert(['user_id' => $rider->id, 'shipment_id' => $shipment->id, 'leg' => 'pickup', 'amount' => 20, 'distance_km' => 2, 'weight_kg' => 1]);
        DB::table('rider_withdrawals')->insert(['user_id' => $rider->id, 'amount' => 10, 'gcash_number' => '09171234567', 'gcash_name' => 'Rider', 'status' => 'pending']);
        DB::table('commissions')->insert(['order_id' => $order->id, 'seller_id' => $seller->id, 'rate' => 10, 'amount' => 50]);
        DB::table('product_reviews')->insert(['order_item_id' => $item->id, 'product_id' => $product->id, 'user_id' => $buyer->id, 'rating' => 5, 'comment' => 'Preserve review']);
        foreach (['orders', 'wallet', 'profile'] as $section) {
            $id = DB::table('account_updates')->insertGetId(['user_id' => $buyer->id, 'event_key' => $section.'-fixture', 'section' => $section, 'title' => 'Update', 'body' => 'Fixture', 'kind' => $section]);
            DB::table('buyer_notification_reads')->insert(['user_id' => $buyer->id, 'notification_key' => 'account:'.$id, 'read_at' => now()]);
        }
        return compact('seller', 'buyer', 'owner', 'rider', 'product', 'order', 'shipment');
    }

    public function test_preview_does_not_change_records_or_create_a_backup(): void
    {
        $this->records();
        $this->artisan('finance:reset')->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('seller_withdrawals', 1);
        $this->assertSame([], $this->newBackups());
    }

    public function test_reset_clears_shared_finances_without_changing_products_or_accounts(): void
    {
        $this->assertReset(false);
    }

    public function test_reset_also_supports_the_deployed_legacy_order_schema(): void
    {
        Schema::create('reviews', function ($table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->integer('rating');
        });
        $this->assertReset(true);
    }

    public function test_an_unknown_dependency_aborts_before_any_deletion(): void
    {
        $this->records();
        Schema::create('extra_order_links', function ($table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
        });
        DB::table('extra_order_links')->insert(['order_id' => 1]);
        $this->artisan('finance:reset --execute')->expectsOutputToContain('Unrecognized reference')->assertFailed();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('extra_order_links', 1);
        $this->assertSame([], $this->newBackups());
    }

    private function assertReset(bool $legacy): void
    {
        $f = $this->records($legacy);
        $protected = [];
        foreach (['products', 'product_variants', 'product_images', 'users', 'sellers', 'logistics_providers'] as $table) $protected[$table] = DB::table($table)->get()->toJson();
        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('finance:reset', ['--execute' => true]), \Illuminate\Support\Facades\Artisan::output());
        foreach (['orders', 'order_items', 'payments', 'shipments', 'escrow_payments', 'settlement_wallet_entries', 'seller_withdrawals', 'rider_commissions', 'rider_leg_commissions', 'rider_withdrawals', 'commissions'] as $table) $this->assertDatabaseCount($table, 0);
        foreach ($protected as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson(), $table);
        $this->assertDatabaseHas('product_reviews', ['product_id' => $f['product']->id, 'order_item_id' => null, 'comment' => 'Preserve review']);
        $this->assertDatabaseCount('account_updates', 1);
        $this->assertDatabaseHas('account_updates', ['section' => 'profile']);
        $this->assertDatabaseCount('buyer_notification_reads', 1);
        $this->assertEquals(0, app(SellerWallet::class)->balance($f['seller']));
        $this->assertEquals(0, app(RiderWallet::class)->balance($f['rider']));
        $this->assertEquals(0, app(Wallet::class)->summary($f['owner'])['balance']);
        $backups = $this->newBackups();
        $this->assertCount(1, $backups);
        $lines = array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($backups[0], FILE_IGNORE_NEW_LINES));
        $this->assertTrue(end($lines)['backup_complete']);
        $this->assertNotEmpty(array_filter($lines, fn ($line) => ($line['table'] ?? '') === 'orders' && isset($line['row'])));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('finance:reset', ['--execute' => true]), \Illuminate\Support\Facades\Artisan::output());
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_database_failure_rolls_back_the_entire_reset(): void
    {
        $this->records();
        DB::unprepared("CREATE TRIGGER reject_reset BEFORE DELETE ON payments BEGIN SELECT RAISE(ABORT, 'fixture failure'); END");
        $this->artisan('finance:reset --execute')->expectsOutputToContain('database changes rolled back')->assertFailed();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('settlement_wallet_entries', 2);
        $this->assertDatabaseCount('seller_withdrawals', 1);
        $this->assertDatabaseHas('product_reviews', ['order_item_id' => 1]);
    }
}
