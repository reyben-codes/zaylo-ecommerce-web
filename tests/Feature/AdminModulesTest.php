<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\ComplianceWarning;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\PlatformPolicy;
use App\Models\Product;
use App\Models\SellerApplication;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Notifications\AccountReviewed;
use App\Notifications\ComplianceWarningIssued;
use App\Notifications\DisputeUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin Center modules: complaints and disputes, seller compliance, commission and reports,
 * platform settings, support chat, and account status emails.
 */
class AdminModulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'name' => 'Ada Admin']);
    }

    public function test_every_admin_module_page_works_and_is_admin_only(): void
    {
        [$order] = $this->order('delivered');
        $this->actingAs($this->admin);

        $dashboard = $this->get(route('admin.dashboard'))->assertOk()->assertSee('Needs your attention');
        foreach (['compliance', 'disputes', 'commissions', 'reports', 'settings', 'chat'] as $page) {
            $dashboard->assertSee(route("admin.{$page}"), false);
            $this->get(route("admin.{$page}"))->assertOk()->assertDontSee('coming soon');
        }
        $this->get(route('admin.users.show', $order->buyer))->assertOk()->assertSee($order->buyer->email);
        $this->get(route('admin.policies.edit', 'privacy'))->assertOk();
        foreach (['sales', 'commission'] as $report) {
            $pdf = $this->get(route('admin.reports.pdf', ['report' => $report]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent());
        }

        $this->actingAs($order->buyer)->get(route('admin.disputes'))->assertForbidden();
    }

    public function test_a_buyer_complaint_is_reviewed_with_the_seller_and_decided_by_the_admin(): void
    {
        [$order, $seller] = $this->order('delivered');
        $buyer = $order->buyer;
        $stranger = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        // The buyer files a complaint with a photo.
        $this->actingAs($buyer)->get(route('buyer.disputes.create', $order))->assertOk()->assertSee('Report a problem');
        $this->post(route('buyer.disputes.store', $order), ['category' => 'damaged', 'details' => 'Too short'])->assertSessionHasErrors('details');
        $this->post(route('buyer.disputes.store', $order), [
            'category' => 'damaged',
            'details' => 'The lamp arrived with a cracked base and the bulb does not light.',
            'evidence' => [UploadedFile::fake()->image('crack.jpg')],
        ])->assertRedirect();
        $dispute = Dispute::firstOrFail();
        $this->assertSame('open', $dispute->status);
        Storage::disk('local')->assertExists($dispute->evidence[0]);
        Notification::assertSentTo($seller, DisputeUpdated::class, fn ($notice) => $notice->event === 'opened');
        // One complaint per order.
        $this->get(route('buyer.disputes.create', $order))->assertRedirect(route('disputes.show', $dispute));

        // The seller sees the case and the evidence, and replies.
        $this->actingAs($seller)->get(route('disputes.index'))->assertOk()->assertSee('Damaged or defective item');
        $this->get(route('disputes.evidence', [$dispute, 0]))->assertOk();
        $this->post(route('disputes.reply', $dispute), ['body' => 'We can send a replacement this week.'])->assertSessionHasNoErrors();
        $this->assertSame('in_review', $dispute->fresh()->status);

        // Nobody outside the order can open it.
        $this->actingAs($stranger)->get(route('disputes.show', $dispute))->assertForbidden();
        $this->get(route('disputes.evidence', [$dispute, 0]))->assertForbidden();

        // The admin reviews, messages everyone, and decides.
        $this->actingAs($this->admin)->get(route('admin.disputes.show', $dispute))->assertOk()
            ->assertSee('cracked base')->assertSee('We can send a replacement')->assertSee($seller->name);
        $this->post(route('admin.disputes.reply', $dispute), ['body' => 'Please confirm the replacement tracking number.'])->assertSessionHasNoErrors();
        $this->patch(route('admin.disputes.update', $dispute), ['status' => 'resolved'])->assertSessionHasErrors('resolution');
        $this->patch(route('admin.disputes.update', $dispute), ['status' => 'resolved', 'resolution' => 'Seller ships a replacement; buyer keeps the damaged lamp.'])->assertSessionHasNoErrors();

        $fresh = $dispute->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertSame($this->admin->id, $fresh->resolved_by);
        Notification::assertSentTo([$buyer, $seller], DisputeUpdated::class, fn ($notice) => $notice->event === 'decided');
        $this->actingAs($buyer)->get(route('disputes.show', $dispute))->assertSee('buyer keeps the damaged lamp');
        $this->post(route('disputes.reply', $dispute), ['body' => 'One more thing'])->assertSessionHasErrors('body');
    }

    public function test_compliance_flags_products_and_the_admin_can_warn_hide_and_suspend(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Gadget Hub']);
        $this->application($seller, 'electronics');
        $offCategory = Product::create(['seller_id' => $seller->id, 'name' => 'Canvas tote', 'category' => 'bags', 'price' => 300, 'stock' => 5, 'is_active' => true]);
        $prohibited = Product::create(['seller_id' => $seller->id, 'name' => 'Replica smart watch', 'category' => 'electronics', 'price' => 900, 'stock' => 5, 'is_active' => true]);
        $fine = Product::create(['seller_id' => $seller->id, 'name' => 'USB-C charger', 'category' => 'electronics', 'price' => 500, 'stock' => 5, 'is_active' => true]);
        $this->actingAs($this->admin);

        $page = $this->get(route('admin.compliance'))->assertOk()->assertSee('Canvas tote')->assertSee('Replica smart watch')->assertDontSee('USB-C charger');
        $page->assertSee('seller registered for Electronics')->assertSee('&quot;replica&quot;', false);

        $this->post(route('admin.compliance.warn', $prohibited), ['reason' => 'prohibited', 'details' => 'Replicas of branded watches cannot be sold.', 'action' => 'product_hidden'])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $prohibited->fresh()->is_active);
        $this->assertSame('product_hidden', ComplianceWarning::firstOrFail()->action);
        Notification::assertSentTo($seller, ComplianceWarningIssued::class);

        // The seller sees the warning in Seller Center and acknowledges it.
        $warning = ComplianceWarning::firstOrFail();
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk()->assertSee('Replicas of branded watches cannot be sold.');
        $this->post(route('seller.warnings.acknowledge', $warning))->assertRedirect();
        $this->assertNotNull($warning->fresh()->acknowledged_at);
        $this->get(route('seller.dashboard'))->assertDontSee('Replicas of branded watches cannot be sold.');

        // Restore, and finally suspend for a repeat violation.
        $this->actingAs($this->admin)->patch(route('admin.compliance.toggle', $prohibited))->assertRedirect();
        $this->assertTrue((bool) $prohibited->fresh()->is_active);
        $this->post(route('admin.compliance.warn', $offCategory), ['reason' => 'wrong_category', 'details' => 'Bags are outside your registered category.', 'action' => 'suspended'])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $seller->fresh()->status);
        Notification::assertSentTo($seller, AccountReviewed::class, fn ($notice) => $notice->status === 'suspended');
        $this->get(route('admin.compliance', ['view' => 'warnings']))->assertSee('Bags are outside your registered category.');
        $this->assertTrue((bool) $fine->fresh()->is_active);
    }

    public function test_commission_and_reports_follow_completed_sales(): void
    {
        $this->order('delivered', 200000);
        $this->order('shipped', 50000);
        $this->order('cancelled', 30000);
        $this->actingAs($this->admin);

        $this->get(route('admin.commissions'))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['commissionEarned'] === 200.0 && $summary['commissionPending'] === 50.0 && $summary['gross'] === 2500.0);
        $this->get(route('admin.reports'))->assertOk()
            ->assertSeeInOrder(['Gross', 'Net', 'Commission', 'Total orders'])
            ->assertSeeInOrder(['<th>Gross</th>', '<th>Net</th>', '<th>Commission</th>', '<th>Orders</th>'], false)
            ->assertViewHas('summary', fn ($summary) => $summary['net'] === $summary['gross'] - $summary['commission'])
            ->assertViewHas('summary', fn ($summary) => $summary['orders'] === 3 && $summary['completedOrders'] === 1 && $summary['cancelled'] === 1)
            ->assertViewHas('bySeller', fn ($rows) => $rows->count() === 3);
        $this->get(route('admin.reports', ['from' => '2026-10-10', 'to' => '2026-10-01']))->assertSessionHasErrors('from');
    }

    public function test_reports_can_be_viewed_and_downloaded_for_all_sellers_or_one(): void
    {
        $this->order('delivered', 200000);
        $this->order('shipped', 50000);
        $this->actingAs($this->admin);

        $page = $this->get(route('admin.reports'))->assertOk()
            ->assertSee('Download report')
            ->assertSee('action="'.route('admin.reports.download').'"', false)
            ->assertSee('<option value="">All sellers</option>', false)
            ->assertViewHas('sellers', fn ($sellers) => $sellers->count() === 2);
        $shopId = $page->viewData('bySeller')->firstWhere('sales', 500.0)['seller_id'];
        $shopName = $page->viewData('sellers')->get($shopId);

        // Sellers stay listed even when the chosen dates have none of their orders.
        $this->get(route('admin.reports', ['from' => '2020-01-01', 'to' => '2020-01-31']))->assertOk()
            ->assertViewHas('sellers', fn ($sellers) => $sellers->count() === 2);

        $this->get(route('admin.reports', ['seller' => $shopId]))->assertOk()
            ->assertSee($shopName.' · reports')
            ->assertSee('All sellers')
            ->assertSee(route('admin.reports', ['from' => now('Asia/Manila')->subDays(29)->toDateString(), 'to' => now('Asia/Manila')->toDateString()]))
            // No print or PDF shortcuts at the top: reports come only from "Download report", with this seller preselected.
            ->assertDontSee('data-print', false)->assertDontSee(route('admin.reports.pdf', ['report' => 'sales']), false)
            ->assertSee('<option value="'.$shopId.'" selected>', false)
            ->assertViewHas('summary', fn ($summary) => $summary['gross'] === 500.0 && $summary['orders'] === 1);

        foreach (['sales', 'commission'] as $report) {
            $all = $this->get(route('admin.reports.download', ['report' => $report]))->assertOk();
            $this->assertStringContainsString("zaylo-{$report}-report-20", $all->headers->get('content-disposition'));
            $one = $this->get(route('admin.reports.download', ['report' => $report, 'seller' => $shopId, 'from' => '2026-01-01']))->assertOk();
            $this->assertStringContainsString("zaylo-{$report}-report-shop-", $one->headers->get('content-disposition'));
            $this->assertStringContainsString('-2026-01-01-to-', $one->headers->get('content-disposition'));
        }
        $this->get(route('admin.reports.download', ['report' => 'payroll']))->assertSessionHasErrors('report');
    }

    public function test_commission_shows_gross_net_commission_and_orders_for_all_or_one_seller(): void
    {
        $this->order('delivered', 200000);
        $this->order('shipped', 50000);
        $this->actingAs($this->admin);

        $all = $this->get(route('admin.commissions'))->assertOk()
            ->assertSeeInOrder(['Gross', 'Net', 'Commission', 'Total orders'])
            ->assertSee('View report')
            ->assertViewHas('summary', fn ($summary) => $summary['gross'] === 2500.0 && $summary['commission'] === 250.0 && $summary['net'] === 2250.0 && $summary['orders'] === 2)
            ->assertViewHas('bySeller', fn ($rows) => $rows->count() === 2 && $rows->every(fn ($row) => $row['net'] === $row['sales'] - $row['commission']));
        $shopId = $all->viewData('bySeller')->firstWhere('sales', 500.0)['seller_id'];

        $this->get(route('admin.commissions', ['seller' => $shopId]))->assertOk()
            ->assertSee('All sellers')
            ->assertViewHas('seller', fn ($seller) => $seller['id'] === $shopId)
            ->assertViewHas('summary', fn ($summary) => $summary['gross'] === 500.0 && $summary['commission'] === 50.0 && $summary['net'] === 450.0 && $summary['orders'] === 1)
            ->assertViewHas('sellers', fn ($sellers) => $sellers->count() === 2);

        $pdf = $this->get(route('admin.reports.pdf', ['report' => 'commission', 'seller' => $shopId]))->assertOk();
        $this->assertStringContainsString('-report-shop-', $pdf->headers->get('content-disposition'));
        $this->get(route('admin.commissions', ['seller' => 'abc']))->assertSessionHasErrors('seller');
    }

    public function test_announcements_reach_their_audience_and_policies_are_editable(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($this->admin);
        $this->post(route('admin.announcements.store'), ['title' => 'Holiday deliveries', 'body' => 'Orders after Dec 20 ship in January.', 'audience' => 'buyer', 'is_published' => 1])->assertSessionHasNoErrors();
        $this->post(route('admin.announcements.store'), ['title' => 'Seller fee update', 'body' => 'Commission stays at 10%.', 'audience' => 'seller', 'is_published' => 1])->assertSessionHasNoErrors();

        // My Orders only runs on the deployed database layout, so the check uses another storefront page.
        $this->actingAs($buyer)->get(route('support'))->assertSee('Holiday deliveries')->assertDontSee('Seller fee update');
        $this->actingAs($this->admin)->patch(route('admin.announcements.update', Announcement::where('audience', 'buyer')->first()), ['toggle' => 1]);
        $this->actingAs($buyer)->get(route('support'))->assertDontSee('Holiday deliveries');

        // Until the admin writes them, every policy page shows its built-in text, which the editor starts from.
        $this->get(route('policies.show', 'privacy'))->assertOk()->assertSee('Data Privacy Act of 2012');
        $this->actingAs($this->admin)->get(route('admin.policies.edit', 'privacy'))->assertSee('National Privacy Commission');
        $this->get(route('policies.show', 'returns'))->assertOk()->assertSee('Report a problem');
        $this->get(route('policies.show', 'prohibited'))->assertOk()->assertSee('Always prohibited');
        $this->get(route('policies.show', 'seller'))->assertOk()->assertSee('10% of the item amount');
        $this->get(route('policies.show', 'shipping'))->assertNotFound();

        $this->actingAs($this->admin)->put(route('admin.policies.update', 'privacy'), [
            'title' => 'Privacy Policy',
            'body' => "## What we collect\n- Your name and <b>address</b>\n- Your orders\n\nWe never sell your data to third parties.",
        ])->assertRedirect(route('admin.settings'));
        $this->get(route('policies.show', 'privacy'))->assertOk()
            ->assertSee('<h2>What we collect</h2>', false)->assertSee('&lt;b&gt;address&lt;/b&gt;', false)->assertDontSee('<b>address</b>', false);
        $this->get(route('terms'))->assertOk()->assertSee('Terms of Service')->assertSee('1. Who can use ZAYLO');
        PlatformPolicy::create(['slug' => 'terms', 'title' => 'ZAYLO Terms 2027', 'body' => str_repeat('Use the marketplace fairly. ', 3)]);
        $this->get(route('terms'))->assertSee('ZAYLO Terms 2027');
    }

    public function test_users_and_admins_message_each_other_through_support(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Bea Buyer']);
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $this->actingAs($buyer)->get(route('support'))->assertOk()->assertSee('ZAYLO support team');
        $thread = SupportThread::where('user_id', $buyer->id)->firstOrFail();
        $this->postJson(route('support.send', $thread), ['body' => 'I cannot change my address.'])->assertCreated();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('unread support messages');
        $this->get(route('admin.chat', ['thread' => $thread->id]))->assertOk()->assertSee('I cannot change my address.');
        $this->postJson(route('support.send', $thread), ['body' => 'Open Account, then Delivery addresses.'])->assertCreated();

        $this->actingAs($buyer)->getJson(route('support.messages', $thread))->assertOk()
            ->assertJsonPath('messages.1.sender', 'ZAYLO support')->assertJsonPath('messages.1.mine', false);
        $this->actingAs($other)->getJson(route('support.messages', $thread))->assertForbidden();

        // Admins can start a conversation with anyone from their profile.
        $this->actingAs($this->admin)->post(route('admin.chat.start'), ['participant_id' => $other->id])->assertRedirect();
        $this->assertTrue(SupportThread::where('user_id', $other->id)->exists());
    }

    public function test_admins_and_users_can_send_photos_in_support_messages(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Bea Buyer']);
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $thread = SupportThread::create(['user_id' => $buyer->id]);

        $this->actingAs($this->admin)->get(route('admin.chat', ['thread' => $thread->id]))->assertOk()
            ->assertSee('data-chat-photo', false)->assertSee('enctype="multipart/form-data"', false);

        // A photo alone is a message; it is re-encoded and kept on the private disk.
        $sent = $this->post(route('support.send', $thread), ['photo' => UploadedFile::fake()->image('damaged-box.jpg', 2400, 1800)], ['Accept' => 'application/json'])
            ->assertCreated()->json('message');
        $message = SupportMessage::findOrFail($sent['id']);
        $this->assertSame('', $message->body);
        Storage::disk('local')->assertExists($message->image_path);
        $this->assertStringStartsWith("support/{$thread->id}/", $message->image_path);
        $this->assertSame(route('support.photo', [$thread, $message]), $sent['photo']);
        [$width] = getimagesizefromstring(Storage::disk('local')->get($message->image_path));
        $this->assertSame(1600, $width);

        // Text and a photo together, from the user's Help & support page.
        $this->actingAs($buyer)->get(route('support'))->assertOk()->assertSee('data-chat-photo', false)->assertSee($sent['photo']);
        $this->post(route('support.send', $thread), ['body' => 'Here is the receipt.', 'photo' => UploadedFile::fake()->image('receipt.png', 400, 600)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('message.body', 'Here is the receipt.');
        $this->get($sent['photo'])->assertOk();

        // Only the people in the conversation can open the photo; empty messages and other files are refused.
        $this->actingAs($other)->get($sent['photo'])->assertForbidden();
        $this->actingAs($buyer)->postJson(route('support.send', $thread), [])->assertJsonValidationErrors('body');
        $this->post(route('support.send', $thread), ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('photo');

        // The admin's list and notifications describe a photo-only message.
        $this->actingAs($this->admin)->get(route('admin.chat'))->assertSee('Here is the receipt.');
        $this->assertSame('📷 Photo', $message->preview);
    }

    public function test_account_decisions_and_deactivation_are_emailed(): void
    {
        $partner = User::factory()->create(['role' => 'logistics', 'status' => 'pending']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($this->admin);

        $this->patch(route('admin.users.status', $partner), ['status' => 'active'])->assertSessionHasNoErrors();
        Notification::assertSentTo($partner, AccountReviewed::class, fn ($notice) => $notice->status === 'active');

        $this->patch(route('admin.users.status', $buyer), ['status' => 'deactivated', 'reason' => 'Closed at the owner\'s request.'])->assertSessionHasNoErrors();
        $this->assertSame('deactivated', $buyer->fresh()->status);
        Notification::assertSentTo($buyer, AccountReviewed::class, fn ($notice) => $notice->status === 'deactivated' && str_contains($notice->reason, 'owner'));
        $this->actingAs($buyer->fresh())->get(route('support'))->assertRedirect();

        $this->actingAs($this->admin)->patch(route('admin.users.status', $buyer), ['status' => 'active'])->assertSessionHasNoErrors();
        Notification::assertSentTo($buyer, AccountReviewed::class, fn ($notice) => $notice->status === 'active');
    }

    /** A one-seller order on the newer schema. Returns [order, seller user]. */
    private function order(string $status, int $subtotalMinor = 100000): array
    {
        $sellerUser = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $shop = $sellerUser->sellers()->create(['name' => 'Shop '.$sellerUser->id, 'slug' => 'shop-'.$sellerUser->id, 'status' => 'approved']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = Order::create([
            'buyer_id' => $buyer->id, 'reference' => 'ZAY-'.$sellerUser->id, 'total_minor' => $subtotalMinor,
            'payment_method' => 'cod', 'shipping_address' => ['recipient' => 'Juan', 'city' => 'Pasig City', 'province' => 'Metro Manila'],
        ]);
        SellerOrder::create([
            'order_id' => $order->id, 'seller_id' => $shop->id, 'subtotal_minor' => $subtotalMinor,
            'shipping_fee_minor' => 0, 'commission_minor' => (int) round($subtotalMinor * 0.1), 'status' => $status,
        ]);

        return [$order->fresh(), $sellerUser];
    }

    private function application(User $seller, string $category): void
    {
        SellerApplication::create([
            'user_id' => $seller->id, 'shop_name' => 'Shop '.$seller->id, 'shop_category' => $category, 'seller_type' => 'individual',
            'tin' => '123-456-789-'.str_pad((string) $seller->id, 3, '0', STR_PAD_LEFT), 'region_code' => '130000000', 'region_name' => 'NCR',
            'city_code' => '137400000', 'city_name' => 'Pasig', 'barangay_code' => '137401001', 'barangay_name' => 'Kapitolyo',
            'street_address' => '1 Main St', 'postal_code' => '1603', 'status' => 'approved',
        ]);
    }
}
