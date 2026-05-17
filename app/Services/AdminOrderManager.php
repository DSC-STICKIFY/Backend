<?php

namespace App\Services;

use App\Interfaces\AdminOrderServices;
use App\Models\OrdersModel;
use App\Models\OrderDetails;
use App\Models\OrdersPayment;
use App\Models\ReturnRefund;
use App\Models\ReturnRefundModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminOrderManager implements AdminOrderServices
{
    // ==================== ORDER LISTING ====================

    public function getOrderList(): JsonResponse
    {
        try {
            $query = OrdersModel::with(['user', 'orderDetails.product', 'reviews', 'artist'])
                ->select(
                    'order_id',
                    'order_number',
                    'status',
                    'promotion_id',
                    'user_id',
                    'courier',
                    'order_date',
                    'total_price',
                    'payment_method',
                    'contact_number',
                    'return_reason',
                    'return_details',
                    'created_at',
                    'tracking_number',
                    'delivery_deadline',
                    'dispatched_at',
                    'auto_completed_at',
                    'expected_shipped_at', // ← add
                    'expected_delivery_at', // ← add
                    'address',
                    'paymongo_source_id', // â†  add
                    'artist_id',           // â†  add
                    'final_design_url',
                    'shipment_requested_at',
                    'shipment_note'
                );

            // ðŸŽ¨ Filter by Artist ID if the user is an artist
            $user = auth('artist_api')->user() ?? auth('sanctum')->user();
            if ($user instanceof \App\Models\ArtistModel) {
                $query->where('artist_id', $user->employee_id);
            }

            $orders = $query->orderBy('created_at', 'desc')->get();

            return response()->json(['orders' => $orders]);
        } catch (\Throwable $e) {
            Log::error('Order list error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getRecentOrders(): JsonResponse
    {
        try {
            $query = OrdersModel::with(['user', 'orderDetails.product', 'reviews']);

            // ðŸŽ¨ Filter by Artist ID if the user is an artist
            $user = auth('artist_api')->user() ?? auth('sanctum')->user();
            if ($user instanceof \App\Models\ArtistModel) {
                $query->where('artist_id', $user->employee_id);
            }

            $orders = $query->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            return response()->json(['recent_orders' => $orders]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to fetch recent orders', 'error' => $e->getMessage()], 500);
        }
    }

    // ==================== PER-ITEM STATUS UPDATES ====================

    public function acceptOrder(int $orderId, ?int $orderDetailsId = null): JsonResponse
    {
        return $this->updateItemStatus($orderId, $orderDetailsId, 'To Process', 'Order accepted and is being prepared.');
    }

    public function shipOrder(int $orderId, ?int $orderDetailsId = null): JsonResponse
    {
        $order = OrdersModel::findOrFail($orderId);
        $method = strtoupper($order->payment_method ?? '');
        $targetStatus = ($method === 'PICKUP' || $method === 'STORE PICKUP') ? 'Item Ready' : 'To Ship';

        return $this->updateItemStatus($orderId, $orderDetailsId, $targetStatus, 'Order is ready to ship.');
    }

    /**
     * Mark order as out for delivery.
     * Sets tracking number, delivery_deadline, dispatched_at,
     * AND auto_completed_at (deadline + 7 days for auto-completion).
     */
    public function outForDelivery(
        int $orderId,
        ?int $orderDetailsId = null,
        ?string $trackingNumber = null,
        int $deliveryDays = 5,
        int $deliveryMinutes = 0
    ): JsonResponse {
        // Days added on top of delivery deadline before auto-completion fires.
        $autoCompleteDays = 7;

        try {
            $finalTracking = $trackingNumber
                ?? 'JT' . date('Ymd') . str_pad($orderId, 6, '0', STR_PAD_LEFT);

            // Delivery deadline (minutes take priority â€” useful for testing)
            $deliveryDeadline = $deliveryMinutes > 0
                ? now()->addMinutes($deliveryMinutes)
                : now()->addDays(max(1, $deliveryDays));

            // Auto-complete fires AFTER delivery deadline
            $autoCompletedAt = $deliveryMinutes > 0
                ? $deliveryDeadline->copy()->addMinutes($autoCompleteDays * 24 * 60)
                : $deliveryDeadline->copy()->addDays($autoCompleteDays);

            $updated = OrdersModel::where('order_id', $orderId)->update([
                'tracking_number'   => $finalTracking,
                'delivery_deadline' => $deliveryDeadline,
                'dispatched_at'     => now(),
                'auto_completed_at' => $autoCompletedAt,
            ]);

            if (!$updated) {
                throw new \Exception("Order #{$orderId} not found or update failed.");
            }

            Log::info("Order #{$orderId} out for delivery", [
                'tracking' => $finalTracking,
                'delivery_deadline' => $deliveryDeadline,
                'auto_completed_at' => $autoCompletedAt,

            ]);

            return $this->updateItemStatus(
                $orderId,
                $orderDetailsId,
                'To Receive',
                'Order is out for delivery.'
            );
        } catch (\Throwable $e) {
            Log::error('outForDelivery error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function completeOrder(int $orderId, ?int $orderDetailsId = null): JsonResponse
    {
        return $this->updateItemStatus($orderId, $orderDetailsId, 'Completed', 'Order marked as completed.');
    }

    public function confirmPayment(array $payment, int $orderId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $order = OrdersModel::with('orderDetails')->findOrFail($orderId);
            $orderDetailsId = $payment['order_details_id'] ?? null;

            $newStatus = 'To Process';

            if ($orderDetailsId) {
                $item = OrderDetails::where('order_id', $orderId)
                    ->where('order_details_id', $orderDetailsId)
                    ->firstOrFail();
                $item->update(['status' => $newStatus]);
                $this->syncParentOrderStatus($orderId);
            } else {
                $order->update(['status' => $newStatus]);
                OrderDetails::where('order_id', $orderId)->update(['status' => $newStatus]);
            }

            if (OrdersPayment::where('order_id', $orderId)->exists()) {
                return response()->json(['success' => false, 'message' => 'Payment already confirmed.'], 400);
            }

            OrdersPayment::create([
                'order_id' => $orderId,
                'payment_amount' => $order->total_price,
                'amount_paid' => $order->total_price,
                'payment_date' => Carbon::now(),
                'reference_number' => $payment['reference_number'] ?? 'PAY-' . $orderId . '-' . time(),
            ]);

            if ($order->status === 'Pending') {
                $order->update(['status' => 'To Process']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed.',
                'order' => $order->fresh()->load(['user', 'orderDetails.product', 'reviews'])
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Payment confirmation failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function updateItemStatus(int $orderId, ?int $orderDetailsId, string $newStatus, string $successMessage): JsonResponse
    {
        DB::beginTransaction();

        try {
            $order = OrdersModel::with('orderDetails')->findOrFail($orderId);
            $itemCount = $order->orderDetails()->count();

            if ($orderDetailsId) {
                $item = OrderDetails::where('order_id', $orderId)
                    ->where('order_details_id', $orderDetailsId)
                    ->firstOrFail();
                $item->update(['status' => $newStatus]);
                $this->syncParentOrderStatus($orderId);
            } else {
                if ($itemCount !== 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'order_details_id is required for orders with multiple items.'
                    ], 400);
                }
                $order->update(['status' => $newStatus]);
                $order->orderDetails()->update(['status' => $newStatus]);
            }

            if ($newStatus === 'Completed') {
                $this->recordPaymentIfNeeded($order);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'order' => $order->fresh()->load(['user', 'orderDetails.product', 'reviews'])
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update item status", [
                'order_id' => $orderId,
                'order_details_id' => $orderDetailsId,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage()
            ], 500);
        }
    }

    private function syncParentOrderStatus(int $orderId): void
    {
        $order = OrdersModel::with('orderDetails')->find($orderId);
        if (!$order)
            return;

        $statuses = $order->orderDetails->pluck('status')->unique()->values();

        if ($statuses->count() === 1) {
            $order->update(['status' => $statuses->first()]);
            return;
        }

        $hasRefunded = $statuses->contains('Refunded');
        $hasActive = $statuses->contains(fn($s) => !in_array($s, ['Refunded', 'Cancelled']));

        if ($hasRefunded && $hasActive) {
            $order->update(['status' => 'Return/Refund']);
        } elseif ($hasRefunded && !$hasActive) {
            $order->update(['status' => 'Cancelled']);
        } else {
            $order->update(['status' => 'Return/Refund']);
        }
    }

    public function cancelOrder(int $orderId, ?int $orderItemId = null, ?string $reason = null): JsonResponse
    {
        DB::beginTransaction();

        try {
            $order = OrdersModel::with('orderDetails', 'orderPayment')->findOrFail($orderId);

            $blocked = ['To Receive', 'Completed'];
            if (in_array($order->status, $blocked)) {
                return response()->json([
                    'message' => 'Cannot cancel orders that are already out for delivery or completed.',
                    'current_status' => $order->status,
                ], 400);
            }

            $refundTriggered = false;
            $refundStatus = null;

            // Handle whole order cancellation logic for refunds
            if (!$orderItemId) {
                $isOnlinePayment = in_array(strtoupper($order->payment_method), ['GCASH', 'GRABPAY', 'PAYMONGO', 'CARD']);
                
                if ($isOnlinePayment) {
                    $payment = $order->orderPayment;
                    if ($payment && $payment->reference_number) {
                        try {
                            $paymongo = app(\App\Services\PayMongoService::class);
                            $paymongo->refundPayment($payment->reference_number, floatval($order->total_price));
                            $refundTriggered = true;
                            $refundStatus = 'Refund Initiated';
                        } catch (\Exception $e) {
                            Log::error("Refund failed during cancellation for Order #{$orderId}: " . $e->getMessage());
                            // We still cancel the order but mark refund status as failed
                            $refundStatus = 'Refund Failed';
                        }
                    }
                } else {
                    $refundStatus = 'No Refund (COD)';
                }
            }

            if ($orderItemId) {
                $item = OrderDetails::where('order_details_id', $orderItemId)
                    ->where('order_id', $orderId)
                    ->firstOrFail();

                if ($item->status === 'Cancelled') {
                    return response()->json(['message' => 'Item is already cancelled.'], 400);
                }

                $item->update(['status' => 'Cancelled']);

                $activeTotal = $order->orderDetails()
                    ->where('status', '!=', 'Cancelled')
                    ->sum('subtotal');
                $order->update([
                    'total_price'   => $activeTotal,
                    'cancel_reason' => $reason
                ]);

                $totalItems = $order->orderDetails()->count();
                $cancelledItems = $order->orderDetails()->where('status', 'Cancelled')->count();

                if ($totalItems > 0 && $totalItems === $cancelledItems) {
                    $order->update(['status' => 'Cancelled', 'refund_status' => $refundStatus]);
                } else {
                    $this->syncParentOrderStatus($orderId);
                }

                DB::commit();

                return response()->json([
                    'message' => 'Item cancelled successfully.',
                    'order' => $order->fresh()->load(['user', 'orderDetails.product', 'reviews']),
                ]);
            }

            $order->update([
                'status'        => 'Cancelled',
                'cancel_reason' => $reason,
                'refund_status' => $refundStatus
            ]);
            $order->orderDetails()->update(['status' => 'Cancelled']);

            DB::commit();

            $msg = 'Order cancelled successfully.';
            if ($refundTriggered) $msg .= ' Refund initiated via PayMongo.';

            return response()->json([
                'message' => $msg,
                'order' => $order->fresh()->load(['user', 'orderDetails.product', 'reviews']),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to cancel order', [
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to cancel order.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function changeStatus(int $orderId, string $currentExpectedStatus, string $newStatus, string $successMessage): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);

            if ($order->status !== $currentExpectedStatus) {
                return response()->json([
                    'message' => "Cannot change status: order is currently '{$order->status}', expected '{$currentExpectedStatus}'."
                ], 400);
            }

            $order->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'order' => $order->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Status change failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to change status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

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
            'order_id' => $order->order_id,
            'payment_amount' => $order->total_price,
            'amount_paid' => $order->total_price,
            'payment_date' => Carbon::now()->toDateTimeString(),
            'reference_number' => $prefix . '-' . $order->order_id,
        ]);
    }

    public function requestReturnRefund(int $orderId, array $data): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);
            if ($order->status !== 'Completed') {
                return response()->json([
                    'message' => 'Only completed orders can be returned.',
                    'current_status' => $order->status,
                ], 400);
            }

            $order->update([
                'status' => 'Return/Refund',
                'return_reason' => $data['reason'] ?? null,
                'return_details' => $data['details'] ?? null,
            ]);

            return response()->json([
                'message' => 'Return/Refund request submitted.',
                'order' => $order->fresh()->load(['user', 'orderDetails.product', 'reviews']),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to submit return/refund request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateOrderStatus(int $orderId, string $status): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);
            $order->update(['status' => $status]);

            return response()->json([
                'message' => 'Order status updated successfully.',
                'order' => $order->fresh()->load(['user', 'orderDetails.product', 'reviews']),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update order status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== PER-ITEM RETURN / REFUND ====================

    public function requestItemReturn(Request $request, int $orderDetailId): JsonResponse
    {
        try {
            $orderDetail = OrderDetails::with('order')->findOrFail($orderDetailId);
            $order = $orderDetail->order;

            if ($orderDetail->status !== 'Completed') {
                return response()->json(['message' => 'Only completed items can be returned.'], 400);
            }

            $existing = ReturnRefundModel::where('order_detail_id', $orderDetailId)
                ->whereIn('status', ['pending', 'approved'])
                ->first();
            if ($existing) {
                return response()->json(['message' => 'A return request already exists for this item.'], 400);
            }

            $data = $request->validate([
                'reason' => 'required|string|max:255',
                'description' => 'nullable|string',
                'proof_image' => 'nullable|image|max:2048',
            ]);

            $imagePath = null;
            if ($request->hasFile('proof_image')) {
                $imagePath = $request->file('proof_image')->store('returns', 'public');
            }

            $returnRequest = ReturnRefundModel::create([
                'order_detail_id' => $orderDetailId,
                'user_id' => $order->user_id,
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'proof_image' => $imagePath,
                'status' => 'pending',
                'messages' => [],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Return request submitted.',
                'data' => $returnRequest->load('orderDetail.product')
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Return request failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to submit request.'], 500);
        }
    }

    public function approveReturnRequest(int $requestId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $returnRequest = ReturnRefundModel::with('orderDetail')->findOrFail($requestId);
            if ($returnRequest->status !== 'pending') {
                return response()->json(['message' => 'Request already processed.'], 400);
            }

            $orderDetail = $returnRequest->orderDetail;
            $orderDetail->update(['status' => 'Refunded']);
            $returnRequest->update(['status' => 'approved']);
            $this->syncParentOrderStatus($orderDetail->order_id);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Return approved and item refunded.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Approve return failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to approve return.'], 500);
        }
    }

    public function rejectReturnRequest(int $requestId, ?string $reason = null): JsonResponse
    {
        try {
            $returnRequest = ReturnRefundModel::findOrFail($requestId);
            if ($returnRequest->status !== 'pending') {
                return response()->json(['message' => 'Request already processed.'], 400);
            }

            $returnRequest->update(['status' => 'rejected', 'admin_notes' => $reason]);

            return response()->json(['success' => true, 'message' => 'Return request rejected.']);
        } catch (\Throwable $e) {
            Log::error('Reject return failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to reject return.'], 500);
        }
    }

    public function getAllReturnRequests(): JsonResponse
    {
        try {
            $returns = ReturnRefundModel::with(['orderDetail.product', 'user'])
                ->orderBy('created_at', 'desc')
                ->get();
            return response()->json(['data' => $returns]);
        } catch (\Throwable $e) {
            Log::error('Get all returns failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to fetch returns.'], 500);
        }
    }
    // ==================== ARTIST WORKFLOW ====================

    public function assignArtist(int $orderId, int $artistId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);
            $order->update([
                'artist_id' => $artistId,
                'status'    => 'To Process'
            ]);

            // Sync all order detail items to "To Process"
            $order->orderDetails()->update(['status' => 'To Process']);

            // Get product_id of the first item to associate message with the right chat thread
            $firstDetail = $order->orderDetails()->first();
            $productId = $firstDetail ? $firstDetail->product_id : null;

            // Auto-send welcome chat message from the assigned artist to the customer
            try {
                $welcomeMessage = \App\Models\Message::create([
                    'sender_id'   => $artistId,
                    'receiver_id' => $order->user_id,
                    'product_id'  => $productId,
                    'body'        => "🎨 Hello! I am your assigned artist for Order #{$order->order_number}. Your customized design order has been accepted! You have 1-2 days to discuss and request revisions. Let's design something amazing together!",
                    'sender_type' => 'artist',
                    'is_read'     => false,
                    'is_bot'      => false,
                ]);

                // Broadcast the message real-time
                broadcast(new \App\Events\MessageSent($welcomeMessage))->toOthers();
            } catch (\Throwable $msgEx) {
                Log::error('Failed to send auto welcome message from artist: ' . $msgEx->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Artist assigned successfully.',
                'order'   => $order->fresh()->load(['user', 'artist', 'orderDetails.product'])
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to assign artist.', 'error' => $e->getMessage()], 500);
        }
    }

    public function markInProgress(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);
            
            $request->validate([
                'expected_shipped_at'  => 'required|date|after_or_equal:now',
                'expected_delivery_at' => 'required|date|after:expected_shipped_at',
            ]);

            $order->update([
                'status'               => 'In Progress',
                'in_progress_at'       => now(),
                'expected_shipped_at'  => $request->input('expected_shipped_at'),
                'expected_delivery_at' => $request->input('expected_delivery_at'),
            ]);

            // Sync all order detail items
            $order->orderDetails()->update(['status' => 'In Progress']);

            return response()->json([
                'success' => true,
                'message' => 'Order marked as In Progress with timeline.',
                'order'   => $order->fresh()->load(['user', 'artist', 'orderDetails.product'])
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to update status.', 'error' => $e->getMessage()], 500);
        }
    }

    public function uploadFinalDesign(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);
            
            $request->validate([
                'final_design' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
            ]);

            if ($request->hasFile('final_design')) {
                // Delete old design if exists
                if ($order->final_design_url) {
                    Storage::disk('public')->delete($order->final_design_url);
                }
                $path = $request->file('final_design')->store('final_designs', 'public');
                $order->update([
                    'final_design_url' => $path,
                    'status'           => 'Finalizing'
                ]);
                $order->orderDetails()->update(['status' => 'Finalizing']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Final design uploaded.',
                'order'   => $order->fresh()
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to upload design.', 'error' => $e->getMessage()], 500);
        }
    }

    public function requestShipment(Request $request, int $orderId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);
            $order->update([
                'status'                => 'Awaiting Shipment Approval',
                'shipment_requested_at' => now(),
                'shipment_note'         => $request->input('note')
            ]);
            $order->orderDetails()->update(['status' => 'Awaiting Shipment Approval']);

            return response()->json([
                'success' => true,
                'message' => 'Shipment request sent to Admin.',
                'order'   => $order->fresh()
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to request shipment.', 'error' => $e->getMessage()], 500);
        }
    }

    public function approveShipmentRequest(int $orderId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);
            $order->update(['status' => 'Shipment Approved']);
            $order->orderDetails()->update(['status' => 'Shipment Approved']);

            return response()->json([
                'success' => true,
                'message' => 'Shipment request approved.',
                'order'   => $order->fresh()
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to approve shipment.', 'error' => $e->getMessage()], 500);
        }
    }

    public function rejectShipmentRequest(\Illuminate\Http\Request $request, int $orderId): \Illuminate\Http\JsonResponse
    {
        try {
            $order = \App\Models\OrdersModel::findOrFail($orderId);
            $reason = $request->input('reason', 'No reason provided.');
            $order->update([
                'status'                => 'Design In Progress',
                'shipment_note'         => $reason,
                'shipment_requested_at' => null,
            ]);
            $order->orderDetails()->update(['status' => 'Design In Progress']);

            return response()->json([
                'success' => true,
                'message' => 'Shipment request rejected. Artist notified.',
                'order'   => $order->fresh()
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed to reject shipment.', 'error' => $e->getMessage()], 500);
        }
    }
}
