<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductSaleController extends Controller
{
    /** A flash sale lasts at most this many hours. */
    public const FLASH_MAX_HOURS = 24;

    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);
        $flash = Product::tracksFlashSales() && $request->input('sale_kind') === 'flash';
        $data = $request->validate([
            'sale_kind' => 'nullable|in:regular,flash',
            'flash_quantity' => [Rule::excludeIf(! $flash || ! in_array($request->input('sale_type'), ['price', 'percent'], true)), 'required', 'integer', 'min:1', 'max:100000'],
            'sale_type' => 'nullable|in:price,percent',
            'sale_value' => 'exclude_unless:sale_type,price,percent|required|numeric|decimal:0,2|gt:0|max:99999999.99',
            'sale_starts_at' => 'exclude_unless:sale_type,price,percent|required|date_format:Y-m-d\TH:i',
            'sale_ends_at' => 'exclude_unless:sale_type,price,percent|required|date_format:Y-m-d\TH:i|after:sale_starts_at',
            'sale_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
        ]);

        DB::transaction(function () use ($product, $data, $flash) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            if (empty($data['sale_type'])) {
                $sale = array_fill_keys(['sale_type', 'sale_value', 'sale_starts_at', 'sale_ends_at', 'sale_variant_id'], null);
            } else {
                $variants = $locked->variants()->lockForUpdate()->get();
                if ($data['sale_type'] === 'percent' && (float) $data['sale_value'] >= 100) {
                    throw ValidationException::withMessages(['sale_value' => 'The discount must be less than 100%.']);
                }
                if ($data['sale_type'] === 'price') {
                    $prices = filled($data['sale_variant_id'] ?? null)
                        ? $variants->where('id', $data['sale_variant_id'])->pluck('regular_price')
                        : $variants->pluck('regular_price')->push((float) $locked->regular_price);
                    if ($prices->isEmpty() || (float) $data['sale_value'] >= $prices->min()) {
                        throw ValidationException::withMessages(['sale_value' => 'The sale price must be lower than every selected regular price. Use a percentage for variations with different prices.']);
                    }
                }
                $sale = Arr::only($data, ['sale_type', 'sale_value']);
                foreach (['sale_starts_at', 'sale_ends_at'] as $field) {
                    $sale[$field] = Carbon::createFromFormat('Y-m-d\TH:i', $data[$field], 'Asia/Manila')->startOfMinute()->utc();
                }
                $sale['sale_variant_id'] = $data['sale_variant_id'] ?? null;
                if ($flash) {
                    if ($sale['sale_starts_at']->diffInMinutes($sale['sale_ends_at']) > self::FLASH_MAX_HOURS * 60) {
                        throw ValidationException::withMessages(['sale_ends_at' => 'A flash sale can last up to '.self::FLASH_MAX_HOURS.' hours.']);
                    }
                    // A new or rescheduled flash sale starts counting its units again.
                    $restart = ! $locked->sale_is_flash || ! $locked->sale_starts_at?->eq($sale['sale_starts_at']);
                    $sale += [
                        'sale_is_flash' => true,
                        'flash_quantity' => (int) $data['flash_quantity'],
                        'flash_sold' => $restart ? 0 : min((int) $locked->flash_sold, (int) $data['flash_quantity']),
                    ];
                }
            }
            if (Product::tracksFlashSales() && empty($sale['sale_is_flash'])) {
                $sale += ['sale_is_flash' => false, 'flash_quantity' => null, 'flash_sold' => 0];
            }
            $locked->update($sale);
            // Retire the old manually entered comparison price without changing regular prices or stock.
            if (Product::usesLegacySchema()) {
                DB::table('products')->where('id', $locked->id)->update(['original_price' => null]);
            } else {
                $locked->defaultVariant?->update(['original_price_minor' => null]);
            }
        });

        return back()->with('status', match (true) {
            empty($data['sale_type']) => 'Sale removed. Regular prices apply.',
            $flash => 'Flash sale saved. It starts and ends automatically (Philippine time), or when its units sell out.',
            default => 'Sale saved. It starts and ends automatically (Philippine time).',
        });
    }
}
