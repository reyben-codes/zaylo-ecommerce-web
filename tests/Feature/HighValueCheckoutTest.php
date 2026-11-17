<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\PsgcFixtures;
use Tests\Support\SignsInMobile;
use Tests\TestCase;

class HighValueCheckoutTest extends TestCase
{
    use RefreshDatabase, PsgcFixtures, SignsInMobile;

    #[\PHPUnit\Framework\Attributes\DataProvider('totals')]
    public function test_checkout_limit_and_mobile_quote(float $price, bool $blocked): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'High value item', 'category' => 'blenders', 'price' => $price, 'stock' => 5, 'is_active' => true]);
        $item = app(CartService::class)->add($buyer, $product, 1);
        $input = [
            'address_id' => $buyer->addresses()->create($this->savedAddressData())->id,
            'cart_item_ids' => [$item->id], 'payment_method' => 'cod', 'idempotency_key' => (string) Str::uuid(),
        ];
        $this->signInMobile($buyer)->postJson('/api/mobile/checkout/quote', ['cart_item_ids' => [$item->id]])
            ->assertOk()->assertJsonPath('cod_available', ! $blocked);
        $this->actingAs($buyer)->withSession(['checkout_item_ids' => [$item->id]])
            ->post(route('buyer.cart.selection'), ['action' => 'checkout', 'cart_item_ids' => [$item->id]])
            ->assertRedirect(route('buyer.checkout.show'));
        $page = $this->get(route('buyer.checkout.show'))->assertOk();
        if ($blocked) {
            $page->assertSee('value="cod"  disabled', false)->assertSee('value="gcash" checked', false);
            $this->post(route('buyer.checkout'), $input)->assertSessionHasErrors('payment_method');
        }
        $response = $this->postJson('/api/mobile/checkout', $input);
        if ($blocked) {
            $response->assertUnprocessable()->assertJsonValidationErrors('payment_method');
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('cart_items', 1);
            $this->assertSame(5, (int) $item->variant->fresh()->stock);
            $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
            config(['escrow.demo_enabled' => true, 'escrow.live' => false, 'escrow.admin_user_id' => $admin->id]);
            $input['payment_method'] = 'gcash';
            $order = app(CheckoutService::class)->place($buyer, [$item->id], $input, '')->sole();
            $this->assertSame('gcash', $order->payment_method);
            $this->assertSame('awaiting_payment', $order->status);
        } else {
            $response->assertCreated();
            $this->assertDatabaseCount('orders', 1);
        }
    }

    public static function totals(): array
    {
        return ['below' => [99879.99, false], 'exactly' => [99880, true], 'above' => [100000, true]];
    }
}
