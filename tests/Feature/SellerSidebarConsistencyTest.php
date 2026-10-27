<?php

namespace Tests\Feature;

use App\Models\Product;
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

        $product = Product::create([
            'seller_id' => $seller->id, 'name' => 'Sidebar Sneaker', 'category' => 'shoes', 'price' => 100, 'stock' => 3, 'is_active' => true,
        ]);

        $pages = [
            route('seller.dashboard') => 'Dashboard',
            route('seller.orders') => 'Orders',
            route('seller.handover') => 'Delivery',
            route('seller.returns') => 'Returns',
            route('seller.inventory') => 'Inventory',
            route('seller.products') => 'Products',
            // Product sub-pages keep the Products section highlighted.
            route('seller.products.create') => 'Products',
            route('seller.products.edit', $product) => 'Products',
            route('seller.reports') => 'Reports',
            route('seller.chat') => 'Messages',
            route('seller.logistics-messages') => 'Logistics chat',
            route('support') => 'Help & support',
            route('seller.activity') => 'Activity log',
            route('seller.account') => 'Account',
        ];

        foreach ($pages as $url => $activeLabel) {
            $response = $this->get($url)->assertOk()->assertSee('css/seller-sidebar.css', false);
            $document = new DOMDocument();
            @$document->loadHTML($response->getContent());
            $xpath = new DOMXPath($document);

            $this->assertSame(1, $xpath->query('//aside[contains(concat(" ", normalize-space(@class), " "), " seller-sidebar ")]')->length);
            $this->assertSame(13, $xpath->query('//aside[contains(@class, "seller-sidebar")]//nav/a')->length);
            $this->assertSame(1, $xpath->query('//aside[contains(@class, "seller-sidebar")]//a[@aria-current="page" and normalize-space(.)="'.$activeLabel.'"]')->length);
            $this->assertSame(1, $xpath->query('//aside[contains(@class, "seller-sidebar")]//a[@aria-current="page"]')->length);
            $this->assertSame(1, $xpath->query('//aside[contains(@class, "seller-sidebar")]//form[@action="'.route('logout').'"]//button')->length);
        }
    }
}
