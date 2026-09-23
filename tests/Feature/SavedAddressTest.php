<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\TestCase;

class SavedAddressTest extends TestCase
{
    use PsgcFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeLocations();
    }

    public function test_user_can_manage_addresses_with_one_default(): void
    {
        $user = $this->buyer();
        $this->get(route('addresses.index'))->assertOk();
        $this->get(route('addresses.create'))->assertOk()->assertSee('data-address-form', false);
        $this->post(route('addresses.store'), $this->addressInput())->assertRedirect(route('addresses.index'));
        $first = $user->addresses()->firstOrFail();
        $this->assertTrue($first->is_default);
        $this->put(route('addresses.update', $first), $this->addressInput())->assertSessionHasNoErrors();
        $this->assertTrue($first->fresh()->is_default);
        $this->patch(route('addresses.default', $first))->assertRedirect();
        $this->assertTrue($first->fresh()->is_default);
        $this->assertSame('Alima', $first->barangay);
        $this->assertSame($user->id, $first->user->id);
        $this->post(route('addresses.store'), $this->addressInput(['label' => 'Work']))->assertSessionHasNoErrors();
        $second = $user->addresses()->latest('id')->firstOrFail();
        $this->assertFalse($second->is_default);
        $this->get(route('addresses.edit', $second))->assertOk()->assertSee('data-selected="042103001"', false);
        $this->put(route('addresses.update', $second), $this->addressInput(['line1' => 'New street', 'label' => 'School', 'is_default' => true]))->assertSessionHasNoErrors();
        $this->assertSame('New street', $second->fresh()->line1);
        $this->assertFalse($first->fresh()->is_default);
        $this->patch(route('addresses.default', $first))->assertRedirect();
        $this->assertTrue($first->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->delete(route('addresses.destroy', $first))->assertRedirect(route('addresses.index'));
        $this->assertTrue($second->fresh()->is_default);
        $this->delete(route('addresses.destroy', $second))->assertRedirect();
        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_ncr_addresses_need_no_province_and_names_are_server_resolved(): void
    {
        $user = $this->buyer();
        $other = User::factory()->create();
        $this->post(route('addresses.store'), $this->addressInput([
            'region_code' => '130000000', 'province_code' => null,
            'city_municipality_code' => '137404000', 'barangay_code' => '137404001',
            'region_name' => 'Forged region', 'city' => 'Forged city', 'province' => 'Forged province',
            'barangay' => 'Forged barangay', 'user_id' => $other->id, 'country_code' => 'US',
        ]))->assertSessionHasNoErrors();
        $address = $user->addresses()->firstOrFail();
        $this->assertSame('National Capital Region', $address->region_name);
        $this->assertSame('Quezon City', $address->city);
        $this->assertSame('Alicia', $address->barangay);
        $this->assertNull($address->province_code);
        $this->assertSame('', $address->province);
        $this->assertSame('PH', $address->country_code);
        $this->assertSame(0, $other->addresses()->count());
    }

    public function test_mismatched_and_invalid_locations_are_rejected(): void
    {
        $user = $this->buyer();
        foreach ([
            ['region_code', '999999999'],
            ['province_code', '999999999'],
            ['city_municipality_code', '137404000'],
            ['barangay_code', '137404001'],
            ['region_code', '../regions'],
            ['postal_code', '12345'],
            ['label', 'Invalid'],
        ] as [$field, $value]) {
            $this->post(route('addresses.store'), $this->addressInput([$field => $value]))->assertSessionHasErrors($field);
        }
        $this->post(route('addresses.store'), $this->addressInput(['province_code' => null]))
            ->assertSessionHasErrors('city_municipality_code');
        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_province_free_localities_outside_ncr_are_supported(): void
    {
        $user = $this->buyer();
        Http::swap(new Factory);
        $base = config('locations.base_url').'/';
        Http::fake([
            $base.'regions/' => Http::response([['code' => '010000000', 'name' => 'Test region']]),
            $base.'regions/010000000/cities-municipalities/' => Http::response([
                ['code' => '013300000', 'name' => 'Test independent locality', 'provinceCode' => false],
                ['code' => '012801000', 'name' => 'Test province-bound city', 'provinceCode' => '012800000'],
            ]),
            $base.'cities-municipalities/013300000/barangays/' => Http::response([['code' => '013300001', 'name' => 'Test barangay']]),
        ]);
        $this->post(route('addresses.store'), $this->addressInput([
            'region_code' => '010000000', 'province_code' => null,
            'city_municipality_code' => '013300000', 'barangay_code' => '013300001',
        ]))->assertSessionHasNoErrors();
        $this->assertNull($user->addresses()->firstOrFail()->province_code);
        $this->post(route('addresses.store'), $this->addressInput([
            'region_code' => '010000000', 'province_code' => null, 'city_municipality_code' => '012801000',
        ]))->assertSessionHasErrors('city_municipality_code');
    }

    public function test_other_users_cannot_view_mutate_or_checkout_an_address(): void
    {
        $this->buyer();
        $other = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $address = $other->addresses()->create($this->savedAddressData());
        $this->get(route('addresses.index'))->assertDontSee('1 Test Street');
        $this->get(route('addresses.edit', $address))->assertForbidden();
        $this->put(route('addresses.update', $address), $this->addressInput())->assertForbidden();
        $this->patch(route('addresses.default', $address))->assertForbidden();
        $this->delete(route('addresses.destroy', $address))->assertForbidden();
        $this->post(route('buyer.checkout'), ['address_id' => $address->id, 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('address_id');
        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'user_id' => $other->id]);
        Http::assertNothingSent();
    }

    public function test_guests_unverified_and_inactive_users_cannot_manage_addresses(): void
    {
        $this->get(route('addresses.index'))->assertRedirect(route('login'));
        $this->post(route('addresses.store'), $this->addressInput())->assertRedirect(route('login'));
        $user = User::factory()->unverified()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($user)->get(route('addresses.index'))->assertRedirect(route('verification.notice'));
        $user->forceFill(['email_verified_at' => now(), 'status' => 'pending'])->save();
        $this->get(route('addresses.index'))->assertRedirect(route('login'));
    }

    public function test_all_marketplace_roles_can_manage_addresses_from_their_accounts(): void
    {
        foreach (['buyer', 'seller', 'courier'] as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            $this->actingAs($user)->get(route($role.'.account'))->assertOk()->assertSee(route('addresses.index'));
            $this->post(route('addresses.store'), $this->addressInput())->assertSessionHasNoErrors();
            $this->assertSame(1, $user->addresses()->count());
        }
    }

    public function test_service_failures_preserve_form_input_and_do_not_create_an_address(): void
    {
        $user = $this->buyer();
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([], 503)]);
        $this->from(route('addresses.create'))->post(route('addresses.store'), $this->addressInput())
            ->assertRedirect(route('addresses.create'))->assertSessionHasErrors('region_code')
            ->assertSessionHasInput('line1', '1 Test Street');
        $this->assertSame(0, $user->addresses()->count());
    }

    public function test_legacy_addresses_are_preserved_and_can_be_completed(): void
    {
        $user = $this->buyer();
        $data = $this->savedAddressData(['line2' => 'Unit 2']);
        foreach (['region_code', 'region_name', 'province_code', 'city_municipality_code', 'barangay_code'] as $key) {
            unset($data[$key]);
        }
        $address = $user->addresses()->create($data);
        $this->assertFalse($address->isStructured());
        $this->get(route('addresses.edit', $address))->assertOk()->assertSee('Unit 2');
        $this->put(route('addresses.update', $address), $this->addressInput(['line2' => 'Unit 2']))->assertSessionHasNoErrors();
        $this->assertTrue($address->fresh()->isStructured());
        $this->assertSame('Unit 2', $address->fresh()->line2);
    }

    public function test_checkout_uses_saved_snapshot_without_duplicates_or_changing_default(): void
    {
        $user = $this->buyer();
        $default = $user->addresses()->create($this->savedAddressData(['is_default' => true]));
        $chosen = $user->addresses()->create($this->savedAddressData(['label' => 'Work', 'line1' => 'Office street']));
        $this->fillCart();
        $this->get(route('buyer.cart'))->assertOk()->assertDontSee('name="address_id"', false);
        $this->get(route('buyer.checkout.show'))->assertOk()->assertSee('name="address_id"', false)->assertDontSee('name="line1"', false);
        $this->post(route('buyer.checkout'), [
            'address_id' => $chosen->id, 'idempotency_key' => (string) Str::uuid(),
            'recipient_name' => 'Forged recipient', 'phone' => '0000000', 'line1' => 'Forged street',
        ])->assertRedirect(route('buyer.orders'));
        $order = Order::firstOrFail();
        $this->assertSame('Test Buyer', $order->recipient_name);
        $this->assertSame($chosen->formatted(), $order->shipping_address);
        $this->assertSame(2, $user->addresses()->count());
        $this->assertTrue($default->fresh()->is_default);
        $this->patch(route('addresses.default', $chosen));
        $this->delete(route('addresses.destroy', $chosen));
        $this->assertSame($order->shipping_address, $order->fresh()->shipping_address);
        Http::assertNothingSent();
    }

    public function test_checkout_rejects_legacy_addresses_without_mutating_cart(): void
    {
        $user = $this->buyer();
        $address = $user->addresses()->create($this->savedAddressData(['barangay_code' => null]));
        $this->fillCart();
        $this->post(route('buyer.checkout'), ['address_id' => $address->id, 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_checkout_add_address_returns_to_checkout_with_saved_selection(): void
    {
        $user = $this->buyer();
        $this->post(route('addresses.store'), $this->addressInput(['return_to' => 'checkout']))
            ->assertRedirect(route('buyer.checkout.show'))->assertSessionHas('selected_address_id', $user->addresses()->first()->id);
    }

    private function buyer(): User
    {
        $user = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($user);

        return $user;
    }

    private function fillCart(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create([
            'seller_id' => $seller->id, 'name' => 'Test product', 'category' => 'electronics',
            'price' => 100, 'stock' => 5, 'is_active' => true,
        ]);
        $this->post(route('buyer.cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
    }
}
