<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SellerApplication;
use App\Models\User;
use App\Support\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The admin account page: every uploaded document and ID opens in the viewer, and a seller's shop can be inspected. */
class AdminAccountDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    public function test_seller_application_documents_are_listed_and_open(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'pending']);
        $this->application($seller, [
            'seller_type' => 'sole_proprietorship',
            'government_id_path' => $this->file('seller/id.jpg'),
            'dti_certificate_path' => $this->file('seller/dti.pdf'),
            'bir_2303_path' => $this->file('seller/bir.pdf'),
        ]);
        $this->actingAs($this->admin);

        $this->get(route('admin.users.show', $seller))->assertOk()
            ->assertSee('3 documents on file')
            ->assertSee('Government-issued ID')->assertSee('DTI Certificate')->assertSee('BIR Form 2303')
            ->assertSee(route('admin.seller-applications.document', [$seller, 'government_id']))
            ->assertSee('data-document-viewer', false);
        $this->get(route('admin.seller-applications.document', [$seller, 'bir_2303']))->assertOk();
    }

    public function test_logistics_partner_and_rider_documents_are_listed_and_open(): void
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'pending']);
        $provider = LogisticsProvider::create([
            'user_id' => $owner->id, 'name' => 'Swift Parcels', 'slug' => 'swift-parcels', 'status' => 'pending', 'contact_phone' => '09170000001',
            'sec_certificate_path' => $this->file('logistics/sec.pdf'), 'bir_2303_path' => $this->file('logistics/bir.pdf'),
            'business_permit_path' => $this->file('logistics/permit.pdf'), 'representative_id_path' => $this->file('logistics/id.jpg'),
        ]);
        $riderUser = User::factory()->create(['role' => 'courier', 'status' => 'active']);
        $rider = Rider::create([
            'user_id' => $riderUser->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved',
            'license_document_path' => $this->file('riders/license.jpg'), 'or_cr_document_path' => $this->file('riders/orcr.pdf'),
        ]);
        $this->actingAs($this->admin);

        $this->get(route('admin.users.show', $owner))->assertOk()
            ->assertSee('4 documents on file')
            ->assertSee('SEC Certificate')->assertSee('BIR Form 2303')->assertSee("Mayor&#039;s / Business Permit", false)
            ->assertSee('Valid ID of Authorized Representative');
        $this->get(route('admin.users.show', $riderUser))->assertOk()
            ->assertSee('2 documents on file')
            ->assertSee("Driver&#039;s license", false)->assertSee('Vehicle OR/CR')->assertSee('Swift Parcels')
            ->assertSee(route('admin.riders.document', [$rider, 'license_document']));

        $this->get(route('admin.riders.document', [$rider, 'or_cr_document']))->assertOk();
        $this->get(route('admin.riders.document', [$rider, 'password']))->assertNotFound();
        $this->actingAs($riderUser)->get(route('admin.riders.document', [$rider, 'license_document']))->assertForbidden();
    }

    public function test_admin_sees_a_sellers_products_and_opens_the_shop_while_it_is_active(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $live = $this->product($seller, 'Linen shirt');
        $pending = $this->product($seller, 'Canvas tote');
        $pending->submitForReview();
        $hidden = $this->product($seller, 'Old jacket', ['is_active' => false]);
        $this->actingAs($this->admin);

        $this->get(route('admin.users.show', $seller))->assertOk()
            ->assertSee('View shop')->assertSee(route('shops.show', Shop::idForSeller($seller)))
            ->assertSee('Shop products')
            ->assertSee(route('products.show', $live))
            ->assertSeeInOrder(['Canvas tote', 'Pending review'])
            ->assertSeeInOrder(['Old jacket', 'Hidden'])
            ->assertDontSee(route('products.show', $hidden));

        // A suspended seller has no public shop page, but the admin still sees the products.
        $seller->forceFill(['status' => 'suspended'])->save();
        $this->get(route('admin.users.show', $seller))->assertOk()
            ->assertDontSee('View shop')
            ->assertSee('The public shop page is offline while this account is not active.')
            ->assertSee('Linen shirt');
    }

    public function test_accounts_without_uploads_say_so(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($this->admin)->get(route('admin.users.show', $buyer))->assertOk()
            ->assertSee('No documents or IDs uploaded yet')
            ->assertDontSee('Shop products');
    }

    private function file(string $path): string
    {
        Storage::disk('local')->put($path, 'file');

        return $path;
    }

    private function product(User $seller, string $name, array $extra = []): Product
    {
        return Product::create($extra + ['seller_id' => $seller->id, 'name' => $name, 'category' => 'clothing', 'price' => 500, 'stock' => 5, 'is_active' => true]);
    }

    private function application(User $seller, array $documents): void
    {
        SellerApplication::create($documents + [
            'user_id' => $seller->id, 'shop_name' => 'Shop '.$seller->id, 'shop_category' => 'fashion',
            'tin' => '123-456-789-'.str_pad((string) $seller->id, 3, '0', STR_PAD_LEFT), 'region_code' => '130000000', 'region_name' => 'NCR',
            'city_code' => '137400000', 'city_name' => 'Pasig', 'barangay_code' => '137401001', 'barangay_name' => 'Kapitolyo',
            'street_address' => '1 Main St', 'postal_code' => '1603', 'status' => 'pending',
        ]);
    }
}
