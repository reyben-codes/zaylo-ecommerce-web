<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Services\PendingRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationUnapprovedReuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_unapproved_account_email_and_phone_can_be_used_again_for_registration(): void
    {
        $unapproved = User::factory()->create([
            'email' => 'unapproved@example.test',
            'phone' => '09171234567',
            'status' => 'pending',
            'role' => 'seller',
        ]);

        $response = $this->post('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'unapproved@example.test',
            'phone' => '09171234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
            'role' => 'buyer',
            'date_of_birth' => '1995-05-15',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pending_registrations', [
            'email' => 'unapproved@example.test',
        ]);
    }

    public function test_approved_account_email_and_phone_cannot_be_reused(): void
    {
        $approved = User::factory()->create([
            'email' => 'approved@example.test',
            'phone' => '09179998888',
            'status' => 'active',
            'role' => 'buyer',
        ]);

        $response = $this->post('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'approved@example.test',
            'phone' => '09179998888',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
            'role' => 'buyer',
            'date_of_birth' => '1995-05-15',
        ]);

        $response->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_completing_registration_replaces_old_unapproved_user_record(): void
    {
        $unapproved = User::factory()->create([
            'email' => 'pending.user@example.test',
            'phone' => '09171112222',
            'status' => 'pending',
            'role' => 'seller',
        ]);

        $service = app(PendingRegistrationService::class);
        $request = request();

        $user = $service->createImmediately($request, [
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'pending.user@example.test',
            'phone' => '09171112222',
            'password' => 'NewPassword123!',
            'role' => 'buyer',
            'date_of_birth' => '1995-05-15',
        ], null);

        $this->assertSame('New Name', $user->name);
        $this->assertSame('active', $user->status);
        $this->assertSame('buyer', $user->role);
        $this->assertDatabaseCount('users', 1);
    }
}
