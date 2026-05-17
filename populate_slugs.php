<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ProductsModel;
use Illuminate\Support\Str;

$products = ProductsModel::whereNull('slug')->get();
echo "Found " . $products->count() . " products needing slugs.\n";

foreach ($products as $p) {
    $slug = Str::slug($p->product_name);
    $original = $slug;
    $count = 1;

    while (ProductsModel::where('slug', $slug)->where('product_id', '!=', $p->product_id)->exists()) {
        $slug = $original . '-' . $count++;
    }

    $p->slug = $slug;
    $p->save();
    echo "Updated [{$p->product_id}] {$p->product_name} -> {$slug}\n";
}
echo "Done!\n";
