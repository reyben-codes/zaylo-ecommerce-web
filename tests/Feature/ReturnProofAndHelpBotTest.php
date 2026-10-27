<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\LegacyOrderTables;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

/**
 * Proof photos for deliveries and returns, the step-by-step return tracker for each role, and the help bot.
 */
class ReturnProofAndHelpBotTest extends TestCase
{
    use LegacyOrderTables;
    use RefreshDatabase;
    use SignsInMobile;

    private User $buyer;

    private User $seller;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        Schema::dropIfExists('delivery_events');
        $this->useLegacyOrderTables();
        Schema::table('shipments', fn (Blueprint $table) => $table->string('delivery_proof_path')->nullable());

        $this->buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Maria Buyer']);
        $this->seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->seller->sellerShop();
        $this->partner = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        LogisticsProvider::create(['user_id' => $this->partner->id, 'name' => 'Express Delivery', 'slug' => 'express-delivery', 'status' => 'approved']);
    }

    public function test_the_logistics_partner_sees_delivery_photos_in_deliveries(): void
    {
        $order = $this->order();
        Storage::disk('local')->put('delivery-proof/door.jpg', 'photo');
        $shipment = $order->shipment()->create([
            'tracking_number' => 'TRK-PHOTO', 'status' => 'delivered', 'delivered_at' => now(),
            'delivery_proof_path' => 'delivery-proof/door.jpg',
        ]);

        $this->actingAs($this->partner)->get(route('logistics.deliveries'))->assertOk()
            ->assertSee('Delivery photo')
            ->assertSee(route('delivery-proof', $shipment), false);
        $this->get(route('delivery-proof', $shipment))->assertOk();

        // A logistics account without a partner company, and a stranger, cannot open it.
        $loose = User::factory()->create(['role' => 'logistics', 'status' => 'active']);
        $this->actingAs($loose)->get(route('delivery-proof', $shipment))->assertForbidden();
        $stranger = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($stranger)->get(route('delivery-proof', $shipment))->assertForbidden();
    }

    public function test_a_return_needs_photos_at_pickup_and_at_the_hub_and_shows_every_step(): void
    {
        $rider = $this->approvedRider(['name' => 'Jun Rider']);
        $case = $this->returnCase(['return_rider_id' => $rider->id, 'return_assigned_at' => now()]);

        // The rider app: the slip alone is not enough, a photo of the item is.
        $this->signInMobile($rider);
        $this->getJson('/api/mobile/rider/dashboard')->assertOk()
            ->assertJsonPath('returns.0.needs_photo', true)
            ->assertJsonPath('returns.0.steps.2.key', 'picked_up')
            ->assertJsonPath('returns.0.steps.2.current', true)
            ->assertJsonPath('returns.0.next_step', "Collect the item (Pickup {$case->pickup_date->format('D, M j')}, 9:00 AM–1:00 PM): scan the buyer's return slip and take a photo of the item.");
        $this->postJson('/api/mobile/rider/returns/scan', ['code' => 'RET-PROOF1'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertSame('scheduled', $case->fresh()->return_status);
        $this->post('/api/mobile/rider/returns/scan', ['code' => 'RET-PROOF1', 'photo' => UploadedFile::fake()->image('item.jpg')], ['Accept' => 'application/json'])
            ->assertOk();
        $case->refresh();
        $this->assertSame('picked_up', $case->return_status);
        Storage::disk('local')->assertExists($case->return_pickup_photo_path);

        // The hub: receiving it needs a photo too.
        $this->actingAs($this->partner)->get(route('logistics.returns'))->assertOk()
            ->assertSee('Picked-up photo')
            ->assertSee('name="photo"', false);
        $this->patch(route('logistics.returns.scan', $case), ['return_status' => 'sorted'])->assertSessionHasErrors('photo');
        $this->patch(route('logistics.returns.scan', $case), ['return_status' => 'sorted', 'photo' => UploadedFile::fake()->image('hub.jpg')])
            ->assertSessionHasNoErrors();
        $case->refresh();
        $this->assertSame('sorted', $case->return_status);
        Storage::disk('local')->assertExists($case->return_hub_photo_path);
        $this->get(route('logistics.scan', ['tracking' => 'RET-PROOF1']))->assertOk()
            ->assertSee('Return tracking')
            ->assertSee('Send the item back to the seller.');

        // Each role sees where the item is and what to do next, with the proof photos.
        $this->actingAs($this->buyer)->get(route('disputes.show', $case))->assertOk()
            ->assertSee('Nothing to do. Your item is on its way back to the seller.')
            ->assertSee(route('return-photo', [$case, 'pickup']), false)
            ->assertSee(route('return-photo', [$case, 'hub']), false);
        $this->actingAs($this->seller)->get(route('seller.returns'))->assertOk()
            ->assertSee('The hub is sending the item back to you. Confirm on the Returns page when it arrives.');
        $this->signInMobile($this->buyer);
        $this->getJson("/api/mobile/complaints/{$case->id}")->assertOk()
            ->assertJsonPath('complaint.return.next', 'Nothing to do. Your item is on its way back to the seller.')
            ->assertJsonPath('complaint.return.steps.3.label', 'At the logistics hub')
            ->assertJsonPath('complaint.return.steps.3.done', true);

        // Photos open for the case, its rider and the partner only.
        foreach ([$this->buyer, $this->seller, $rider, $this->partner] as $viewer) {
            $this->assertSame(200, $this->actingAs($viewer)->get(route('return-photo', [$case, 'hub']))->baseResponse->getStatusCode(), "Viewer {$viewer->role} could not open the photo.");
        }
        $stranger = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($stranger)->get(route('return-photo', [$case, 'pickup']))->assertForbidden();
    }

    public function test_the_help_bot_answers_for_each_kind_of_account(): void
    {
        $this->actingAs($this->seller)->get(route('seller.dashboard'))->assertOk()
            ->assertSee('data-faq-bot', false)->assertSee('js/faq-bot.js', false);
        $this->getJson(route('faq'))->assertOk()
            ->assertJsonPath('items.0.q', 'How do I add a product?')
            ->assertJsonPath('items.0.link.url', route('seller.products.create'))
            ->assertJsonPath('support.url', route('support'))
            ->assertJsonFragment(['q' => 'How do I talk to a person at ZAYLO?']);

        $this->actingAs($this->partner)->getJson(route('faq'))->assertJsonFragment(['q' => 'Where are the delivery photos?']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->getJson(route('faq'))->assertJsonPath('support.url', route('admin.chat'));

        // The app gets buyer or rider answers without links.
        $this->getJson('/api/mobile/faq?role=rider')->assertOk()
            ->assertJsonFragment(['q' => 'How do I collect a returned item?'])
            ->assertJsonMissingPath('items.0.link');
        $this->getJson('/api/mobile/faq?role=admin')->assertUnprocessable();

        // Guests browsing the store do not get the bot.
        auth()->logout();
        $this->get('/')->assertDontSee('data-faq-bot', false);
    }

    private function order(): Order
    {
        return Order::create([
            'order_number' => 'ZAY-'.Str::upper(Str::random(8)), 'user_id' => $this->buyer->id,
            'seller_id' => \App\Support\Shop::idForSeller($this->seller), 'status' => 'completed',
            'subtotal' => 450, 'shipping_fee' => 50, 'total' => 500, 'payment_method' => 'cod',
            'recipient_name' => 'Maria Buyer', 'phone' => '09171234567', 'shipping_address' => 'Test Street, Manila', 'placed_at' => now(),
        ]);
    }

    private function returnCase(array $attributes = []): Dispute
    {
        return Dispute::create($attributes + [
            'order_id' => $this->order()->id, 'opened_by' => $this->buyer->id, 'status' => 'in_review', 'category' => 'damaged',
            'reason' => 'The item arrived with a torn seam.', 'evidence' => [], 'return_type' => 'return_refund',
            'return_method' => 'pickup', 'return_tracking_number' => 'RET-PROOF1', 'return_status' => 'scheduled',
            'pickup_date' => today()->addDay(), 'pickup_slot' => 'morning',
        ]);
    }
}
