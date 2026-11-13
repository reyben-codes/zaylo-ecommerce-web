<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LegacyOrderTables;
use Tests\TestCase;

class SellerDailyHistoryTest extends TestCase
{
    use RefreshDatabase, LegacyOrderTables;

    public function test_daily_dashboard_resets_at_manila_midnight_and_history_keeps_old_orders(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-13 15:59:00', 'UTC'));
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $shop = $seller->sellerShop();
        $buyer = User::factory()->create();
        foreach (['2026-11-12 16:00:00' => 'TODAY', '2026-11-12 15:59:59' => 'YESTERDAY'] as $date => $reference) {
            $order = Order::create(['buyer_id' => $buyer->id, 'reference' => $reference, 'total_minor' => 10000, 'payment_method' => 'cod', 'shipping_address' => []]);
            $row = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $shop->id, 'subtotal_minor' => 10000, 'shipping_fee_minor' => 0, 'commission_minor' => 0, 'status' => 'pending']);
            $row->forceFill(['created_at' => $date])->save();
        }
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()
            ->assertViewHas('orderCounts', fn ($counts) => $counts['all'] === 1);
        $this->get(route('seller.orders.history', ['date' => '2026-11-12']))->assertOk()->assertSee('YESTERDAY')->assertDontSee('TODAY');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-13 16:00:00', 'UTC'));
        $this->get(route('seller.dashboard'))->assertOk()->assertViewHas('orderCounts', fn ($counts) => $counts['all'] === 0);
        $this->get(route('seller.orders.history'))->assertOk()->assertSee('TODAY')->assertSee('YESTERDAY');
        $this->actingAs(User::factory()->create(['role' => 'seller', 'status' => 'active']))
            ->get(route('seller.orders.history'))->assertOk()->assertDontSee('TODAY')->assertDontSee('YESTERDAY');
    }

    public function test_legacy_history_filters_cancelled_delivered_and_refunded_orders(): void
    {
        $this->useLegacyOrderTables();
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $buyer = User::factory()->create();
        foreach (['cancelled', 'delivered', 'completed'] as $status) {
            $order = Order::create([
                'seller_id' => $seller->sellerShop()->id, 'user_id' => $buyer->id, 'order_number' => 'HISTORY-'.$status,
                'status' => $status, 'subtotal' => 100, 'shipping_fee' => 120, 'total' => 220,
                'payment_method' => 'cod', 'recipient_name' => 'Buyer', 'phone' => '09171234567', 'shipping_address' => 'Manila', 'placed_at' => now()->subDay(),
            ]);
            if ($status === 'completed') {
                Dispute::create(['order_id' => $order->id, 'opened_by' => $buyer->id, 'status' => 'resolved', 'reason' => 'Refund', 'refund_amount' => 100, 'refunded_at' => now()]);
            }
        }
        $this->actingAs($seller)->get(route('seller.orders.history', ['status' => 'cancelled']))->assertOk()->assertSee('HISTORY-cancelled')->assertDontSee('HISTORY-delivered');
        $this->get(route('seller.orders.history', ['status' => 'delivered']))->assertOk()->assertSee('HISTORY-delivered')->assertSee('HISTORY-completed')->assertDontSee('HISTORY-cancelled');
        $this->get(route('seller.orders.history', ['status' => 'refunded']))->assertOk()->assertSee('HISTORY-completed')->assertSee('Refunded: PHP 100.00')->assertDontSee('HISTORY-delivered');
    }
}
