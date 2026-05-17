<?php

namespace App\Http\Controllers;

use App\Models\OrdersModel;
use App\Models\OrderDetails;
use App\Services\TrackingWormService;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(protected TrackingWormService $trackingService) {}

    public function track(Request $request, $orderId)
    {
        try {
            $order = OrdersModel::where('order_id', $orderId)->firstOrFail();

            // ❌ AUTO‑COMPLETE REMOVED – orders will NOT be completed automatically
            //    Only manual "Order Received" button will trigger completion.

            if (!$order->tracking_number) {
                return response()->json([
                    'error'  => 'No tracking number yet.',
                    'status' => 'pending',
                ], 404);
            }

            // Get tracking events from service
            $trackingData = $this->trackingService->track($order->tracking_number);

            // Inject deadline fields into response
            $trackingData['delivery_deadline'] = $order->delivery_deadline
                ? \Carbon\Carbon::parse($order->delivery_deadline)->toIso8601String()
                : null;

            $trackingData['dispatched_at'] = $order->dispatched_at
                ? \Carbon\Carbon::parse($order->dispatched_at)->toIso8601String()
                : null;

            $trackingData['auto_completed'] = (bool) $order->auto_completed_at;

            return response()->json($trackingData);

        } catch (\Throwable $e) {
            \Log::error('TrackingController error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}