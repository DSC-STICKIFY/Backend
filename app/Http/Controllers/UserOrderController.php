<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Services\UserProductOrder;
use App\Models\ProductsModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UserOrderController extends Controller
{
    protected UserProductOrder $orderService;

    public function __construct(UserProductOrder $orderService)
    {
        $this->orderService = $orderService;
    }

    public function ProductOrder(OrderRequest $orderRequest): JsonResponse
    {
        try {
            $result = $this->orderService->placeOrder($orderRequest->validated());

            if (isset($result['checkout_url'])) {
                return response()->json([
                    'message' => $result['message'],
                    'order_id' => $result['order_id'],
                    'checkout_url' => $result['checkout_url'],
                ], 201);
            }

            return response()->json($result, 201);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'Failed to place order',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getUserOrders(): JsonResponse
    {
        try {
            $userId = auth()->id();
            if (!$userId) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            $result = $this->orderService->getUserOrders($userId);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'Failed to fetch user orders',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getOrderHistory(): JsonResponse
    {
        try {
            $result = $this->orderService->getOrderHistory();
            return response()->json($result, 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'Failed to retrieve order history',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function getAllProducts(): JsonResponse
    {
        try {
            $result = $this->orderService->getAllProducts();
            return response()->json($result, 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'Failed to retrieve products',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function viewProductDetails(int $id): JsonResponse
    {
        try {
            $result = $this->orderService->viewProductDetails($id);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            $status = $th->getCode() === 404 ? 404 : 500;
            return response()->json([
                'message' => $th->getMessage() ?: 'Failed to retrieve product',
                'error' => $th->getMessage(),
            ], $status);
        }
    }

    public function cancelOrder(Request $request, int $orderId): JsonResponse
    {
        try {
            $orderItemId = $request->input('order_item_id');
            $result = $this->orderService->cancelOrder($orderId, $orderItemId);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            $status = str_contains($th->getMessage(), 'cannot be cancelled') ? 400 : 500;
            return response()->json([
                'message' => $th->getMessage() ?: 'Failed to cancel order',
                'error' => $th->getMessage(),
            ], $status);
        }
    }

    public function getOrderDetails(int $orderId): JsonResponse
    {
        try {
            $result = $this->orderService->getOrderDetails($orderId);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'Order not found or access denied',
                'error' => $th->getMessage(),
            ], 404);
        }
    }

    //  Payment failed

    public function requestReturnRefund(Request $request, int $orderId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:255',
                'details' => 'nullable|string',
            ]);
            $result = $this->orderService->requestReturnRefund($orderId, $validated);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            $status = str_contains($th->getMessage(), 'Only completed orders') ? 400 : 500;
            return response()->json([
                'message' => $th->getMessage() ?: 'Failed to submit return/refund request',
                'error' => $th->getMessage(),
            ], $status);
        }
    }

    public function getOrderStats(): JsonResponse
    {
        try {
            $result = $this->orderService->getOrderStats();
            return response()->json($result, 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'Failed to retrieve order statistics',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function cancelOrderItem(int $orderId, int $itemId): JsonResponse
    {
        try {
            $result = $this->orderService->cancelOrder($orderId, $itemId);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            $status = str_contains($th->getMessage(), 'cannot be cancelled') ? 400 : 500;
            return response()->json([
                'message' => $th->getMessage() ?: 'Failed to cancel item',
                'error' => $th->getMessage(),
            ], $status);
        }
    }

    public function updateItemStatus(Request $request, int $orderId, int $itemId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:Pending,To Process,To Ship,To Receive,Completed,Cancelled,Return/Refund,Refunded',
            ]);
            $result = $this->orderService->updateItemStatus($orderId, $itemId, $validated['status']);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            return response()->json([
                'message' => 'Failed to update item status',
                'error' => $th->getMessage(),
            ], 500);
        }
    }

    public function approveDesign(int $orderId): JsonResponse
    {
        try {
            $result = $this->orderService->approveDesign($orderId);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            return response()->json(['message' => 'Failed to approve design', 'error' => $th->getMessage()], 500);
        }
    }

    public function requestChange(int $orderId): JsonResponse
    {
        try {
            $result = $this->orderService->requestChange($orderId);
            return response()->json($result, 200);
        } catch (Throwable $th) {
            return response()->json(['message' => 'Failed to request change', 'error' => $th->getMessage()], 500);
        }
    }
}