<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase, PsgcFixtures;

    public function test_buyers_without_an_id_can_order_everything_except_18_plus_products(): void
    {
        $buyer = $this->buyer();
        $everyday = $this->product();
        $adult = $this->product(adult: true, name: 'Craft Beer Set');
        $this->actingAs($buyer)->post(route('buyer.cart.add', $everyday), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('buyer.cart.add', $adult), ['quantity' => 1])->assertSessionHasNoErrors();
        $items = $buyer->cart()->first()->items()->get();
        $everydayItem = $items->first(fn (CartItem $item) => $item->product?->is($everyday))->id;
        $adultItem = $items->first(fn (CartItem $item) => $item->product?->is($adult))->id;
        $address = $buyer->addresses()->first()->id;

        // Buyers see the 18+ badge before they buy.
        $this->get(route('products.show', $adult))->assertOk()->assertSee('18+')->assertSee('need an approved ID to order it');
        $this->get(route('products.show', $everyday))->assertOk()->assertDontSee('class="adult-badge"', false);

        // An order with an 18+ product is refused and names it; checkout says so before Place order.
        $this->post(route('buyer.cart.selection'), ['action' => 'checkout', 'cart_item_ids' => [$everydayItem, $adultItem]]);
        $this->get(route('buyer.checkout.show'))->assertOk()->assertSee('18+ items need an approved ID')->assertSee('Craft Beer Set is for adults only');
        $this->post(route('buyer.checkout'), ['cart_item_ids' => [$everydayItem, $adultItem], 'address_id' => $address, 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors(['identity' => 'Some items are for adults (18+): Craft Beer Set. Your ID needs to be approved before you can order them. Upload it in Profile Settings, or leave them out of this order.']);
        $this->assertSame(0, Order::count());

        // Everything else checks out without an ID: no notice, and Place order is not held back.
        $this->post(route('buyer.cart.selection'), ['action' => 'checkout', 'cart_item_ids' => [$everydayItem]]);
        $this->get(route('buyer.checkout.show'))->assertOk()->assertDontSee('18+ items need an approved ID')
            ->assertDontSee('18+ items need an approved ID before you can order them.');
        $checkout = app(CheckoutService::class);
        $cartItems = $buyer->cart()->first()->items()->with('product')->get();
        $checkout->ensureCanOrder($buyer, $cartItems->whereIn('id', [$everydayItem]));
        $this->assertSame(['Craft Beer Set'], $checkout->itemsNeedingIdentity($buyer, $cartItems)->pluck('product.name')->all());

        // With an approved ID, 18+ products can be ordered too.
        $buyer->forceFill(['identity_verified_at' => now()])->save();
        $this->assertCount(0, $checkout->itemsNeedingIdentity($buyer->fresh(), $cartItems));
        $checkout->ensureCanOrder($buyer->fresh(), $cartItems);
    }

    public function test_sellers_mark_18_plus_products_themselves_and_admins_review_the_change(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Wine Glass Set', 'category' => 'storage_bins', 'price' => 500, 'stock' => 5, 'is_active' => true]);
        $this->assertFalse($product->is_adult_only, 'Products are not 18+ unless the seller says so.');

        $this->actingAs($seller)->get(route('seller.products.edit', $product))->assertOk()->assertSee('18+ product (for adults only)');
        $payload = ['name' => $product->name, 'categories' => ['storage_bins'], 'price' => 500, 'stock' => 5, 'is_active' => 1];
        $this->put(route('seller.products.update', $product), $payload + ['is_adult' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->is_adult_only);
        if (Product::usesReview()) {
            $this->assertTrue($product->fresh()->is_pending_review, 'Marking a product 18+ sends it back for review.');
        }

        // A form without the box leaves the mark alone; unticking it clears it.
        $this->put(route('seller.products.update', $product), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->is_adult_only);
        $this->put(route('seller.products.update', $product), $payload + ['is_adult' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->is_adult_only);
    }

    public function test_an_18_plus_product_needs_an_admin_approved_id(): void
    {
        Storage::fake('local');
        $buyer = $this->buyer();
        $product = $this->product(adult: true);

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->get(route('buyer.account'))->assertOk()->assertSee('ID not verified (only for 18+ items)')->assertSee('Submit ID for review');
        $this->post(route('buyer.checkout'), ['address_id' => $buyer->addresses()->first()->id, 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('identity');

        $this->post(route('buyer.identity.store'), ['id_type' => 'philid', 'document' => UploadedFile::fake()->create('front.jpg', 120, 'image/jpeg')])
            ->assertRedirect()->assertSessionHasNoErrors();
        $submission = IdentityVerification::where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame('pending', $submission->status);
        Storage::disk('local')->assertExists($submission->document_path);
        $this->assertStringStartsWith("identity-documents/{$buyer->id}/", $submission->document_path);
        $this->get(route('buyer.account'))->assertSee('Under review');

        // Buyers cannot open the review area or documents; admins can.
        $this->get(route('admin.identity.document', $submission))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.identity'))->assertOk()->assertSee($buyer->name)->assertSee('PhilSys National ID')
            ->assertSee('js/document-viewer.js', false)->assertSee('data-document-viewer', false)->assertSee('data-document-type="image"', false);
        $this->get(route('admin.identity.document', $submission))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $this->get(route('admin.identity.document', $submission))->headers->get('Cache-Control'));
        $this->get(route('admin.dashboard'))->assertSee('1 buyer ID waiting for review');

        $this->patch(route('admin.identity.review', $submission), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertNotNull($buyer->fresh()->identity_verified_at);
        $this->patch(route('admin.identity.review', $submission), ['decision' => 'reject', 'reason' => 'Late'])->assertSessionHasErrors('decision');

        $this->actingAs($buyer->fresh())->get(route('buyer.account'))->assertSee('Your ID is verified')->assertDontSee('Submit ID for review');
        $this->post(route('buyer.identity.store'), ['id_type' => 'philid', 'document' => UploadedFile::fake()->create('again.jpg', 120, 'image/jpeg')])
            ->assertSessionHasErrors('document');
    }

    public function test_rejected_buyer_sees_the_reason_and_can_upload_again(): void
    {
        Storage::fake('local');
        $buyer = $this->buyer();
        $this->actingAs($buyer)->post(route('buyer.identity.store'), ['id_type' => 'passport', 'document' => UploadedFile::fake()->create('blurry.jpg', 120, 'image/jpeg')]);
        $submission = IdentityVerification::firstOrFail();
        $firstFile = $submission->document_path;

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.identity.review', $submission), ['decision' => 'reject'])->assertSessionHasErrors('reason');
        $this->patch(route('admin.identity.review', $submission), ['decision' => 'reject', 'reason' => 'The photo is too blurry to read.'])->assertSessionHasNoErrors();
        $this->assertNull($buyer->fresh()->identity_verified_at);

        $this->actingAs($buyer)->get(route('buyer.account'))->assertSee('Not approved')->assertSee('The photo is too blurry to read.')->assertSee('Upload new ID');
        $this->post(route('buyer.identity.store'), ['id_type' => 'passport', 'document' => UploadedFile::fake()->create('scan.pdf', 300, 'application/pdf')])
            ->assertSessionHasNoErrors();

        // The same submission is reopened with the new file; the old file is removed.
        $this->assertSame(1, IdentityVerification::count());
        $this->assertSame('pending', $submission->fresh()->status);
        Storage::disk('local')->assertMissing($firstFile);
    }

    public function test_uploads_must_be_a_supported_id_file(): void
    {
        Storage::fake('local');
        $buyer = $this->buyer();

        $this->actingAs($buyer)->post(route('buyer.identity.store'), ['id_type' => 'library_card', 'document' => UploadedFile::fake()->create('id.jpg', 120, 'image/jpeg')])->assertSessionHasErrors('id_type');
        $this->post(route('buyer.identity.store'), ['id_type' => 'philid', 'document' => UploadedFile::fake()->create('id.exe', 10)])->assertSessionHasErrors('document');
        $this->post(route('buyer.identity.store'), ['id_type' => 'philid', 'document' => UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg')])->assertSessionHasErrors('document');
        $this->assertSame(0, IdentityVerification::count());
    }

    private function buyer(): User
    {
        $buyer = User::factory()->identityUnverified()->create(['role' => 'buyer', 'status' => 'active']);
        $buyer->addresses()->create($this->savedAddressData());

        return $buyer;
    }

    private function product(bool $adult = false, string $name = 'ID Test Lamp'): Product
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        return Product::create(['seller_id' => $seller->id, 'name' => $name, 'category' => 'storage_bins', 'price' => 500, 'stock' => 5, 'is_active' => true, 'is_adult' => $adult]);
    }
}
