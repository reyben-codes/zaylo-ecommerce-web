<?php

namespace Tests\Feature;

use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileRiderAccountAndSupportTest extends TestCase
{
    use RefreshDatabase;

    private function rider(): User
    {
        return User::factory()->create([
            'role' => 'rider', 'status' => 'active', 'phone' => '09556644889', 'password' => Hash::make('Zaylo#2026'),
        ]);
    }

    public function test_history_and_earnings_start_empty_with_every_period(): void
    {
        Sanctum::actingAs($this->rider());

        $this->getJson('/api/mobile/rider/history?period=today')
            ->assertOk()
            ->assertJsonPath('summary.deliveries', 0)
            ->assertJsonPath('next_page', null);
        $this->getJson('/api/mobile/rider/history?period=yesterday')->assertUnprocessable();

        $this->getJson('/api/mobile/rider/earnings')
            ->assertOk()
            ->assertJsonStructure(['periods' => ['today', 'week', 'month', 'all'], 'last_7_days'])
            ->assertJsonCount(7, 'last_7_days');
    }

    public function test_rider_updates_mobile_number_with_signup_rules(): void
    {
        User::factory()->create(['phone' => '09171234567']);
        Sanctum::actingAs($rider = $this->rider());

        $this->patchJson('/api/mobile/rider/account/phone', ['phone' => '09171234567'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone' => 'already registered']);
        $this->patchJson('/api/mobile/rider/account/phone', ['phone' => '19171234567'])
            ->assertUnprocessable()->assertJsonValidationErrors(['phone' => 'start with 09']);
        $this->patchJson('/api/mobile/rider/account/phone', ['phone' => '09556644889'])->assertOk(); // own number

        $this->patchJson('/api/mobile/rider/account/phone', ['phone' => '09998887777'])->assertOk();
        $this->assertSame('09998887777', $rider->fresh()->phone);
    }

    public function test_password_change_needs_the_current_password(): void
    {
        $rider = $this->rider();
        Sanctum::actingAs($rider);

        $this->putJson('/api/mobile/rider/account/password', [
            'current_password' => 'wrong', 'password' => 'Zaylo#2027', 'password_confirmation' => 'Zaylo#2027',
        ])->assertUnprocessable()->assertJsonValidationErrors(['current_password' => 'incorrect']);

        $this->putJson('/api/mobile/rider/account/password', [
            'current_password' => 'Zaylo#2026', 'password' => 'Zaylo#2027', 'password_confirmation' => 'Zaylo#2027',
        ])->assertOk();
        $this->assertTrue(Hash::check('Zaylo#2027', $rider->fresh()->password));
    }

    public function test_rider_and_support_staff_chat(): void
    {
        $rider = $this->rider();
        Sanctum::actingAs($rider);

        $clientId = '11111111-1111-4111-8111-111111111111';
        $this->postJson('/api/mobile/rider/support/messages', ['body' => 'The seller is closed.', 'client_id' => $clientId])->assertCreated();
        $this->postJson('/api/mobile/rider/support/messages', ['body' => 'The seller is closed.', 'client_id' => $clientId])->assertCreated();
        // The app's chat is the rider's Help & support thread, answered from the Admin Center inbox.
        $thread = SupportThread::where('user_id', $rider->id)->firstOrFail();
        $this->assertSame(1, $thread->messages()->count(), 'a retried send is stored once');

        $staff = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($staff)->get(route('admin.chat', ['thread' => $thread->id]))->assertOk()->assertSee('The seller is closed.');
        $this->actingAs($staff)->postJson(route('support.send', $thread), ['body' => 'We are calling the seller.'])->assertCreated();

        Sanctum::actingAs($rider);
        $this->getJson('/api/mobile/rider/support/unread')->assertJsonPath('unread', 1);
        $this->getJson('/api/mobile/rider/support')
            ->assertOk()
            ->assertJsonPath('messages.1.body', 'We are calling the seller.')
            ->assertJsonPath('messages.1.from_staff', true);
        $this->getJson('/api/mobile/rider/support/unread')->assertJsonPath('unread', 0);
    }
}
