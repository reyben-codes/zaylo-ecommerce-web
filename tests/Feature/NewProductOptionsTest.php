<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewProductOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_colors_and_sizes_are_saved_with_the_new_product(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()
            ->assertSee('Colors &amp; sizes', false)->assertSee('data-option-card-template', false);

        $this->post(route('seller.products.store'), $this->payload() + ['options' => [
            ['color' => ' Black ', 'sizes' => [
                ['size' => 'S', 'price' => 499, 'stock' => 4],
                ['size' => 'M', 'price' => '', 'stock' => 6],
                ['size' => '', 'price' => '', 'stock' => 0],
            ]],
            ['color' => 'Olive', 'sizes' => [['size' => '', 'price' => 450, 'stock' => 3]]],
            ['color' => '', 'sizes' => [['size' => '', 'price' => '', 'stock' => 0]]],
        ]])->assertSessionHasNoErrors()->assertRedirect();

        $tee = Product::where('name', 'Everyday Tee')->firstOrFail();
        $variants = $tee->variants()->orderBy('id')->get();
        $this->assertSame(['Black / S', 'Black / M', 'Olive'], $variants->pluck('name')->all());
        $this->assertSame(['color' => 'Black', 'size' => 'M'], $variants[1]->options);
        // A size left without a price uses the main price.
        $this->assertSame([499.0, 550.0, 450.0], $variants->map->regular_price->all());
        $this->assertSame([4, 6, 3], $variants->pluck('stock')->all());
        $this->assertSame(13, (int) $tee->variants()->sum('stock'));

        $this->assertTrue($tee->is_pending_review, 'buyers wait for an admin to approve it');
        $tee->forceFill(['review_status' => Product::REVIEW_APPROVED])->save(); // New listings wait for an admin.
        auth()->logout();
        $this->get(route('products.show', $tee))->assertOk()->assertSee('Olive')->assertSee('Black');
    }

    public function test_sizes_without_a_color_and_no_options_at_all(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller)->post(route('seller.products.store'), $this->payload() + ['options' => [
            ['color' => '', 'sizes' => [['size' => 'US 8', 'price' => 2000, 'stock' => 2], ['size' => 'US 9', 'price' => 2100, 'stock' => 1]]],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['US 8', 'US 9'], Product::firstOrFail()->variants()->orderBy('id')->pluck('name')->all());

        // Blank cards leave a single-item product with the main price and stock.
        $this->post(route('seller.products.store'), ['name' => 'Plain Mug'] + $this->payload() + ['options' => [
            ['color' => '', 'sizes' => [['size' => '', 'price' => '', 'stock' => 0]]],
        ]])->assertSessionHasNoErrors();
        $mug = Product::where('name', 'Plain Mug')->firstOrFail();
        $this->assertSame(1, $mug->variants()->count());
        $this->assertSame(9, $mug->stock);
    }

    public function test_invalid_colors_and_sizes_save_nothing(): void
    {
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active']);
        $this->actingAs($seller);
        $cases = [
            'unnamed color beside a named one' => [['color' => 'Black', 'sizes' => [['size' => 'S', 'price' => 1, 'stock' => 1]]], ['color' => '', 'sizes' => [['size' => 'M', 'price' => 1, 'stock' => 1]]]],
            'same color twice' => [['color' => 'Black', 'sizes' => [['size' => 'S']]], ['color' => 'black', 'sizes' => [['size' => 'M']]]],
            'same size twice' => [['color' => 'Black', 'sizes' => [['size' => 'S'], ['size' => 's']]]],
        ];
        foreach ($cases as $case => $options) {
            $this->post(route('seller.products.store'), $this->payload() + ['options' => $options])->assertSessionHasErrors('options', null, 'default');
        }
        // No main price and a size without one.
        $this->post(route('seller.products.store'), ['price' => ''] + $this->payload() + ['options' => [['color' => 'Red', 'sizes' => [['size' => 'L', 'stock' => 1]]]]])
            ->assertSessionHasErrors('options');
        $this->post(route('seller.products.store'), $this->payload() + ['options' => [['color' => 'Red', 'sizes' => [['size' => 'L', 'price' => -1]]]]])
            ->assertSessionHasErrors('options.0.sizes.0.price');
        $this->assertSame(0, Product::count());

        // The form comes back with what was typed.
        $this->post(route('seller.products.store'), $this->payload() + ['options' => $cases['same size twice']]);
        $this->get(route('seller.products.create'))->assertSee('value="Black"', false)->assertSee('Each size can only be listed once for Black.');
    }

    private function payload(): array
    {
        return ['name' => 'Everyday Tee', 'category' => 'clothing', 'price' => 550, 'stock' => 9, 'is_active' => 1];
    }
}
