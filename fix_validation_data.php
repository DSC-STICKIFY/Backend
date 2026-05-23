<?php
/**
 * Data Fix: Correct cs_review_status / staff_validation_status
 * for all existing orders that were created before the validation
 * workflow was added. Sets non-customizable orders to 'not_applicable'.
 */
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Custom Order Validation Data Fix ===\n\n";

// 1. Find all orders that have customizable products (using correct table names)
$customizableOrderIds = DB::table('orders_details_table')
    ->join('products_table', 'orders_details_table.product_id', '=', 'products_table.product_id')
    ->where('products_table.is_customizable', 1)
    ->pluck('orders_details_table.order_id')
    ->unique()
    ->toArray();

echo "Orders with customizable products: " . count($customizableOrderIds) . "\n";
echo "IDs: " . implode(', ', $customizableOrderIds) . "\n\n";

// 2. Fix non-customizable orders: set both statuses to not_applicable
$fixedNotApplicable = DB::table('orders_table')
    ->whereNotIn('order_id', $customizableOrderIds)
    ->where('cs_review_status', '!=', 'not_applicable')
    ->update([
        'cs_review_status' => 'not_applicable',
        'staff_validation_status' => 'not_applicable',
    ]);

echo "Fixed (set not_applicable for non-customizable orders): $fixedNotApplicable rows\n\n";

// 3. For customizable orders that are still 'Pending' or 'Pending Payment',
//    set them back to pending_admin_approval (admin hasn't accepted yet)
$fixedPending = DB::table('orders_table')
    ->whereIn('order_id', $customizableOrderIds)
    ->whereIn('status', ['Pending', 'Pending Payment'])
    ->where('cs_review_status', 'pending_review')
    ->update([
        'cs_review_status' => 'pending_admin_approval',
    ]);

echo "Fixed (reverted to pending_admin_approval for unaccepted customizable): $fixedPending rows\n\n";

// 4. For customizable orders that are Cancelled/Completed/Refunded/Return,
//    set them to not_applicable (workflow doesn't apply anymore)
$fixedTerminal = DB::table('orders_table')
    ->whereIn('order_id', $customizableOrderIds)
    ->whereIn('status', ['Cancelled', 'Completed', 'Refunded', 'Return/Refund'])
    ->whereNotIn('cs_review_status', ['not_applicable'])
    ->update([
        'cs_review_status' => 'not_applicable',
        'staff_validation_status' => 'not_applicable',
    ]);

echo "Fixed (set not_applicable for terminated customizable orders): $fixedTerminal rows\n\n";

// 5. Show final state
echo "=== Final state of ALL orders ===\n";
$orders = DB::table('orders_table')
    ->select('order_id', 'order_number', 'status', 'cs_review_status', 'staff_validation_status')
    ->orderBy('order_id')
    ->get();

foreach ($orders as $order) {
    echo "#{$order->order_id} {$order->order_number} | Status: {$order->status} | CS: {$order->cs_review_status} | Staff: {$order->staff_validation_status}\n";
}

echo "\nDone!\n";
