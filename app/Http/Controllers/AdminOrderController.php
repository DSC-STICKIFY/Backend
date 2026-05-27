<?php

namespace App\Http\Controllers;

use App\Models\OrdersModel;
use App\Models\OrdersPayment;
use App\Services\AdminOrderManager;
use App\Services\PayMongoService;
use App\Services\UserProductOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminOrderController extends Controller
{
    protected AdminOrderManager $adminOrderManager;
    protected UserProductOrder $orderService;
    protected PayMongoService $paymongo;

    public function __construct(AdminOrderManager $adminOrderManager, UserProductOrder $orderService, PayMongoService $paymongo)
    {
        $this->adminOrderManager = $adminOrderManager;
        $this->orderService = $orderService;
        $this->paymongo = $paymongo;
    }

    // ====================== ORDER LISTING ======================

    public function getOrderList(): JsonResponse
    {
        return $this->adminOrderManager->getOrderList();
    }

    public function getRecentOrders(): JsonResponse
    {
        return $this->adminOrderManager->getRecentOrders();
    }

    public function getAllOrders(): JsonResponse
    {
        return $this->adminOrderManager->getOrderList();
    }
    
    public function getDispatchedOrders(): JsonResponse
    {
        return $this->adminOrderManager->getDispatchedOrders();
    }

    // ====================== PER-ITEM STATUS ACTIONS ======================

    public function acceptOrder(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'order_details_id' => 'nullable|integer|exists:orders_details_table,order_details_id'
        ]);
        return $this->adminOrderManager->acceptOrder($orderId, $data['order_details_id'] ?? null);
    }

    public function shipOrder(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'order_details_id' => 'nullable|integer|exists:orders_details_table,order_details_id'
        ]);
        return $this->adminOrderManager->shipOrder($orderId, $data['order_details_id'] ?? null);
    }

    public function outForDelivery(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'order_details_id' => 'nullable|integer|exists:orders_details_table,order_details_id',
            'tracking_number' => 'nullable|string|max:100',
            'delivery_days' => 'nullable|integer|min:1',
            'delivery_minutes' => 'nullable|integer|min:0',
        ]);

        return $this->adminOrderManager->outForDelivery(
            $orderId,
            $data['order_details_id'] ?? null,
            $data['tracking_number'] ?? null,
            (int) ($data['delivery_days'] ?? 5),
            (int) ($data['delivery_minutes'] ?? 0)
        );
    }

    public function completeOrder(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'order_details_id' => 'nullable|integer|exists:orders_details_table,order_details_id'
        ]);
        return $this->adminOrderManager->completeOrder($orderId, $data['order_details_id'] ?? null);
    }

    public function confirmPayment(Request $request, int $orderId): JsonResponse
    {
        $paymentData = $request->validate([
            'order_details_id' => 'nullable|integer|exists:orders_details_table,order_details_id',
            'reference_number' => 'nullable|string|max:100',
        ]);
        return $this->adminOrderManager->confirmPayment($paymentData, $orderId);
    }

    public function cancelOrder(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'order_details_id' => 'nullable|integer|exists:orders_details_table,order_details_id',
            'reason'           => 'required|string|max:500'
        ]);
        return $this->adminOrderManager->cancelOrder($orderId, $data['order_details_id'] ?? null, $data['reason']);
    }

    // ====================== PER-ITEM RETURN / REFUND ======================

    public function requestItemReturn(Request $request, int $orderDetailId): JsonResponse
    {
        return $this->adminOrderManager->requestItemReturn($request, $orderDetailId);
    }

    public function approveReturnRequest(int $requestId): JsonResponse
    {
        return $this->adminOrderManager->approveReturnRequest($requestId);
    }

    public function rejectReturnRequest(Request $request, int $requestId): JsonResponse
    {
        $reason = $request->input('reason');
        return $this->adminOrderManager->rejectReturnRequest($requestId, $reason);
    }

    public function getAllReturnRequests(): JsonResponse
    {
        return $this->adminOrderManager->getAllReturnRequests();
    }

    // ====================== LEGACY (WHOLE-ORDER RETURN) – DEPRECATED ======================

    /**
     * @deprecated Use per‑item return flow instead (requestItemReturn + approveReturnRequest).
     */
    public function requestReturnRefund(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string', 'details' => 'nullable|string']);
        return $this->adminOrderManager->requestReturnRefund($orderId, $data);
    }

    /**
     * @deprecated Use per‑item approveReturnRequest instead.
     */
    public function approveReturn(int $orderId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);

            $payment = OrdersPayment::where('order_id', $orderId)->first();

            if (!$payment) {
                return response()->json([
                    'message' => 'No payment record found.'
                ], 404);
            }

            // 🔥 TRIGGER PAYMONGO REFUND HERE
            $refund = $this->paymongo->refundPayment(
                $payment->reference_number,
                floatval($order->total_price)
            );

            if (!$refund || !isset($refund['data'])) {
                throw new \Exception("Refund failed in PayMongo");
            }

            return $this->adminOrderManager->changeStatus(
                $orderId,
                'Return/Refund',
                'Refunded',
                'Refund successful and order updated.'
            );

        } catch (\Throwable $e) {
            Log::error('Refund failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Refund failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @deprecated Use per‑item rejectReturnRequest instead.
     */
    public function rejectReturn(int $orderId): JsonResponse
    {
        return $this->adminOrderManager->changeStatus($orderId, 'Return/Refund', 'Completed', 'Return rejected. Order status reverted to Completed.');
    }

    // ====================== ARTIST WORKFLOW ======================

    public function assignArtist(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'artist_id' => 'required|integer|exists:employees,employee_id'
        ]);
        return $this->adminOrderManager->assignArtist($orderId, $data['artist_id']);
    }

    public function approveLayout(int $orderId): JsonResponse
    {
        return $this->adminOrderManager->approveLayout($orderId);
    }

    public function rejectLayout(Request $request, int $orderId): JsonResponse
    {
        return $this->adminOrderManager->rejectLayout($request, $orderId);
    }

    public function approveShipmentRequest(int $orderId): JsonResponse
    {
        return $this->adminOrderManager->approveShipmentRequest($orderId);
    }

    public function rejectShipmentRequest(Request $request, int $orderId): JsonResponse
    {
        return $this->adminOrderManager->rejectShipmentRequest($request, $orderId);
    }

    public function staffConfirmShipment(int $orderId): JsonResponse
    {
        return $this->adminOrderManager->staffConfirmShipment($orderId);
    }

    // ====================== OTHER ACTIONS ======================

    public function updateOrderStatus(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:Pending,To Process,To Ship,To Receive,Shipped,Item Ready,Completed,Cancelled,Partially Refunded,Refunded,Partially Completed'
        ]);
        return $this->adminOrderManager->updateOrderStatus($orderId, $data['status']);
    }
}