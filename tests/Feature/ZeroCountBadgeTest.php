<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZeroCountBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new \DOMXPath($document);
    }

    public function test_zero_badges_are_hidden_on_buyer_seller_admin_and_logistics_pages(): void
    {
        $pages = [
            'buyer' => ['/buyer/orders', '/buyer/notifications'],
            'seller' => ['/seller/orders', '/seller/pickups', '/seller/products', '/seller/returns', '/seller/products/sections'],
            'admin' => ['/admin/registrations', '/admin/identity', '/admin/disputes', '/admin/compliance'],
            'logistics' => ['/logistics/pickups', '/logistics/pickups?tab=scheduled', '/logistics/riders', '/logistics/couriers', '/logistics/deliveries', '/logistics/returns'],
        ];
        foreach ($pages as $role => $urls) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);
            if ($role === 'logistics') LogisticsProvider::create(['user_id' => $user->id, 'name' => 'Test Logistics', 'slug' => 'test-logistics', 'status' => 'approved']);
            foreach ($urls as $url) {
                $response = $this->actingAs($user)->get($url)->assertOk();
                $xpath = $this->document($response->getContent());
                foreach ($xpath->query('//span[not(@hidden)]') as $span) {
                    $class = $span->getAttribute('class');
                    $parentClass = $span->parentNode instanceof \DOMElement ? $span->parentNode->getAttribute('class') : '';
                    if (preg_match('/order-tab-count|pickup-badge|nav-item-count|sidebar__badge|account-update-count/', $class)
                        || preg_match('/workspace-tab|product-tab|order-tab/', $parentClass)) {
                        $this->assertDoesNotMatchRegularExpression('/^0(?:\s|$)/', trim($span->textContent), $url);
                    }
                }
            }
        }
    }

    public function test_positive_order_count_stays_visible_and_zero_tabs_keep_live_update_hooks(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $order = Order::create(['buyer_id' => $buyer->id, 'reference' => 'ZAY-COUNT-1', 'status' => 'awaiting_payment',
            'payment_method' => 'gcash', 'total_minor' => 10000, 'shipping_address' => ['city' => 'Calamba']]);
        $order->payment()->create(['method' => 'gcash', 'provider' => 'gcash', 'amount_minor' => 10000, 'status' => 'pending']);
        \App\Services\EscrowPayments::reserve($order);
        $response = $this->actingAs($buyer)->get('/buyer/orders')->assertOk();
        $xpath = $this->document($response->getContent());
        $positive = $xpath->query('//*[@data-order-count="to_pay"]')->item(0);
        $empty = $xpath->query('//*[@data-order-count="cancelled"]')->item(0);
        $this->assertSame('1', trim($positive->textContent));
        $this->assertFalse($positive->hasAttribute('hidden'));
        $this->assertTrue($empty->hasAttribute('hidden'));
        $this->assertSame('', trim($empty->textContent));
    }
}
