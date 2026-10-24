<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LogisticsProvider;
use App\Models\Rider;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Activity logs: successful actions are recorded per account, failed ones are not, and each role
 * sees the right slice (admin: everyone; logistics: its team; seller, rider, buyer: themselves).
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_ins_failed_attempts_and_sign_outs_are_logged(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'email' => 'ada@example.com']);

        $this->post(route('admin.login.submit'), ['email' => 'ada@example.com', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        // The right password on the wrong portal is refused, so it is not a sign-in.
        $this->post(route('seller.login.submit'), ['email' => 'ada@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertSame(['auth.failed'], ActivityLog::pluck('event')->all());

        $this->post(route('admin.login.submit'), ['email' => 'ada@example.com', 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->post(route('logout'));
        $this->assertGuest();

        $this->assertSame(['auth.failed', 'admin.login.submit', 'logout'], ActivityLog::orderBy('id')->pluck('event')->all());
        $signOut = ActivityLog::latest('id')->first();
        $this->assertSame($admin->id, $signOut->user_id);
        $this->assertSame('admin', $signOut->actor_role);
        $this->assertSame('Signed out', $signOut->description);
        $this->assertNotNull($signOut->ip_address);
    }

    public function test_only_successful_actions_are_logged_and_passwords_are_never_kept(): void
    {
        [$owner, $provider] = $this->partner('Express Delivery');
        $rider = $this->rider($provider, 'Marco Rider');
        $this->actingAs($owner);

        $this->patch(route('logistics.riders.availability', $rider), ['available' => 'maybe'])->assertSessionHasErrors('available');
        $this->assertSame(0, ActivityLog::count());

        $this->patch(route('logistics.riders.availability', $rider), ['available' => '0'])->assertSessionHasNoErrors();
        $log = ActivityLog::sole();
        $this->assertSame('logistics.riders.availability', $log->event);
        $this->assertSame('riders', $log->category);
        $this->assertSame($provider->id, $log->logistics_provider_id);
        $this->assertSame([Rider::class, $rider->id], [$log->subject_type, $log->subject_id]);
        $this->assertSame('Rider Marco Rider', $log->subject_label);
        $this->assertSame('Marco Rider is paused and will not get new deliveries.', $log->result);
        $this->assertSame(['Available' => 'No'], $log->details());

        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->put(route('buyer.account.password'), [
            'current_password' => 'password', 'password' => 'N3w-Secret!pass', 'password_confirmation' => 'N3w-Secret!pass',
        ])->assertSessionHasNoErrors();
        $change = ActivityLog::where('user_id', $buyer->id)->sole();
        $this->assertSame('Changed password', $change->description);
        $this->assertStringNotContainsString('N3w-Secret', json_encode($change->getAttributes()));
    }

    public function test_each_role_sees_its_own_activity(): void
    {
        $logger = app(ActivityLogger::class);
        [$owner, $provider] = $this->partner('Express Delivery');
        [$otherOwner] = $this->partner('Other Couriers');
        $rider = $this->rider($provider, 'Marco Rider');
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'name' => 'Sam Seller']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $logger->record('logistics.parcels.sort', 'logistics', 'Sorted a parcel', $owner, null, ['area' => 'Pasig City']);
        $logger->record('courier.deliveries.update', 'deliveries', 'Updated a delivery', $rider->user, null, ['status' => 'delivered']);
        $logger->record('logistics.parcels.sort', 'logistics', 'Sorted a parcel for the rival', $otherOwner);
        $logger->record('seller.products.store', 'products', 'Added a product', $seller);
        $logger->record('buyer.checkout', 'orders', 'Placed an order', $buyer);

        $this->actingAs($owner)->get(route('logistics.activity'))->assertOk()
            ->assertSee('Sorted a parcel')->assertSee('Pasig City')->assertSee('Updated a delivery')->assertSee('Marco Rider')
            ->assertDontSee('for the rival')->assertDontSee('Added a product');
        $this->get(route('logistics.activity', ['role' => 'rider']))->assertOk()->assertSee('Updated a delivery')->assertDontSee('Sorted a parcel');
        $this->get(route('logistics.dashboard'))->assertSee(route('logistics.activity'), false);

        $this->actingAs($rider->user)->get(route('courier.activity'))->assertOk()
            ->assertSee('Updated a delivery')->assertDontSee('Sorted a parcel');
        $this->actingAs($seller)->get(route('seller.activity'))->assertOk()
            ->assertSee('Added a product')->assertDontSee('Placed an order');
        $this->actingAs($buyer)->get(route('buyer.account'))->assertOk()
            ->assertSee('Recent account activity')->assertSee('Placed an order')->assertDontSee('Added a product');

        $this->actingAs($admin)->get(route('admin.activity'))->assertOk()
            ->assertSee('for the rival')->assertSee('Added a product')->assertSee('Placed an order');
        $this->get(route('admin.activity', ['role' => 'seller']))->assertOk()->assertSee('Added a product')->assertDontSee('Placed an order');
        $this->get(route('admin.activity', ['search' => 'Pasig']))->assertOk()->assertSee('Sorted a parcel')->assertDontSee('Added a product');
        $this->get(route('admin.activity', ['category' => 'orders']))->assertOk()->assertSee('Placed an order')->assertDontSee('Added a product');
        $this->get(route('admin.users.show', $seller))->assertOk()->assertSee('Recent activity')->assertSee('Added a product');
        $this->get(route('admin.activity', ['user' => $seller->id]))->assertOk()->assertSee('Activity of Sam Seller')->assertDontSee('Placed an order');

        foreach (['seller.activity', 'logistics.activity', 'admin.activity', 'courier.activity'] as $page) {
            $this->actingAs($buyer)->get(route($page))->assertForbidden();
        }
    }

    public function test_every_logged_route_exists_and_has_a_known_category(): void
    {
        foreach (config('activity.routes') as $name => [$category]) {
            $this->assertTrue(Route::has($name), "Unknown route {$name} in config/activity.php");
            $this->assertArrayHasKey($category, config('activity.categories'), "Unknown category for {$name}");
        }
    }

    public function test_old_entries_are_pruned(): void
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        app(ActivityLogger::class)->record('logout', 'auth', 'Signed out', $user);
        ActivityLog::query()->update(['created_at' => now()->subDays(config('activity.retention_days') + 1)]);
        app(ActivityLogger::class)->record('logout', 'auth', 'Signed out', $user);

        $this->artisan('model:prune', ['--model' => [ActivityLog::class]])->assertSuccessful();

        $this->assertSame(1, ActivityLog::count());
    }

    private function partner(string $name): array
    {
        $owner = User::factory()->create(['role' => 'logistics', 'status' => 'active', 'email_verified_at' => now()]);
        $provider = LogisticsProvider::create(['user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'status' => 'approved']);

        return [$owner, $provider];
    }

    private function rider(LogisticsProvider $provider, string $name): Rider
    {
        $user = User::factory()->create(['role' => 'rider', 'status' => 'active', 'name' => $name]);

        return Rider::create(['user_id' => $user->id, 'logistics_provider_id' => $provider->id, 'vehicle_type' => 'Motorcycle', 'status' => 'approved', 'is_active' => true]);
    }
}
