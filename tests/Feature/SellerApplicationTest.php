<?php

namespace Tests\Feature;

use App\Models\SellerApplication;
use App\Models\User;
use App\Notifications\SellerApplicationReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CompletesSignUp;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class SellerApplicationTest extends TestCase
{
    use CompletesSignUp, PsgcFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->fakeLocations();
    }

    public function test_seller_signs_up_with_shop_address_and_documents(): void
    {
        $this->get(route('register', ['role' => 'seller']))->assertOk()
            ->assertSee('name="shop_name"', false)->assertSee('name="tin"', false)->assertSee('name="dti_certificate"', false)
            ->assertSee('enctype="multipart/form-data"', false);

        $this->post(route('register'), $this->signupData())->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));
        // Until the code is entered there is no account or application, only the parked documents.
        $this->assertDatabaseCount('seller_applications', 0);
        $this->assertDatabaseMissing('users', ['email' => 'mariaclara.santos@example.com']);
        $this->post(route('verification.verify'), ['otp' => $this->signUpCode('mariaclara.santos@example.com')])->assertRedirect(route('seller.application'));

        $user = User::where('email', 'mariaclara.santos@example.com')->firstOrFail();
        $application = $user->sellerApplication;
        $this->assertStringStartsWith("seller-applications/{$user->id}/", $application->government_id_path);
        $this->assertSame([], Storage::disk('local')->allFiles('pending-registrations'));
        $this->assertSame('pending', $user->status);
        $this->assertSame('Maria Clara Handicrafts', $application->shop_name);
        $this->assertSame('Maria Clara Handicrafts', $user->sellerStoreName());
        $this->assertSame('sole_proprietorship', $application->seller_type);
        $this->assertSame('123-456-789-000', $application->tin);
        // Place names come from the location service, not the form.
        $this->assertSame('Quezon City', $application->city_name);
        $this->assertSame('Alicia', $application->barangay_name);
        $this->assertNull($application->province_code);
        Storage::disk('local')->assertExists([$application->government_id_path, $application->dti_certificate_path, $application->bir_2303_path]);
        $this->assertNull($application->sec_certificate_path);
    }

    public function test_required_documents_follow_the_seller_type(): void
    {
        $corporation = $this->signupData(['seller_type' => 'corporation', 'email' => 'corp@example.com', 'phone' => '09170000002', 'shop_name' => 'RV Gadget Hub', 'tin' => '456-789-012-000']);
        unset($corporation['dti_certificate']);
        $this->post(route('register'), $corporation)->assertSessionHasErrors(['sec_certificate' => 'The SEC Certificate is required for corporations.']);
        $this->assertDatabaseMissing('users', ['email' => 'corp@example.com']);

        $individual = $this->signupData(['seller_type' => 'individual', 'email' => 'solo@example.com', 'phone' => '09170000003', 'shop_name' => 'Solo Crafts', 'tin' => '111-222-333-000']);
        unset($individual['dti_certificate'], $individual['bir_2303']);
        $solo = $this->signUp($individual);
        $this->assertSame(['government_id'], array_keys($solo->sellerApplication->documents()));
    }

    public function test_shop_name_tin_postal_code_and_files_are_validated(): void
    {
        $this->signUp($this->signupData());

        // Sign-up allows 6 attempts a minute, so each attempt moves the clock on.
        $second = function (array $overrides) {
            $this->travel(1)->minutes();

            return $this->post(route('register'), $this->signupData($overrides + ['email' => 'second@example.com', 'phone' => '09170000004', 'shop_name' => 'Another Shop', 'tin' => '222-333-444-000']));
        };
        $second(['shop_name' => 'MC'])->assertSessionHasErrors(['shop_name' => 'The shop name must be 3 to 30 characters.']);
        $second(['shop_name' => str_repeat('a', 31)])->assertSessionHasErrors('shop_name');
        $second(['shop_name' => 'maria clara handicrafts'])->assertSessionHasErrors(['shop_name' => 'This shop name is already taken.']);
        $second(['tin' => '123-456-789-00'])->assertSessionHasErrors(['tin' => 'Enter the TIN as 000-000-000-000 (12 digits).']);
        $second(['tin' => '123-456-78A-000'])->assertSessionHasErrors('tin');
        $second(['tin' => '123-456-789-000'])->assertSessionHasErrors(['tin' => 'This TIN is already registered.']);
        $second(['postal_code' => '112'])->assertSessionHasErrors(['postal_code' => 'Enter a 4-digit postal code.']);
        $second(['government_id' => UploadedFile::fake()->create('price_list.docx', 320)])->assertSessionHasErrors(['government_id' => 'Government-issued ID: upload a JPG, PNG or PDF file.']);
        $second(['government_id' => UploadedFile::fake()->create('size_5.1MB.jpg', 5222, 'image/jpeg')])->assertSessionHasErrors(['government_id' => 'Government-issued ID: the file must be 5 MB or smaller.']);
        $second(['barangay_code' => '042103001'])->assertSessionHasErrors('barangay_code');
        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);

        $second(['government_id' => UploadedFile::fake()->create('size_5.0MB.jpg', 5120, 'image/jpeg')])->assertSessionHasNoErrors();
    }

    public function test_pending_seller_sees_the_review_notice_instead_of_the_dashboard(): void
    {
        $seller = $this->appliedSeller();

        $this->post(route('seller.login.submit'), ['email' => $seller->email, 'password' => 'Tindahan@2026'])->assertRedirect(route('seller.application'));
        $this->get(route('seller.application'))->assertOk()->assertSee('Your application is under review')->assertSee('Maria Clara Handicrafts')->assertDontSee('Resubmit application');
        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.application'));
        $this->get(route('seller.products.create'))->assertRedirect(route('seller.application'));
    }

    public function test_admin_rejects_with_a_reason_and_the_seller_resubmits_then_is_approved(): void
    {
        $seller = $this->appliedSeller();
        $oldDti = $seller->sellerApplication->dti_certificate_path;
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($admin)->get(route('admin.registrations'))->assertOk()->assertSee('Home &amp; Living', false)->assertSee(route('admin.seller-applications.show', $seller));
        $this->get(route('admin.seller-applications.show', $seller))->assertOk()
            ->assertSee('123-456-789-000')->assertSee('Quezon City')->assertSee('DTI Certificate')->assertSee('Reason shown to the seller')
            // Documents open in the viewer modal; the link still works as a new tab without JavaScript.
            ->assertSee('js/document-viewer.js', false)->assertSee('data-document-gallery', false)->assertSee('data-document-title="DTI Certificate"', false);
        $this->get(route('admin.seller-applications.document', [$seller, 'dti_certificate']))->assertOk();
        $this->get(route('admin.seller-applications.document', [$seller, 'sec_certificate']))->assertNotFound();

        $this->patch(route('admin.users.status', $seller), ['status' => 'rejected'])->assertSessionHasErrors('reason');
        $reason = 'DTI certificate is blurred and the business name cannot be read.';
        $this->patch(route('admin.users.status', $seller), ['status' => 'rejected', 'reason' => $reason])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $seller->fresh()->status);
        $this->assertSame($reason, $seller->sellerApplication()->value('rejection_reason'));

        // A rejection is final until the seller resubmits: no Approve button, and a stale form or crafted request fails.
        $this->get(route('admin.users.show', $seller))->assertOk()
            ->assertSee('can be reviewed again once they resubmit')
            ->assertDontSee('name="status" value="active"', false);
        $this->get(route('admin.seller-applications.show', $seller))->assertOk()->assertDontSee('name="status" value="active"', false);
        foreach (['active', 'pending', 'suspended'] as $status) {
            $this->patch(route('admin.users.status', $seller), ['status' => $status])->assertSessionHasErrors('status');
        }
        $this->assertSame('rejected', $seller->fresh()->status);
        $this->assertNull($seller->fresh()->approved_at);
        $this->assertSame('rejected', $seller->sellerApplication()->value('status'));
        Notification::assertSentToTimes($seller, SellerApplicationReviewed::class, 1);
        Notification::assertSentTo($seller, SellerApplicationReviewed::class, fn ($notification) => ! $notification->approved && $notification->reason === $reason
            && str_contains(implode(' ', $notification->toMail($seller)->introLines), $reason));

        $this->post(route('logout'));
        $this->post(route('seller.login.submit'), ['email' => $seller->email, 'password' => 'Tindahan@2026'])->assertRedirect(route('seller.application'));
        $this->get(route('seller.application'))->assertSee('Your application was not approved')->assertSee($reason)->assertSee('Resubmit application');
        $this->get(route('seller.dashboard'))->assertRedirect(route('seller.application'));

        $resubmit = $this->applicationFields(['dti_certificate' => UploadedFile::fake()->create('dti_certificate_clear.pdf', 1024, 'application/pdf')]);
        unset($resubmit['government_id'], $resubmit['bir_2303']);
        $this->post(route('seller.application.update'), $resubmit)->assertSessionHasNoErrors()->assertRedirect(route('seller.application'));
        $application = $seller->sellerApplication()->first();
        $this->assertSame('pending', $seller->fresh()->status);
        $this->assertSame('pending', $application->status);
        $this->assertNull($application->rejection_reason);
        $this->assertNotSame($oldDti, $application->dti_certificate_path);
        Storage::disk('local')->assertMissing($oldDti);
        Storage::disk('local')->assertExists($application->government_id_path);

        $this->post(route('logout'));
        $this->actingAs($admin)->get(route('admin.registrations'))->assertSee($seller->email);
        $this->patch(route('admin.users.status', $seller), ['status' => 'active'])->assertSessionHasNoErrors();
        Notification::assertSentTo($seller, SellerApplicationReviewed::class, fn ($notification) => $notification->approved);
        $this->post(route('logout'));
        $this->post(route('seller.login.submit'), ['email' => $seller->email, 'password' => 'Tindahan@2026'])->assertRedirect(route('seller.dashboard'));
    }

    public function test_only_admins_see_applications_and_documents(): void
    {
        $seller = $this->appliedSeller();
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);

        $this->actingAs($buyer)->get(route('admin.seller-applications.show', $seller))->assertForbidden();
        $this->get(route('admin.seller-applications.document', [$seller, 'government_id']))->assertForbidden();
        $this->post(route('seller.application.update'), $this->applicationFields())->assertForbidden();
    }

    public function test_seller_without_an_application_is_asked_to_complete_it(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'pending', 'email_verified_at' => now()]);
        $seller->openSellerShop('Old Shop Name');

        $this->actingAs($seller)->get(route('seller.application'))->assertOk()->assertSee('Complete your seller application')->assertSee('Submit application');
        $this->post(route('seller.application.update'), $this->applicationFields(['shop_name' => 'Fresh Start Shop', 'tin' => '999-888-777-000']))->assertSessionHasNoErrors();
        $this->assertSame('Fresh Start Shop', $seller->fresh()->sellerStoreName());
        $this->get(route('seller.application'))->assertSee('Your application is under review');
    }

    private function appliedSeller(): User
    {
        return $this->signUp($this->signupData());
    }

    private function signupData(array $overrides = []): array
    {
        return array_replace([
            'role' => 'seller', 'first_name' => 'Maria Clara', 'last_name' => 'Santos', 'email' => 'mariaclara.santos@example.com',
            'phone' => '09189876543', 'password' => 'Tindahan@2026', 'password_confirmation' => 'Tindahan@2026', 'terms' => '1',
        ], $this->applicationFields(), $overrides);
    }

    private function applicationFields(array $overrides = []): array
    {
        return array_replace([
            'shop_name' => 'Maria Clara Handicrafts', 'shop_category' => 'home_living', 'seller_type' => 'sole_proprietorship', 'tin' => '123-456-789-000',
            'region_code' => '130000000', 'province_code' => '', 'city_code' => '137404000', 'barangay_code' => '137404001',
            'street_address' => 'Blk 12 Lot 8, Sampaguita St.', 'postal_code' => '1121',
            'government_id' => UploadedFile::fake()->create('govt_id_front.jpg', 1200, 'image/jpeg'),
            'dti_certificate' => UploadedFile::fake()->create('dti_certificate.pdf', 850, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf'),
        ], $overrides);
    }
}
