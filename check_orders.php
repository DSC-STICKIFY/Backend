<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$orders = App\Models\OrdersModel::with(['orderDetails.product'])->get();
foreach ($orders as $order) {
    echo "Order ID: " . $order->order_id . " | Number: " . $order->order_number . " | Status: " . $order->status . " | CS: " . $order->cs_review_status . " | Staff: " . $order->staff_validation_status . "\n";
    foreach ($order->orderDetails as $detail) {
        $p = $detail->product;
        echo "  - Item: " . ($p ? $p->product_name : 'N/A') . " (ID: " . $detail->product_id . ") | Customizable: " . ($p ? $p->is_customizable : 'N/A') . "\n";
    }
}
