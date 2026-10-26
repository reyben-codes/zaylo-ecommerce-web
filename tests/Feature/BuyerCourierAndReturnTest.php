<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerCourierAndReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_order_can_specify_courier_truck_choice(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'reference' => 'ZAY-TRUCK-001',
            'total_minor' => 55000,
            'payment_method' => 'cod',
            'shipping_address' => ['recipient' => 'Test Buyer', 'phone' => '09123456789', 'city' => 'Manila'],
            'courier_choice' => 'jnt_express',
        ]);

        $this->assertSame('jnt_express', $order->courier_choice);
        $this->assertSame('J&T Express', $order->courierName());
    }

    public function test_buyer_can_file_return_with_pickup_schedule_and_courier(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'email_verified_at' => now()]);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'reference' => 'ZAY-RETURN-001',
            'total_minor' => 85000,
            'payment_method' => 'cod',
            'shipping_address' => ['recipient' => 'Maria Santos', 'phone' => '09171234567', 'city' => 'Quezon City'],
            'courier_choice' => 'flash_express',
        ]);

        $pickupDate = today()->addDays(2)->toDateString();

        $dispute = (new \App\Services\DisputeService())->open(
            order: $order,
            buyer: $buyer,
            category: 'damaged_item',
            details: 'The item arrived damaged with broken parts inside.',
            evidence: [],
            returnDetails: [
                'return_type' => 'return_refund',
                'return_method' => 'pickup',
                'return_courier' => 'flash_express',
                'pickup_date' => $pickupDate,
                'pickup_slot' => 'morning',
            ]
        );

        $this->assertNotNull($dispute);
        $this->assertStringStartsWith('RET-', $dispute->return_tracking_number);
        $this->assertSame('return_refund', $dispute->return_type);
        $this->assertSame('pickup', $dispute->return_method);
        $this->assertSame('flash_express', $dispute->return_courier);
        $this->assertSame('Flash Express', $dispute->returnCourierName());
        $this->assertSame('Courier Pickup (Home Address)', $dispute->returnMethodName());
        $this->assertSame('scheduled', $dispute->return_status);
        $this->assertSame($pickupDate, $dispute->pickup_date->toDateString());
        $this->assertSame('morning', $dispute->pickup_slot);

        // View dispute case page has the return tracker card
        $this->actingAs($buyer);
        $showResponse = $this->get(route('disputes.show', $dispute));
        $showResponse->assertOk();
        $showResponse->assertSee('Return &amp; Refund Tracker', false);
        $showResponse->assertSee($dispute->return_tracking_number);
        $showResponse->assertSee('Flash Express');
        $showResponse->assertSee('Courier Handover Barcode');
    }

    public function test_logistics_partner_scans_return_parcel_and_advances_status(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'reference' => 'ZAY-SCAN-RET',
            'total_minor' => 125000,
            'payment_method' => 'cod',
            'shipping_address' => ['recipient' => 'Juan Dela Cruz', 'phone' => '09181234567', 'city' => 'Makati City'],
            'courier_choice' => 'jnt_express',
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'opened_by' => $buyer->id,
            'category' => 'wrong_item',
            'reason' => 'Received wrong color and model completely.',
            'status' => 'open',
            'return_type' => 'return_refund',
            'return_method' => 'pickup',
            'return_courier' => 'jnt_express',
            'return_tracking_number' => 'RET-TEST12345',
            'return_status' => 'scheduled',
            'pickup_date' => today()->addDay(),
            'pickup_slot' => 'afternoon',
        ]);

        $this->actingAs($owner);

        // Look up by return tracking number
        $scanResponse = $this->get(route('logistics.scan', ['tracking' => 'RET-TEST12345']));
        $scanResponse->assertOk();
        $scanResponse->assertSee('RET-TEST12345');
        $scanResponse->assertSee('J&amp;T Express', false);
        $scanResponse->assertSee('Confirm Truck Pickup from Home');

        // Confirm pickup
        $patchResponse = $this->patch(route('logistics.returns.scan', $dispute), [
            'return_status' => 'picked_up',
        ]);
        $patchResponse->assertRedirect(route('logistics.scan', ['tracking' => 'RET-TEST12345']));

        $dispute->refresh();
        $this->assertSame('picked_up', $dispute->return_status);

        // Advance to sorted at hub
        $this->patch(route('logistics.returns.scan', $dispute), [
            'return_status' => 'sorted',
        ]);
        $dispute->refresh();
        $this->assertSame('sorted', $dispute->return_status);
    }
}
