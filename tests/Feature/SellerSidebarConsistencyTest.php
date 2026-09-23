<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerSidebarConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_seller_section_uses_the_shared_sidebar(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller);

        $routes = [
            'seller.dashboard' => 'Dashboard',
            'seller.orders' => 'Orders',
            'seller.handover' => 'Handover',
            'seller.inventory' => 'Inventory',
            'seller.products' => 'Products',
            'seller.reports' => 'Reports',
            'seller.chat' => 'Messages',
            'seller.account' => 'Account',
        ];

        foreach ($routes as $route => $activeLabel) {
            $response = $this->get(route($route))->assertOk()->assertSee('css/seller-sidebar.css', false);
            $document = new DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new DOMXPath($document);

            $this->assertSame(1, $xpath->query('//aside[contains(concat(" ", normalize-space(@class), " "), " seller-sidebar ")]')->length);
            $this->assertSame(8, $xpath->query('//aside[contains(@class, "seller-sidebar")]//nav/a')->length);
            $this->assertSame(1, $xpath->query('//aside[contains(@class, "seller-sidebar")]//a[@aria-current="page" and normalize-space(.)="'.$activeLabel.'"]')->length);
            $this->assertSame(1, $xpath->query('//aside[contains(@class, "seller-sidebar")]//form[@action="'.route('logout').'"]//button')->length);
        }
    }
}
