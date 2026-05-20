<?php

namespace App\Services;

use App\Models\OrderDetails;
use App\Models\OrdersModel;
use App\Models\ReturnMediaModel;
use App\Models\ReturnRefundModel;
use App\Models\SettingModel;             
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Notifications\RefundProcessed;
use App\Models\ReturnMessageModel;

class ReturnRefundService
{
    /**
     * Create a new return/refund request
     */
    public function createReturn(Request $request)
    {
        $this->validateReturnRequest($request);

        $user = auth()->user();
        if (!$user) {
            throw ValidationException::withMessages([
                'auth' => 'User must be authenticated.'
            ]);
        }

        $userId = $this->extractUserId($user);
        if (!$userId) {
            Log::error('ReturnRefundService: Cannot extract user ID', [
                'user_class'      => get_class($user),
                'user_attributes' => $user->toArray() ?? []
            ]);
            throw ValidationException::withMessages([
                'user' => 'Invalid user identifier. Please refresh and try again.'
            ]);
        }

        $order = OrdersModel::findOrFail((int) $request->order_id);

        if (!$request->filled('order_details_id')) {
            throw ValidationException::withMessages([
                'order_details_id' => 'Specific item is required for return/refund.'
            ]);
        }

        $orderDetail = OrderDetails::where('order_details_id', $request->order_details_id)
            ->where('order_id', $request->order_id)
            ->first();

        if (!$orderDetail) {
            throw ValidationException::withMessages([
                'order_details_id' => 'Order item not found.'
            ]);
        }

        // ✅ FIX: normalize status — handles "To Receive", "to_receive", "completed", etc.
        $normalizedStatus = strtolower(str_replace('_', ' ', $orderDetail->status ?? ''));
        $allowedStatuses  = ['to receive', 'completed'];

        if (!in_array($normalizedStatus, $allowedStatuses)) {
            throw ValidationException::withMessages([
                'order_details_id' => 'Only items with status "To Receive" or "Completed" can be returned.'
            ]);
        }

        // ✅ 3-day window check (falls back to updated_at if completed_at is missing)
        $statusChangedAt = $orderDetail->completed_at ?? $orderDetail->updated_at;
        if (!$statusChangedAt) {
            throw ValidationException::withMessages([
                'order_details_id' => 'Cannot determine when the item status changed.'
            ]);
        }

        $threeDaysAgo = Carbon::now()->subDays(3);
        if (Carbon::parse($statusChangedAt)->lt($threeDaysAgo)) {
            throw ValidationException::withMessages([
                'order_details_id' => 'Return window expired. You can only request a return within 3 days.'
            ]);
        }

        // Prevent duplicate return
        $existing = ReturnRefundModel::where('order_id', $request->order_id)
            ->where('order_details_id', $request->order_details_id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'order_details_id' => 'This item already has an active return request.'
            ]);
        }

        // Product details
        $product     = $orderDetail->product;
        $productId   = $orderDetail->product_id;
        $productName = $orderDetail->product_name ?? $product->product_name ?? 'Product';
        $unitPrice   = (float) ($orderDetail->item_price ?? $orderDetail->price ?? 0);
        $qty         = (int)   ($orderDetail->quantity ?? 1);

        // ✅ DYNAMIC refund % — reads from settings table, defaults to 70
        $refundPct    = (float) SettingModel::get('refund_percentage', 70) / 100;
        $refundAmount = ($unitPrice * $qty) * $refundPct;

        // Create return
        $return = ReturnRefundModel::create([
            'order_id'         => (int) $request->order_id,
            'order_details_id' => (int) $request->order_details_id,
            'product_id'       => $productId,
            'product_name'     => $productName,
            'user_id'          => $userId,
            'reason'           => $request->reason,
            'description'      => $request->input('description'),
            'refund_amount'    => $refundAmount,
            'status'           => 'pending',
            'gcash_number'     => $request->input('gcash_number'),
        ]);

        // Update order detail status
        $orderDetail->update(['status' => 'Return/Refund']);

        // Update overall order status if all items are returned/cancelled
        $this->checkIfOrderShouldBeReturned($order);

        $this->uploadMedia($return, $request);

        return $return->load(['media', 'messages', 'orderDetail.product', 'order']);
    }

    /**
     * Extract user ID from authenticated user object
     */
    private function extractUserId($user)
    {
        if (method_exists($user, 'getAuthIdentifier')) {
            $id = $user->getAuthIdentifier();
            if ($id) return $id;
        }
        if (method_exists($user, 'getKey')) {
            $id = $user->getKey();
            if ($id) return $id;
        }
        return $user->user_id ?? $user->id ?? null;
    }

    private function checkIfOrderShouldBeReturned($order)
    {
        $totalItems    = $order->orderDetails()->count();
        $affectedItems = $order->orderDetails()
            ->whereIn('status', ['Return/Refund', 'Cancelled', 'Returned', 'Refunded'])
            ->count();
        $completedItems = $order->orderDetails()
            ->where('status', 'Completed')
            ->count();

        if ($totalItems === $affectedItems) {
            $order->update(['status' => 'Return/Refund']);
        } elseif ($affectedItems > 0 && $completedItems > 0) {
            $order->update(['status' => 'Partially Refunded']);
        }
    }

    public function authorizeSubAdmin($returnId)
    {
        $authUser = auth()->user();
        if ($authUser && method_exists($authUser, 'getTable') && $authUser->getTable() === 'sub_admin_table') {
            throw ValidationException::withMessages([
                'auth' => 'Only the Super Admin can authorize a sub-admin.'
            ]);
        }

        $return = ReturnRefundModel::findOrFail($returnId);
        $return->update(['subadmin_authorized' => true]);

        // Post system message log
        ReturnMessageModel::create([
            'return_id' => $returnId,
            'sender_type' => 'admin',
            'sender_id' => auth()->id(),
            'message' => '[SYSTEM] Super Admin has authorized Sub-Admin to approve or reject this return/refund request.',
        ]);

        return $return->load(['media', 'messages', 'orderDetail.product', 'order']);
    }

    public function updateReturnStatus($returnId, $status, $proofFile = null)
    {
        $return = ReturnRefundModel::with(['order.orderPayment', 'orderDetail', 'user'])->findOrFail($returnId);

        $authUser = auth()->user();
        if ($authUser && method_exists($authUser, 'getTable') && $authUser->getTable() === 'sub_admin_table') {
            if (!$return->subadmin_authorized) {
                throw ValidationException::withMessages([
                    'status' => 'You are not authorized by the Super Admin to process this return/refund request.'
                ]);
            }
        }

        if (!in_array($status, ['approved', 'rejected', 'refunded', 'completed'])) {
            throw ValidationException::withMessages([
                'status' => 'Invalid status. Use approved, rejected, refunded, or completed.'
            ]);
        }

        // ── Automated PayMongo Refund ─────────────────────────────────────────
        // If approving an online payment, attempt automated refund via API.
        if ($status === 'approved') {
            $payment = $return->order->orderPayment;
            // Check if it's a PayMongo payment (reference starts with 'pay_')
            if ($payment && $payment->reference_number && str_starts_with($payment->reference_number, 'pay_')) {
                try {
                    $paymongo = app(\App\Services\PayMongoService::class);
                    $refundResponse = $paymongo->refundPayment(
                        $payment->reference_number, 
                        (float)$return->refund_amount
                    );
                    
                    $return->paymongo_refund_id  = $refundResponse['data']['id'] ?? null;
                    $return->refund_completed_at = now();
                    $status = 'refunded'; // Automatically move to refunded state
                    
                } catch (\Exception $e) {
                    Log::error('ReturnRefundService: PayMongo Auto-Refund Failed', [
                        'return_id' => $returnId,
                        'error'     => $e->getMessage()
                    ]);
                    // We stay in 'approved' (manual fallback) or we could throw error.
                    // For now, let it be 'approved' so admin can try manual refund if API fails.
                }
            }
        }

        // ── Manual Refund Proof ───────────────────────────────────────────────
        if ($status === 'refunded' && $proofFile) {
            $path = $proofFile->store('refunds/proofs', 'public');
            $return->refund_proof        = $path;
            $return->refund_completed_at = now();
        }

        $return->update(['status' => $status]);

        // ── Sync Order/Item Status ────────────────────────────────────────────
        // We only officially mark the ITEM as "Refunded" when:
        // 1. Customer confirms receipt (completed)
        // 2. OR PayMongo refund was successful (since it's guaranteed by gateway)
        if ($status === 'completed' || ($status === 'refunded' && $return->paymongo_refund_id)) {
            if ($return->orderDetail) {
                $return->orderDetail->update(['status' => 'Refunded']);

                $order               = $return->order;
                $totalItems          = $order->orderDetails()->count();
                $refundedOrCancelled = $order->orderDetails()
                    ->whereIn('status', ['Refunded', 'Cancelled', 'Returned'])
                    ->count();
                $completedItems = $order->orderDetails()
                    ->where('status', 'Completed')
                    ->count();

                if ($totalItems > 0 && $totalItems === $refundedOrCancelled) {
                    $order->update(['status' => 'Refunded']);
                } elseif ($completedItems > 0 && $refundedOrCancelled > 0) {
                    $order->update(['status' => 'Partially Refunded']);
                }
            }
        }

        // ── Trigger Notification ─────────────────────────────────────────────
        if ($status === 'refunded') {
            $customer = $return->user;
            if ($customer) {
                $customer->notify(new RefundProcessed($return));
            }
        }

        return $return->load(['media', 'messages', 'orderDetail.product', 'order']);
    }

    public function getMessages($returnId)
    {
        $lastId = request()->query('last_id');
        
        $query = ReturnRefundMessage::with('sender')
            ->where('return_refund_id', $returnId);

        if ($lastId) {
            $query->where('id', '>', $lastId);
        }

        return $query->orderBy('created_at', 'asc')->get();
    }

    public function getReturn($returnId)
    {
        return ReturnRefundModel::with([
            'order:order_id,order_number,total_price,payment_method,status,artist_id',
            'order.artist:employee_id,first_name,last_name,email',
            'orderDetail.product:product_id,product_name,product_image,product_price,is_customizable',
            'media',
            'messages',
        ])->findOrFail($returnId);
    }

    public function getAllReturns()
    {
        return ReturnRefundModel::with([
            'order:order_id,order_number,total_price,payment_method,status,artist_id',
            'order.artist:employee_id,first_name,last_name,email',
            'orderDetail.product:product_id,product_name,product_image,is_customizable',
            'media',
            'messages',
        ])->orderBy('created_at', 'desc')->get();
    }

    private function validateReturnRequest(Request $request): void
    {
        $request->validate([
            'order_id'         => 'required|integer|exists:orders_table,order_id',
            'order_details_id' => 'nullable|integer|exists:orders_details_table,order_details_id',
            'reason'           => 'required|string|max:500',
            'description'      => 'nullable|string|max:1000',
            'gcash_number'     => 'nullable|string|max:20',
            'images.*'         => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'videos.*'         => 'nullable|mimes:mp4,mov,avi,wmv,webm|max:51200',
        ]);
    }

    private function uploadMedia(ReturnRefundModel $return, Request $request): void
    {
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if ($image->isValid()) {
                    $path = $image->store('returns/images', 'public');
                    ReturnMediaModel::create([
                        'return_id' => $return->id,
                        'file_path' => $path,
                        'file_type' => 'image',
                    ]);
                }
            }
        }

        if ($request->hasFile('videos')) {
            foreach ($request->file('videos') as $video) {
                if ($video->isValid()) {
                    $path = $video->store('returns/videos', 'public');
                    ReturnMediaModel::create([
                        'return_id' => $return->id,
                        'file_path' => $path,
                        'file_type' => 'video',
                    ]);
                }
            }
        }
    }
}