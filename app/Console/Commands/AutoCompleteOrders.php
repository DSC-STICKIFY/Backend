<?php

namespace App\Console\Commands;

use App\Models\OrdersModel;
use App\Models\OrdersPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCompleteOrders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:auto-complete
                            {--dry-run : Preview which orders would be completed without saving}';

    /**
     * The console command description.
     */
    protected $description = 'Auto-complete orders where status is "To Receive" and auto_completed_at has passed';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('🔍 Checking for orders eligible for auto-completion...');

        // Fetch all eligible orders using the model scope
        $orders = OrdersModel::pendingAutoComplete()
            ->with('orderDetails')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('✅ No orders to auto-complete.');
            return self::SUCCESS;
        }

        $this->info("Found {$orders->count()} order(s) to process.");

        if ($isDryRun) {
            $this->warn('-- DRY RUN MODE: No changes will be saved --');
            $this->table(
                ['Order ID', 'Order Number', 'User ID', 'auto_completed_at'],
                $orders->map(fn($o) => [
                    $o->order_id,
                    $o->order_number,
                    $o->user_id,
                    $o->auto_completed_at,
                ])
            );
            return self::SUCCESS;
        }

        $completed = 0;
        $skipped   = 0;
        $failed    = 0;

        foreach ($orders as $order) {
            DB::beginTransaction();
            try {
                // Guard: skip if already completed manually between query and loop
                if ($order->status === 'Completed') {
                    $skipped++;
                    DB::rollBack();
                    continue;
                }

                // Mark order + items as Completed
                $order->update(['status' => 'Completed']);
                $order->orderDetails()->update(['status' => 'Completed']);

                // Record payment entry for COD / PICKUP orders if not yet recorded
                $this->recordPaymentIfNeeded($order);

                DB::commit();

                $completed++;

                Log::info('Auto-completed order', [
                    'order_id'          => $order->order_id,
                    'order_number'      => $order->order_number,
                    'auto_completed_at' => $order->auto_completed_at,
                ]);

                $this->line("  ✔ #{$order->order_number} auto-completed.");

            } catch (\Throwable $e) {
                DB::rollBack();
                $failed++;

                Log::error('Auto-complete failed for order', [
                    'order_id' => $order->order_id,
                    'error'    => $e->getMessage(),
                ]);

                $this->error("  ✘ #{$order->order_number} failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done. Completed: {$completed} | Skipped: {$skipped} | Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * Mirror of AdminOrderManager::recordPaymentIfNeeded().
     * Creates a payment record for COD / STORE PICKUP / GCASH orders on completion.
     */
    private function recordPaymentIfNeeded(OrdersModel $order): void
    {
        $methods = ['COD', 'STORE PICKUP', 'GCASH'];

        if (!in_array(strtoupper($order->payment_method ?? ''), $methods)) {
            return;
        }

        if (OrdersPayment::where('order_id', $order->order_id)->exists()) {
            return;
        }

        $prefix = strtoupper($order->payment_method) === 'GCASH' ? 'GCASH' : 'COD';

        OrdersPayment::create([
            'order_id'         => $order->order_id,
            'payment_amount'   => $order->total_price,
            'amount_paid'      => $order->total_price,
            'payment_date'     => Carbon::now()->toDateTimeString(),
            'reference_number' => $prefix . '-AUTO-' . $order->order_id,
        ]);
    }
}