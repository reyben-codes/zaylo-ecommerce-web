<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Destructive actions ask in the site-wide confirmation modal (public/js/confirm-dialog.js), never the browser's confirm().
 */
class ConfirmDialogTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_layout_loads_the_shared_modal(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Canvas Tote', 'category' => 'bags', 'price' => 650, 'stock' => 4, 'is_active' => true]);

        // Seller layout: archiving a product asks first.
        $this->actingAs($seller)->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('js/confirm-dialog.js', false)->assertSee('css/confirm-dialog.css', false)
            ->assertDontSee('logout-confirm.js', false)
            ->assertSee('data-confirm-title="Archive product?"', false)
            ->assertSee('data-confirm-tone="danger"', false)
            ->assertSee("data-confirm-if=\"input[name='remove_photos[]']:checked\"", false);

        // Store layout (buyers) and the workspace layout (admin) load it too.
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active']);
        $this->actingAs($buyer)->get(route('addresses.index'))->assertOk()
            ->assertSee('js/confirm-dialog.js', false)->assertDontSee('window.confirm', false);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->actingAs($admin)->get(route('admin.users'))->assertOk()->assertSee('js/confirm-dialog.js', false);
    }

    public function test_admin_account_actions_confirm_before_changing_an_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        User::factory()->create(['role' => 'buyer', 'status' => 'active', 'name' => 'Ana Reyes']);

        $this->actingAs($admin)->get(route('admin.users'))->assertOk()
            ->assertSee('data-confirm="Suspend Ana Reyes? They will be emailed and signed out."', false)
            ->assertSee('data-confirm-title="Suspend account?"', false);
    }

    public function test_no_page_uses_the_browsers_plain_confirm_box(): void
    {
        $files = array_merge(glob(resource_path('views/**/*.blade.php')) ?: [], glob(resource_path('views/*/*/*.blade.php')) ?: [], glob(public_path('js/*.js')) ?: []);
        foreach ($files as $file) {
            $this->assertStringNotContainsString('window.confirm(', file_get_contents($file), basename($file).' should use data-confirm instead.');
        }
        $this->assertFileDoesNotExist(public_path('js/logout-confirm.js'));
    }
}
