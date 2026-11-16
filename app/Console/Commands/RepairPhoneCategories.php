<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductCategoryChecks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RepairPhoneCategories extends Command
{
    protected $signature = 'catalog:repair-phone-categories {--execute : Back up and correct misplaced iPhone device listings}';

    protected $description = 'Preview or correct iPhone device categories without changing prices, stock, images or options';

    public function handle(): int
    {
        $products = Product::where('name', 'like', '%iphone%')->get()->filter(fn ($product) =>
            ProductCategoryChecks::suggestion($product->name) === 'mobile_phones' && ProductCategoryChecks::forProduct($product));
        $this->table(['ID', 'Product', 'Current categories', 'Suggested'], $products->map(fn ($p) => [$p->id, $p->name, implode(', ', $p->category_keys), 'Mobile Phones'])->all());
        if (! $this->option('execute') || $products->isEmpty()) {
            $this->info($products->isEmpty() ? 'No misplaced iPhone device listings found.' : 'Preview only. Use --execute to correct these listings and return them for admin review.');
            return self::SUCCESS;
        }
        $path = storage_path('app/private/category-repairs/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(6)).'.json');
        DB::transaction(function () use ($products, $path) {
            $locked = Product::whereKey($products->modelKeys())->lockForUpdate()->get();
            $backup = $locked->map(fn ($p) => ['product' => $p->getRawOriginal(), 'additional_categories' => $p->additionalCategories->toArray()])->all();
            if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true)) throw new RuntimeException('Cannot create private backup directory.');
            $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) throw new RuntimeException('Category backup failed. Nothing was changed.');
            chmod($path, 0600);
            foreach ($locked as $product) {
                if (ProductCategoryChecks::suggestion($product->name) !== 'mobile_phones' || ! ProductCategoryChecks::forProduct($product)) continue;
                $product->update(['category' => 'mobile_phones', 'custom_tags' => null]);
                $product->syncAdditionalCategories([]);
                $product->submitForReview();
                $seller = Product::usesLegacySchema() ? $product->seller : $product->seller?->owner;
                if ($seller) app(\App\Services\AccountUpdates::class)->send($seller->id, 'phone-category-repair-'.$product->id, 'products',
                    'Product category corrected', $product->name.' was moved to Mobile Phones and is awaiting admin approval.', 'product', $product->id);
            }
        });
        $this->info('Phone categories corrected; listings are pending admin review. Prices, stock, images and options were preserved.');
        $this->line('Private backup: '.$path);
        return self::SUCCESS;
    }
}
