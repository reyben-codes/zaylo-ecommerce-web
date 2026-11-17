<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use App\Notifications\DisputeUpdated;
use App\Services\DisputeService;
use App\Services\ReturnPickups;
use App\Services\SellerQueues;
use App\Support\ReturnProcess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReturnApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function requestReturn(string $method = 'pickup'): array
    {
        Notification::fake();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'RETURN-APPROVAL', 'total_minor' => 50000, 'payment_method' => 'cod', 'shipping_address' => ['city' => 'Manila']]);
        $order->sellerOrders()->create(['seller_id' => $seller->sellerShop()->id, 'subtotal_minor' => 50000, 'shipping_fee_minor' => 0, 'commission_minor' => 5000, 'status' => 'delivered']);
        $case = app(DisputeService::class)->open($order, $buyer, 'damaged', 'The product arrived damaged and cannot be used.', [], [
            'return_type' => 'return_refund', 'return_method' => $method, 'pickup_date' => today()->addDay(),
        ]);

        return compact('buyer', 'seller', 'order', 'case');
    }

    public function test_new_return_waits_for_seller_and_only_its_seller_can_approve_once(): void
    {
        ['buyer' => $buyer, 'seller' => $seller, 'case' => $case] = $this->requestReturn();
        $this->assertSame('pending_seller', $case->return_status);
        $this->assertFalse(ReturnPickups::canAssign($case));
        $this->assertSame([], ReturnPickups::logisticsSteps($case));
        $this->assertSame('approved', collect(ReturnProcess::for($case, 'buyer')['steps'])->firstWhere('current', true)['key']);
        $this->actingAs($buyer)->get(route('disputes.show', $case))->assertOk()->assertSee('Waiting for seller approval')->assertDontSee('Courier Handover Barcode');

        $other = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($other)->patch(route('seller.returns.approve', $case))->assertForbidden();
        $case->update(['status' => 'in_review']); // A case reply is not approval.
        $this->assertSame(1, app(SellerQueues::class)->returnsNeedingAction($seller));
        $this->actingAs($seller)->get(route('seller.returns'))->assertSee('Approve return');
        $this->patch(route('seller.returns.approve', $case))->assertSessionHasNoErrors();
        $case->refresh();
        $this->assertSame('scheduled', $case->return_status);
        $this->assertEquals($seller->id, $case->return_approved_by);
        $this->assertNotNull($case->return_approved_at);
        $this->assertTrue(ReturnPickups::canAssign($case));
        $this->patch(route('seller.returns.approve', $case))->assertSessionHasErrors('return');
        $this->assertSame(1, $case->messages()->count());
        Notification::assertSentTo($buyer, DisputeUpdated::class, fn ($notice) => $notice->event === 'return_approved');
    }

    public function test_pending_dropoff_cannot_advance_and_its_deadline_starts_at_approval(): void
    {
        ['seller' => $seller, 'case' => $case] = $this->requestReturn('dropoff');
        $this->assertNull($case->dropoff_deadline);
        try {
            app(ReturnPickups::class)->advance($case, 'sorted', $seller);
            $this->fail('An unapproved return must not enter the hub.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('return_status', $exception->errors());
        }
        $this->actingAs($seller)->patch(route('seller.returns.approve', $case))->assertSessionHasNoErrors();
        $case->refresh();
        $this->assertSame(now('Asia/Manila')->addDays(3)->toDateString(), $case->dropoff_deadline->toDateString());
        $this->assertSame(['sorted'], ReturnPickups::logisticsSteps($case));
    }

    public function test_even_a_previously_assigned_rider_cannot_collect_before_approval(): void
    {
        ['case' => $case] = $this->requestReturn();
        $rider = User::factory()->create(['role' => 'rider', 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => 'Return Hub', 'slug' => 'return-hub', 'status' => 'approved']);
        $profile = Rider::create(['user_id' => $rider->id, 'logistics_provider_id' => $provider->id, 'status' => 'approved']);
        $service = app(ReturnPickups::class);
        try {
            $service->assign($case, $profile, $owner);
            $this->fail('Assignment must wait for approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rider_id', $exception->errors());
        }
        $case->update(['return_rider_id' => $rider->id]);
        $this->assertCount(0, $service->forRider($rider));
        $this->expectException(ValidationException::class);
        $service->scan($rider, $case->return_tracking_number);
    }

    public function test_migration_requires_approval_for_uncollected_existing_returns_only(): void
    {
        ['case' => $waiting] = $this->requestReturn('dropoff');
        $migration = require database_path('migrations/2026_11_17_000005_add_seller_return_approval.php');
        $migration->down();
        $waiting->update(['return_status' => 'scheduled', 'dropoff_deadline' => today()->addDays(3)]);
        $inTransit = $waiting->fresh()->replicate();
        $inTransit->fill(['return_tracking_number' => 'RET-IN-TRANSIT', 'return_status' => 'picked_up'])->save();
        $migration->up();

        $this->assertSame('pending_seller', $waiting->fresh()->return_status);
        $this->assertNull($waiting->fresh()->dropoff_deadline);
        $this->assertSame('picked_up', $inTransit->fresh()->return_status);
    }
}
