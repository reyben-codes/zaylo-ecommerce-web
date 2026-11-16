<?php

namespace Tests\Feature;

use App\Models\IdentityVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class IdentityReplacementTest extends TestCase
{
    use RefreshDatabase, SignsInMobile;

    private function verifiedBuyer(): array
    {
        Storage::fake('local');
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'identity_verified_at' => now()]);
        Storage::disk('local')->put('identity-documents/'.$buyer->id.'/approved.jpg', 'private original');
        $approved = $buyer->identityVerifications()->create(['id_type' => 'philid',
            'document_path' => 'identity-documents/'.$buyer->id.'/approved.jpg', 'status' => 'approved', 'reviewed_at' => now()]);
        return [$buyer, $approved];
    }

    public function test_verified_buyer_can_replace_id_on_web_and_only_the_new_upload_can_be_approved(): void
    {
        [$buyer, $approved] = $this->verifiedBuyer();
        $this->actingAs($buyer)->get(route('buyer.account'))->assertOk()->assertSee('Change ID and re-verify');
        $this->post(route('buyer.identity.store'), ['id_type' => 'passport', 'document' => UploadedFile::fake()->create('replacement.pdf', 200, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertRedirect();
        $latest = $buyer->latestIdentityVerification()->firstOrFail();
        $this->assertGreaterThan($approved->id, $latest->id);
        $this->assertSame('pending', $latest->status);
        $this->assertSame('passport', $latest->id_type);
        $this->assertFalse($buyer->fresh()->isIdentityVerified());
        $adultItem = (new \App\Models\CartItem)->setRelation('product', new \App\Models\Product(['name' => 'Adult item', 'is_adult' => true]));
        try {
            app(\App\Services\CheckoutService::class)->ensureCanOrder($buyer->fresh(), collect([$adultItem]));
            $this->fail('Replacement must require a new approval for 18+ purchases.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('identity', $error->errors());
        }
        $this->assertSame('approved', $approved->fresh()->status);
        Storage::disk('local')->assertExists($approved->document_path);
        $this->get(route('admin.identity.document', $latest))->assertForbidden();
        $this->actingAs($buyer->fresh())->get(route('buyer.account'))->assertOk()->assertSee('Under review');
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.identity.review', $latest), ['decision' => 'approve', 'document_version' => $latest->reviewToken()])
            ->assertSessionHasNoErrors();
        $this->assertTrue($buyer->fresh()->isIdentityVerified());
        app(\App\Services\CheckoutService::class)->ensureCanOrder($buyer->fresh(), collect([$adultItem]));
    }

    public function test_invalid_replacement_preserves_the_approved_id_and_verification(): void
    {
        [$buyer, $approved] = $this->verifiedBuyer();
        $this->actingAs($buyer)->post(route('buyer.identity.store'), ['id_type' => 'passport', 'document' => UploadedFile::fake()->create('bad.exe', 10)])
            ->assertSessionHasErrors('document');
        $this->assertTrue($buyer->fresh()->isIdentityVerified());
        $this->assertDatabaseCount('identity_verifications', 1);
        Storage::disk('local')->assertExists($approved->document_path);
    }

    public function test_mobile_replacement_returns_unverified_pending_status_for_the_signed_in_user(): void
    {
        [$buyer, $approved] = $this->verifiedBuyer();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->signInMobile($buyer)->post('/api/mobile/account/identity', ['user_id' => $other->id, 'id_type' => 'passport',
            'document' => UploadedFile::fake()->create('new-id.pdf', 200, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('identity.verified', false)->assertJsonPath('identity.submission.status', 'pending')
            ->assertJsonPath('identity.submission.id_type', 'passport');
        $this->assertNull($buyer->fresh()->identity_verified_at);
        $this->assertSame(0, $other->identityVerifications()->count());
        $this->assertSame('approved', $approved->fresh()->status);
    }

    public function test_admin_cannot_approve_a_replaced_pending_document_from_a_stale_page(): void
    {
        [$buyer] = $this->verifiedBuyer();
        $this->actingAs($buyer)->post(route('buyer.identity.store'), ['id_type' => 'passport', 'document' => UploadedFile::fake()->create('first.pdf', 50, 'application/pdf')]);
        $pending = $buyer->latestIdentityVerification()->firstOrFail();
        $oldVersion = $pending->reviewToken();
        $this->post(route('buyer.identity.store'), ['id_type' => 'philid', 'document' => UploadedFile::fake()->create('second.pdf', 50, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->patch(route('admin.identity.review', $pending), ['decision' => 'approve', 'document_version' => $oldVersion])
            ->assertSessionHasErrors('decision');
        $this->assertFalse($buyer->fresh()->isIdentityVerified());
        $this->assertSame('pending', $pending->fresh()->status);
        $this->patch(route('admin.identity.review', $pending), ['decision' => 'approve', 'document_version' => $pending->fresh()->reviewToken()])
            ->assertSessionHasNoErrors();
        $this->assertTrue($buyer->fresh()->isIdentityVerified());
    }

    public function test_replacement_does_not_reopen_an_older_rejected_record_before_the_approved_one(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'identity_verified_at' => now()]);
        $rejected = $buyer->identityVerifications()->create(['id_type' => 'passport', 'document_path' => 'old/rejected.pdf', 'status' => 'rejected']);
        $approved = $buyer->identityVerifications()->create(['id_type' => 'philid', 'document_path' => 'old/approved.pdf', 'status' => 'approved']);
        $this->actingAs($buyer)->post(route('buyer.identity.store'), ['id_type' => 'passport', 'document' => UploadedFile::fake()->create('latest.pdf', 50, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertGreaterThan($approved->id, $buyer->latestIdentityVerification()->first()->id);
        $this->assertSame('pending', $buyer->latestIdentityVerification()->first()->status);
    }
}
