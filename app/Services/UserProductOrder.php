<?php

namespace App\Services;


use App\Interfaces\OrderInterface;
use App\Interfaces\ProductViewerInterface;
use App\Models\OrdersModel;
use App\Models\ProductsModel;
use App\Services\ReturnPolicyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Services\PromotionServices;
use Throwable;

class UserProductOrder implements OrderInterface, ProductViewerInterface
{
    protected $paymongo;
    protected $promotionService;
    protected $returnPolicyService;
    public function __construct(PayMongoService $paymongo, PromotionServices $promotionService, ReturnPolicyService $returnPolicyService)
    {
        $this->paymongo = $paymongo;
        $this->promotionService = $promotionService;
        $this->returnPolicyService = $returnPolicyService;
    }

    private function transformProduct($product, $activePromos = null)
    {
        $originalPrice = (float) $product->product_price;
        
        if ($activePromos === null) {
            $userId = auth('sanctum')->id() ?? auth()->id();
            $activePromos = $this->promotionService->getActivePromotions($userId);
        }

        $bestPrice = $originalPrice;
        $appliedPromo = null;
        $appliedPromoId = null;

        foreach ($activePromos as $promo) {
            // Check if promo applies to a single unit (quantity 1)
            if (($promo['min_quantity'] ?? 0) <= 1 && ($promo['min_amount'] ?? 0) <= $originalPrice) {
                $discount = $this->promotionService->calculateDiscount($promo, $originalPrice, 1);
                $discountedPrice = $originalPrice - $discount;

                if ($discountedPrice < $bestPrice) {
                    $bestPrice = $discountedPrice;
                    $appliedPromo = $promo['name'];
                    $appliedPromoId = $promo['promotion_id'] ?? $promo['id'];
                }
            }
        }

        $designs = $product->designs ? $product->designs->map(function ($d) {
            return [
                'id' => $d->id,
                'design_name' => $d->design_name,
                'design_image' => $d->design_image ? asset('storage/' . $d->design_image) : null,
                'additional_price' => (float) $d->additional_price,
            ];
        }) : [];

        $qualities = $product->qualities ? $product->qualities->map(function ($q) {
            return [
                'id' => $q->id,
                'quality_name' => $q->quality_name,
                'description' => $q->description,
                'additional_price' => (float) $q->additional_price,
            ];
        }) : [];

        $sizes = $product->sizes ? $product->sizes->map(function ($s) {
            return [
                'id' => $s->id,
                'size_name' => $s->size_name,
                'additional_price' => (float) $s->additional_price,
            ];
        }) : [];

        return [
            'product_id' => $product->product_id,
            'product_name' => $product->product_name,
            'product_description' => $product->product_description,
            'product_price' => $originalPrice,
            'discounted_price' => $bestPrice < $originalPrice ? $bestPrice : null,
            'applied_promo' => $appliedPromo,
            'applied_promo_id' => $appliedPromoId,
            'product_image' => $product->product_image
                ? asset('storage/' . $product->product_image)
                : null,
            'product_category' => $product->product_category,
            'product_type' => $product->product_type,
            'is_active' => $product->is_active,
            'is_customizable' => $product->is_customizable,
            'created_at' => $product->created_at,
            'designs' => $designs,
            'qualities' => $qualities,
            'sizes' => $sizes,
        ];
    }

    /**
     * Place a new order with multiple items
     * Each item creates a separate row in order_details table
     */
    public function placeOrder(array $orderDetails): array
    {
        DB::beginTransaction();

        try {
            $items = $orderDetails['items'] ?? [];
            unset($orderDetails['items']);

            $orderDetails['order_date'] = Carbon::now()->toDateTimeString();
            $orderDetails['status'] = 'Pending';

            // Check if any ordered product is customizable
            $productIds = collect($items)->pluck('product_id')->unique()->toArray();
            $hasCustomizable = ProductsModel::whereIn('product_id', $productIds)
                ->where('is_customizable', 1)
                ->exists();

            $orderDetails['cs_review_status'] = $hasCustomizable ? 'pending_admin_approval' : 'not_applicable';
            $orderDetails['staff_validation_status'] = $hasCustomizable ? 'pending_validation' : 'not_applicable';

            $order = OrdersModel::create($orderDetails);

            foreach ($items as $item) {
                $order->orderDetails()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'] ?? 1,
                    'item_price' => $item['item_price'] ?? 0,
                    'subtotal' => $item['subtotal'] ?? (($item['quantity'] ?? 1) * ($item['item_price'] ?? 0)),
                    'size' => $item['size'] ?? null,
                    'comments' => $item['comments'] ?? null,
                    'status' => 'Pending',
                    'design_name' => $item['design_name'] ?? null,
                    'quality_name' => $item['quality_name'] ?? null,
                ]);
            }

            $order->load('orderDetails.product');

            $paymentMethod = strtoupper($orderDetails['payment_method'] ?? '');

            if ($paymentMethod === 'COD') {
                DB::commit();
                return [
                'message'      => 'Order placed successfully. Pay upon delivery.',
                'order_id'     => $order->order_id,      // ← fix here too
                'order_number' => $order->order_number,
                'order'        => $order,
            ];
            }

            if ($paymentMethod === 'PICKUP') {
                DB::commit();
                return [
            'message'      => 'Order placed successfully. Please pick up your order at the store.',
            'order_id'     => $order->order_id,      // ← and here
            'order_number' => $order->order_number,
            'order'        => $order,
        ];
            }

            if ($paymentMethod === 'GCASH') {
            $order->update(['status' => 'Pending Payment']);
            DB::commit();

            return [
                'message'      => 'Order placed. Redirecting to GCash...',
                'order_id'     => $order->order_id,
                'order_number' => $order->order_number,
                'order'        => $order,
            ];
        }

            throw new \Exception('Invalid payment method.');

        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Order placement failed', [
                'error' => $th->getMessage(),
                'data' => $orderDetails,
            ]);
            throw $th;
        }
    }

    /**
     * Get user's orders — includes reviews and per-item return policy window.
     */
    public function getUserOrders(int $userId): array
    {
        $orders = OrdersModel::with(['orderDetails.product.designs', 'reviews', 'returnRefund', 'artist'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->get();

        $returnStatusMap = [];
        foreach ($orders as $order) {
            if ($order->returnRefund) {
                foreach ($order->returnRefund as $return) {
                    if ($return->order_details_id) {
                        $returnStatusMap[$return->order_details_id] = $return->status;
                    }
                }
            }
        }

        return $orders->map(function ($order) use ($returnStatusMap) {
            $arr = $order->toArray();

            // ── Resolve return policy per item and attach window info ──────────
            // Return window base = delivery_deadline (starts when delivery expires),
            // falling back to completed_at then updated_at.
            $baseTime = $order->delivery_deadline
                ?? $order->completed_at
                ?? $order->updated_at;

            foreach ($arr['order_details'] as &$item) {
                $product = $order->orderDetails
                    ->firstWhere('order_details_id', $item['order_details_id'])
                    ?->product;

                $windowSeconds = null;
                $returnDeadline = null;

                if ($product) {
                    $policy = $this->returnPolicyService->resolvePolicyForProduct($product);

                    if ($policy && $policy->is_returnable && $policy->allowed_value > 0) {
                        // Convert policy window to seconds
                        $windowSeconds = match ($policy->allowed_unit) {
                            'minutes' => $policy->allowed_value * 60,
                            'hours'   => $policy->allowed_value * 3600,
                            'days'    => $policy->allowed_value * 86400,
                            default   => null,
                        };

                        // Deadline = completed_at (or updated_at) + window
                        if ($windowSeconds && $baseTime) {
                            $returnDeadline = Carbon::parse($baseTime)
                                ->addSeconds($windowSeconds)
                                ->toIso8601String();
                        }
                    }
                }

                $productImage = null;
                if (!empty($item['design_name']) && $item['design_name'] !== 'Custom Design' && $product && clone $product->designs) {
                    $matchedDesign = $product->designs->firstWhere('design_name', $item['design_name']);
                    if ($matchedDesign && $matchedDesign->design_image) {
                        $productImage = asset('storage/' . $matchedDesign->design_image);
                    }
                }

                $item['product_image']         = $productImage;
                $item['return_window_seconds'] = $windowSeconds;
                $item['return_deadline']       = $returnDeadline;
                $item['return_status']         = $returnStatusMap[$item['order_details_id']] ?? null;
            }
            unset($item);

            // Top-level return_status fallback (for orders with single returns)
            $firstReturn = $order->returnRefund?->first();
            $arr['return_status'] = $firstReturn?->status ?? null;

            // Also attach a top-level return_window_seconds (first item's policy)
            $firstItem = $arr['order_details'][0] ?? null;
            $arr['return_window_seconds'] = $firstItem['return_window_seconds'] ?? null;
            $arr['return_deadline']       = $firstItem['return_deadline'] ?? null;

            return $arr;
        })->toArray();
    }

    /**
     * Get formatted order history — includes reviews and item-level status
     */
        public function getOrderHistory(): array
    {
        $user = auth()->user();
        if (!$user) {
            throw new \Exception('Unauthenticated', 401);
        }

        $orders = OrdersModel::with([
            'orderDetails.product.designs',
            'user',
            'reviews',
            'returnRefund'
        ])->where('user_id', $user->getKey())
            ->orderBy('created_at', 'desc')
            ->get();

        $returnStatusMap = [];
        foreach ($orders as $order) {
            if ($order->returnRefund) {
                $returns = $order->returnRefund;
                if ($returns instanceof \Illuminate\Database\Eloquent\Collection) {
                    foreach ($returns as $return) {
                        if ($return->order_details_id) {
                            $returnStatusMap[$return->order_details_id] = $return->status;
                        }
                    }
                } else {
                    if ($returns->order_details_id) {
                        $returnStatusMap[$returns->order_details_id] = $returns->status;
                    }
                }
            }
        }

        $formatted = $orders->map(function ($order) use ($returnStatusMap) {
            // ✅ REMOVED the misplaced return here

            $items = $order->orderDetails
                ->filter(fn($detail) => ($detail->status ?? $order->status) !== 'Cancelled')
                ->map(function ($detail) use ($order, $returnStatusMap) {
                    $productImage = null;
                    if ($detail->design_name && $detail->design_name !== 'Custom Design' && $detail->product && $detail->product->designs) {
                        $matchedDesign = $detail->product->designs->firstWhere('design_name', $detail->design_name);
                        if ($matchedDesign && $matchedDesign->design_image) {
                            $productImage = asset('storage/' . $matchedDesign->design_image);
                        }
                    }
                    if (!$productImage && $detail->product?->product_image) {
                        $productImage = asset('storage/' . $detail->product->product_image);
                    }

                    return [
                        'order_details_id' => $detail->order_details_id,
                        'product_id'       => $detail->product_id,
                        'product_name'     => $detail->product?->product_name ?? 'Unknown',
                        'product_image'    => $productImage,
                        'product_category' => $detail->product?->product_category,
                        'product_type'     => $detail->product?->product_type,
                        'quantity'         => $detail->quantity,
                        'item_price'       => $detail->item_price,
                        'subtotal'         => $detail->subtotal,
                        'size'             => $detail->size,
                        'comments'         => $detail->comments,
                        'status'           => $detail->status ?? $order->status,
                        'product'          => $detail->product,
                        'return_status'    => $returnStatusMap[$detail->order_details_id] ?? null,
                    ];
                });

            $activeTotal = $order->orderDetails
                ->filter(fn($detail) => ($detail->status ?? $order->status) !== 'Cancelled')
                ->sum('subtotal');

            $reviews     = $order->reviews ?? collect();
            $firstReview = $reviews->first();

            return [
                'order_id'             => $order->order_id,
                'order_number'         => $order->order_number,
                'order_date'           => $order->order_date
                    ? Carbon::parse($order->order_date)->toDateTimeString()
                    : null,
                'total_price'          => $activeTotal,
                'original_total'       => $order->total_price,
                'status'               => $order->status,
                'cancel_reason'        => $order->cancel_reason,
                'payment_method'       => $order->payment_method,
                'courier'              => $order->courier,
                'contact_number'       => $order->contact_number,
                'address'              => $order->address ?? ($order->user?->address ?? 'N/A'), // ✅ fixed column
                'return_reason'        => $order->return_reason,
                'return_details'       => $order->return_details,
                'items'                => $items->values(),
                'orders_details_table' => $items->values(),
                'items_count'          => $items->count(),
                'cancelled_count'      => $order->orderDetails->where('status', 'Cancelled')->count(),
                'reviews'              => $reviews->values(),
                'has_review'           => $reviews->isNotEmpty(),
                'rating'               => $firstReview?->rating,
                'comment'              => $firstReview?->comment,
                'admin_reply'          => $firstReview?->admin_reply,
            ];
        });

        return $formatted->values()->toArray(); // ✅ return is HERE, after map() is done
    }

    
    /**
     * Cancel order or specific item
     */
    public function cancelOrder(int $orderId, ?int $orderItemId = null): array
    {
        DB::beginTransaction();

        try {
            $userId = auth()->id();

            $order = OrdersModel::where('user_id', $userId)
                ->with('orderDetails')
                ->findOrFail($orderId);

            $blockedStatuses = ['To Receive', 'Completed', 'Cancelled', 'Return/Refund', 'Refunded'];

            if (in_array($order->status, $blockedStatuses)) {
                throw new \Exception('Order cannot be cancelled at this stage. Current status: ' . $order->status);
            }

            if ($orderItemId) {
                $item = $order->orderDetails()
                    ->where('order_details_id', $orderItemId)
                    ->firstOrFail();

                if (in_array($item->status, $blockedStatuses)) {
                    throw new \Exception('This item cannot be cancelled. Current status: ' . $item->status);
                }

                if ($item->status === 'Cancelled') {
                    throw new \Exception('Item is already cancelled.');
                }

                $originalTotalPrice = floatval($order->total_price);
                $currentSubtotalBeforeCancel = $order->orderDetails()
                    ->where('status', '!=', 'Cancelled')
                    ->sum('subtotal');
                $shippingFee = max(0, $originalTotalPrice - $currentSubtotalBeforeCancel);

                $item->update(['status' => 'Cancelled']);

                $activeItems = $order->orderDetails()
                    ->where('status', '!=', 'Cancelled')
                    ->get();

                $activeTotal = $activeItems->sum('subtotal');

                if ($activeTotal > 0) {
                    $newTotal = $activeTotal + $shippingFee;
                    $order->update(['total_price' => $newTotal]);
                } else {
                    $allSubtotal = $order->orderDetails()->sum('subtotal');
                    $restoreTotalPrice = $allSubtotal + $shippingFee;
                    $order->update(['total_price' => $restoreTotalPrice]);
                }

                $totalItems = $order->orderDetails()->count();
                $cancelledItems = $order->orderDetails()
                    ->where('status', 'Cancelled')
                    ->count();

                if ($totalItems > 0 && $totalItems === $cancelledItems) {
                    $order->update(['status' => 'Cancelled']);
                }

                DB::commit();

                return [
                    'message' => 'Item cancelled successfully.',
                    'order_id' => $order->order_number,
                    'item_id' => $orderItemId,
                    'new_total' => $newTotal,
                    'order' => $order->fresh()->load('orderDetails.product'),
                ];
            }

            $order->update(['status' => 'Cancelled']);
            $order->orderDetails()->update(['status' => 'Cancelled']);

            DB::commit();

            return [
                'message' => 'Order cancelled successfully.',
                'order_id' => $order->order_number,
                'order' => $order->fresh()->load('orderDetails.product'),
            ];

        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Cancel order failed', [
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'user_id' => $userId ?? null,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Return/Refund request
     */
    public function requestReturnRefund(int $orderId, array $data): array
    {
        DB::beginTransaction();

        try {
            $userId = auth()->id();

            $order = OrdersModel::where('user_id', $userId)
                ->findOrFail($orderId);

            if ($order->status !== 'Completed') {
                throw new \Exception('Only completed orders can be returned. Current status: ' . $order->status);
            }

            $order->update([
                'status' => 'Return/Refund',
                'return_reason' => $data['reason'] ?? null,
                'return_details' => $data['details'] ?? null,
            ]);

            DB::commit();

            return [
                'message' => 'Return/Refund request submitted.',
                'order_id' => $order->order_number,
                'order' => $order->fresh(),
            ];
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Return/Refund request failed', [
                'order_id' => $orderId,
                'user_id' => $userId ?? null,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }

    /**
     * Get single order details with item-level status
     */
    public function getOrderDetails(int $orderId): array
    {
        $userId = auth()->id();

        $order = OrdersModel::with(['user', 'orderDetails.product', 'reviews'])
            ->where('user_id', $userId)
            ->findOrFail($orderId);

        if ($order->orderDetails) {
            $order->orderDetails->each(function ($detail) use ($order) {
                if ($detail->product?->product_image) {
                    $detail->product->product_image = asset('storage/' . $detail->product->product_image);
                }
                $detail->item_status = $detail->status ?? $order->status;
            });
        }

        $activeTotal = $order->orderDetails
            ->filter(function ($detail) {
                return ($detail->status ?? $order->status) !== 'Cancelled';
            })
            ->sum('subtotal');

        $result = $order->toArray();
        $result['active_total'] = $activeTotal;
        $result['original_total'] = $order->total_price;
        $result['cancelled_items_count'] = $order->orderDetails
            ->where('status', 'Cancelled')
            ->count();

        return $result;
    }

    /**
     * Get all active products
     */
    public function getAllProducts(): array
    {
        $products = ProductsModel::with(['designs', 'qualities', 'sizes'])
            ->where('is_active', 1)
            ->orderBy('created_at', 'desc')
            ->get();

        $userId = auth('sanctum')->id() ?? auth()->id();
        $activePromos = $this->promotionService->getActivePromotions($userId);

        $formatted = $products->map(fn($product) => $this->transformProduct($product, $activePromos));

        return [
            'message' => 'Products retrieved successfully',
            'products' => $formatted,
            'count' => $formatted->count(),
        ];
    }

    /**
     * View specific product
     */
    public function viewProductDetails(int $id): array
    {
        $product = ProductsModel::with(['designs', 'qualities', 'sizes'])->find($id);

        if (!$product) {
            throw new \Exception('Product not found', 404);
        }

        return [
            'message' => 'Product retrieved successfully',
            'product' => $this->transformProduct($product),
        ];
    }

    /**
     * Get order statistics for the authenticated user
     */
    public function getOrderStats(): array
    {
        $userId = auth()->id();

        if (!$userId) {
            throw new \Exception('Unauthenticated', 401);
        }

        $orders = OrdersModel::where('user_id', $userId)->get();

        return [
            'total_orders' => $orders->count(),
            'pending' => $orders->where('status', 'Pending')->count(),
            'to_process' => $orders->where('status', 'To Process')->count(),
            'to_ship' => $orders->where('status', 'To Ship')->count(),
            'to_receive' => $orders->where('status', 'To Receive')->count(),
            'completed' => $orders->where('status', 'Completed')->count(),
            'cancelled' => $orders->where('status', 'Cancelled')->count(),
            'return_refund' => $orders->where('status', 'Return/Refund')->count(),
            'total_spent' => $orders->where('status', 'Completed')->sum('total_price'),
        ];
    }

    /**
     * Update item status (for admin use)
     */
    public function updateItemStatus(int $orderId, int $orderItemId, string $status): array
    {
        DB::beginTransaction();

        try {
            $order = OrdersModel::findOrFail($orderId);

            $item = $order->orderDetails()
                ->where('order_details_id', $orderItemId)
                ->firstOrFail();

            $item->update(['status' => $status]);

            $allItems = $order->orderDetails;
            $allSameStatus = $allItems->every(function ($item) use ($status) {
                return $item->status === $status;
            });

            if ($allSameStatus && $allItems->isNotEmpty()) {
                $order->update(['status' => $status]);
            }

            DB::commit();

            return [
                'message' => 'Item status updated successfully',
                'item' => $item->fresh(),
                'order' => $order->fresh(),
            ];

        } catch (Throwable $th) {
            DB::rollBack();
            Log::error('Update item status failed', [
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'status' => $status,
                'error' => $th->getMessage(),
            ]);
            throw $th;
        }
    }
    /**
     * Approve the final design submitted by the artist
     */
    public function approveDesign(int $orderId): array
    {
        DB::beginTransaction();
        try {
            $order = OrdersModel::where('user_id', auth()->id())->findOrFail($orderId);
            
            $order->update([
                'status'               => 'Awaiting Shipment Approval',
                'customer_approved_at' => now()
            ]);

            DB::commit();
            return [
                'success' => true,
                'message' => 'Design approved. Order is now awaiting shipment approval.',
                'order'   => $order->fresh()
            ];
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Request changes/revisions for the design
     */
    public function requestChange(int $orderId): array
    {
        DB::beginTransaction();
        try {
            $order = OrdersModel::where('user_id', auth()->id())->findOrFail($orderId);
            
            $order->update(['status' => 'For Revision']);

            DB::commit();
            return [
                'success' => true,
                'message' => 'Change request submitted.',
                'order'   => $order->fresh()
            ];
        } catch (Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}