<?php

// Explicit catalog maintenance only; never run automatically during deployment.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

$missing = static function (?string $description): bool {
    $text = html_entity_decode(strip_tags($description ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', '', $text) === '';
};

if (in_array('--inventory', $argv, true)) {
    $count = 0;
    foreach (Product::orderBy('id')->lazyById() as $product) {
        if (! $missing($product->description)) continue;
        echo json_encode([
            'id' => $product->id, 'name' => $product->name, 'category' => $product->category_label,
            'variants' => $product->variants()->pluck('name')->all(),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
        $count++;
    }
    echo "Missing descriptions: {$count}".PHP_EOL;
    exit(0);
}

$descriptions = json_decode(file_get_contents(__DIR__.'/data/product-descriptions.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($descriptions as $id => $entry) {
    if (! ctype_digit((string) $id) || empty($entry['name']) || $missing($entry['description'] ?? null)) {
        throw new RuntimeException('Invalid description entry: '.$id);
    }
}
$execute = in_array('--execute', $argv, true);
set_time_limit(0);

DB::transaction(function () use ($descriptions, $execute, $missing) {
    $products = DB::table('products')->whereIn('id', [...array_keys($descriptions), 98])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    $updates = [];
    foreach ($descriptions as $id => $entry) {
        $product = $products->get($id);
        if (! $product) throw new RuntimeException('Product no longer exists: '.$id);
        if (! $missing($product->description)) continue;
        if ($product->name !== $entry['name']) throw new RuntimeException('Product name changed; review description for ID '.$id);
        $updates[$id] = ['description' => $entry['description']];
        echo 'Fill description: #'.$id.' '.$product->name.PHP_EOL;
    }
    $testProduct = $products->get(98);
    if ($testProduct) {
        if ($testProduct->name !== 'Balat ni Alice') throw new RuntimeException('Test product identity changed. Nothing updated.');
        if ($testProduct->archived_at === null || $testProduct->is_active) {
            $updates[98] = ['archived_at' => $testProduct->archived_at ?? now(), 'is_active' => false];
            echo 'Archive test listing: #98 Balat ni Alice'.PHP_EOL;
        }
    }
    if (! $execute) {
        echo 'Preview only: '.count($updates).' product updates. Use --execute to apply.'.PHP_EOL;
        return;
    }
    if ($updates === []) {
        echo 'No changes needed.'.PHP_EOL;
        return;
    }

    $directory = storage_path('app/private/product-description-backups');
    if (! is_dir($directory) && ! mkdir($directory, 0700, true)) throw new RuntimeException('Cannot create backup directory.');
    $path = $directory.'/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(6)).'.json';
    $backup = [];
    foreach ($updates as $id => $attributes) {
        $before = ['id' => $id, 'name' => $products[$id]->name, 'updated_at' => $products[$id]->updated_at];
        foreach (array_keys($attributes) as $field) $before[$field] = $products[$id]->{$field};
        $backup[] = $before;
    }
    $json = json_encode(['created_at' => now()->toIso8601String(), 'products' => $backup], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (file_put_contents($path, $json, LOCK_EX) !== strlen($json)) throw new RuntimeException('Cannot persist backup. Nothing updated.');
    chmod($path, 0600);
    foreach ($updates as $id => $attributes) {
        DB::table('products')->where('id', $id)->update($attributes + ['updated_at' => now()]);
    }
    foreach ($updates as $id => $attributes) {
        $saved = DB::table('products')->where('id', $id)->first();
        if (isset($attributes['description']) && $saved->description !== $attributes['description']) {
            throw new RuntimeException('Description verification failed for ID '.$id);
        }
        if ($id === 98 && ($saved->is_active || $saved->archived_at === null)) {
            throw new RuntimeException('Test product was not archived.');
        }
    }
    echo 'Updated '.count($updates).' products. Backup: '.$path.PHP_EOL;
});
