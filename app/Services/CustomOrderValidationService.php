<?php

namespace App\Services;

use App\Models\OrdersModel;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomOrderValidationService
{
    /**
     * CS: Send order for Staff manual validation check.
     * Transitions cs_review_status -> approved_for_staff
     * and staff_validation_status -> pending_validation.
     */
    public function sendToStaffValidation(int $orderId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);

            $order->update([
                'cs_review_status'       => 'approved_for_staff',
                'staff_validation_status' => 'pending_validation',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order sent to staff for manual validation.',
                'order'   => $order->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('sendToStaffValidation error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * CS: Reject order at the CS review stage.
     * Transitions cs_review_status -> rejected_by_cs.
     */
    public function csRejectOrder(int $orderId, string $reason): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);

            $order->update([
                'cs_review_status'       => 'rejected_by_cs',
                'staff_validation_status' => 'pending_validation', // not reached
                'rejection_reason'        => $reason,
                'status'                  => 'Cancelled',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order rejected by Customer Service.',
                'order'   => $order->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('csRejectOrder error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Staff: Submit manual validation result.
     * Possible outcomes: can_accommodate, partially_accommodate, cannot_accommodate.
     */
    public function staffSubmitValidation(
        int     $orderId,
        string  $validationStatus,      // 'can_accommodate' | 'partially_accommodate' | 'cannot_accommodate'
        ?string $staffNote = null,
        ?int    $approvedQuantity = null,
        ?string $rejectionReason = null
    ): JsonResponse {
        $allowed = ['can_accommodate', 'partially_accommodate', 'cannot_accommodate'];
        if (!in_array($validationStatus, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Invalid validation status.'], 422);
        }

        DB::beginTransaction();
        try {
            $order = OrdersModel::findOrFail($orderId);

            $updateData = [
                'staff_validation_status' => $validationStatus,
                'staff_validation_note'   => $staffNote,
            ];

            if ($validationStatus === 'partially_accommodate' && $approvedQuantity !== null) {
                $updateData['manual_approved_quantity'] = $approvedQuantity;
            }

            if ($validationStatus === 'cannot_accommodate') {
                $updateData['rejection_reason'] = $rejectionReason;
                // Auto-move to Cancelled after staff says cannot accommodate
                $updateData['status'] = 'Cancelled';
            }

            if ($validationStatus === 'can_accommodate') {
                // Ready for artist assignment — move back to CS queue
                $updateData['cs_review_status'] = 'pending_artist_assignment';
            }

            if ($validationStatus === 'partially_accommodate') {
                // Needs CS to inform customer first
                $updateData['cs_review_status'] = 'pending_partial_response';
            }

            $order->update($updateData);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Validation submitted successfully.',
                'order'   => $order->fresh(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('staffSubmitValidation error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * CS: Customer confirmed partial accommodation — proceed with approved_quantity.
     * Moves cs_review_status -> pending_artist_assignment.
     */
    public function customerAcceptsPartial(int $orderId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);

            $order->update([
                'cs_review_status' => 'pending_artist_assignment',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Partial accommodation accepted. Ready for artist assignment.',
                'order'   => $order->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('customerAcceptsPartial error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * CS: Customer declined partial accommodation — cancel the order.
     */
    public function customerDeclinesPartial(int $orderId): JsonResponse
    {
        try {
            $order = OrdersModel::findOrFail($orderId);

            $order->update([
                'cs_review_status' => 'rejected_by_cs',
                'status'           => 'Cancelled',
                'rejection_reason' => 'Customer declined partial accommodation.',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled — customer declined partial accommodation.',
                'order'   => $order->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error('customerDeclinesPartial error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get orders pending Staff manual validation.
     */
    public function getPendingStaffValidation(): JsonResponse
    {
        try {
            $orders = OrdersModel::with(['user', 'orderDetails.product'])
                ->where('cs_review_status', 'approved_for_staff')
                ->where('staff_validation_status', 'pending_validation')
                ->orderBy('updated_at', 'asc')
                ->get();

            foreach ($orders as $order) {
                foreach ($order->orderDetails as $item) {
                    if ($item->product && $item->product->is_customizable) {
                        try {
                            $designMsg = Message::where('sender_id', $order->user_id)
                                ->where('sender_type', 'customer')
                                ->where('product_id', $item->product_id)
                                ->whereNotNull('image')
                                ->orderBy('created_at', 'desc')
                                ->first();

                            if ($designMsg) {
                                $item->custom_design_image = $designMsg->image;
                                $bodyText = trim(str_replace('[DESIGN]', '', $designMsg->body ?? ''));
                                $item->custom_design_comments = ($bodyText !== '' && $bodyText !== 'None') ? $bodyText : null;
                            } else {
                                $textMsg = Message::where('sender_id', $order->user_id)
                                    ->where('sender_type', 'customer')
                                    ->where('product_id', $item->product_id)
                                    ->whereNotNull('body')
                                    ->orderBy('created_at', 'desc')
                                    ->first();
                                if ($textMsg) {
                                    $bodyText = trim(str_replace('[DESIGN]', '', $textMsg->body ?? ''));
                                    $item->custom_design_comments = ($bodyText !== '' && $bodyText !== 'None') ? $bodyText : null;
                                }
                            }
                        } catch (\Throwable $itemError) {
                            Log::error('Error processing order item', [
                                'order_id' => $order->order_id,
                                'product_id' => $item->product_id,
                                'error' => $itemError->getMessage()
                            ]);
                            $item->custom_design_image = null;
                            $item->custom_design_comments = null;
                        }
                    }
                }
            }

            return response()->json(['orders' => $orders]);
        } catch (\Throwable $e) {
            Log::error('getPendingStaffValidation error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get orders in CS review queue.
     * Returns all "To Process" orders that contain customizable products.
     */
    public function getCSQueue(): JsonResponse
    {
        try {
            $orders = OrdersModel::with(['user', 'orderDetails.product', 'artist'])
                ->where(function($q) {
                    // Include normal "To Process" orders and also orders that currently have no assigned artist
                    $q->where('status', 'To Process')
                      ->orWhereNull('artist_id');
                })
                ->orderBy('updated_at', 'asc')
                ->get()
                ->filter(function ($order) {
                    return $order->orderDetails->contains(function ($item) {
                        return $item->product && $item->product->is_customizable;
                    });
                })
                ->values();

            foreach ($orders as $order) {
                foreach ($order->orderDetails as $item) {
                    if ($item->product && $item->product->is_customizable) {
                        try {
                            $designMsg = Message::where('sender_id', $order->user_id)
                                ->where('sender_type', 'customer')
                                ->where('product_id', $item->product_id)
                                ->whereNotNull('image')
                                ->orderBy('created_at', 'desc')
                                ->first();

                            if ($designMsg) {
                                $item->custom_design_image = $designMsg->image;
                                $bodyText = trim(str_replace('[DESIGN]', '', $designMsg->body ?? ''));
                                $item->custom_design_comments = ($bodyText !== '' && $bodyText !== 'None') ? $bodyText : null;
                            } else {
                                $textMsg = Message::where('sender_id', $order->user_id)
                                    ->where('sender_type', 'customer')
                                    ->where('product_id', $item->product_id)
                                    ->whereNotNull('body')
                                    ->orderBy('created_at', 'desc')
                                    ->first();
                                if ($textMsg) {
                                    $bodyText = trim(str_replace('[DESIGN]', '', $textMsg->body ?? ''));
                                    $item->custom_design_comments = ($bodyText !== '' && $bodyText !== 'None') ? $bodyText : null;
                                }
                            }
                        } catch (\Throwable $itemError) {
                            Log::error('Error processing CS queue order item', [
                                'order_id' => $order->order_id,
                                'product_id' => $item->product_id,
                                'error' => $itemError->getMessage()
                            ]);
                            $item->custom_design_image = null;
                            $item->custom_design_comments = null;
                        }
                    }
                }
            }

            return response()->json(['orders' => $orders]);
        } catch (\Throwable $e) {
            Log::error('getCSQueue error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
