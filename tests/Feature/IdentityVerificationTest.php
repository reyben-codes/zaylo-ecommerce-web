<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class IdentityVerificationTest extends TestCase
{
    use RefreshDatabase, PsgcFixtures;

    public function test_unverified_buyer_can_shop_but_not_check_out_until_an_admin_approves_their_id(): void
    {
        Storage::fake('local');
        $buyer = $this->buyer();
        $product = $this->product();

        $this->actingAs($buyer)->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        $this->get(route('buyer.account'))->assertOk()->assertSee('ID not verified yet')->assertSee('Submit ID for review');
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
        $this->actingAs($admin)->get(route('admin.identity'))->assertOk()->assertSee($buyer->name)->assertSee('PhilSys National ID');
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

    private function product(): Product
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);

        return Product::create(['seller_id' => $seller->id, 'name' => 'ID Test Lamp', 'category' => 'home_living', 'price' => 500, 'stock' => 5, 'is_active' => true]);
    }
}
