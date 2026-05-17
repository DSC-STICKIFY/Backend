<?php

namespace App\Services;

use App\Models\Review;
use App\Models\OrdersModel;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function getAllReviews($perPage = 20)
    {
        return Review::with(['user', 'product', 'order'])
            ->latest()
            ->paginate($perPage);
    }

    public function createReview(array $data)
{
    $orderId     = $data['order_id'];
    $orderItemId = $data['order_details_id'] ?? null;

    // All validation checks (these don't write, so they're fine outside)
    $this->ensureOrderBelongsToUser($orderId, $data['user_id']);
    $this->ensureOrderItemIsCompleted($orderId, $orderItemId);
    $this->ensureProductBelongsToOrderItem($orderId, $orderItemId, $data['product_id']);

    $exists = Review::where('order_details_id', $orderItemId)->exists();
    if ($exists) {
        throw ValidationException::withMessages([
            'order_details_id' => 'You have already reviewed this item.',
        ]);
    }

    return DB::transaction(function () use ($data, $orderId, $orderItemId) {
        $review = Review::create([
            'order_id'         => $orderId,
            'order_details_id' => $orderItemId,
            'user_id'          => $data['user_id'],
            'product_id'       => $data['product_id'],
            'rating'           => $data['rating'],
            'comment'          => $data['comment'] ?? null,
            'artist_rating'    => $data['artist_rating'] ?? null,
            'artist_comment'   => $data['artist_comment'] ?? null,
            'rider_rating'     => $data['rider_rating'] ?? null,
            'rider_comment'    => $data['rider_comment'] ?? null,
        ]);

        DB::table('orders_details_table')
            ->where('order_details_id', $orderItemId)
            ->update(['has_review' => true]);

        $this->updateOrderReviewStatus($orderId);

        return $review;
    });
}

    public function replyToReview(Review $review, string $reply)
    {
        $review->update(['admin_reply' => $reply]);
        return $review;
    }

    public function toggleReviewStatus(Review $review)
    {
        // Toggle between visible (public) and hidden (removed from testimonials)
        $nextStatus = $review->status === 'visible' ? 'hidden' : 'visible';
        $review->status = $nextStatus;
        $review->save();
        return $review;
    }

    public function isOrderFullyReviewed($orderId): bool
    {
        $totalItems = DB::table('orders_details_table')
            ->where('order_id', $orderId)
            ->count();

        if ($totalItems === 0) return false;

        $reviewedItems = DB::table('orders_details_table')
            ->where('order_id', $orderId)
            ->where('has_review', true)
            ->count();

        return $reviewedItems >= $totalItems;
    }

    public function updateOrderReviewStatus($orderId)
    {
        $order = OrdersModel::find($orderId);
        if (!$order) return;

        $fullyReviewed = $this->isOrderFullyReviewed($orderId);
        $order->has_review = $fullyReviewed;
        $order->save();
    }

    private function ensureOrderBelongsToUser($orderId, $userId)
    {
        $order = OrdersModel::find($orderId);
        if (!$order || $order->user_id != $userId) {
            throw ValidationException::withMessages([
                'order_id' => 'You can only review your own orders.',
            ]);
        }
    }

    private function ensureOrderItemIsCompleted($orderId, $orderItemId)
    {
        if (!$orderItemId) {
            throw ValidationException::withMessages([
                'order_details_id' => 'Order item ID is required.',
            ]);
        }

        $item = DB::table('orders_details_table')
            ->where('order_id', $orderId)
            ->where('order_details_id', $orderItemId)
            ->first();

        if (!$item) {
            throw ValidationException::withMessages([
                'order_details_id' => 'Order item not found.',
            ]);
        }

        $current = strtolower(trim($item->status ?? ''));
        $allowed = ['completed', 'delivered', 'received', 'to receive', 'to_receive'];

        if (!in_array($current, $allowed)) {
            throw ValidationException::withMessages([
                'order_details_id' => 'You can only review items that have been delivered or completed.',
            ]);
        }
    }

    private function ensureProductBelongsToOrderItem($orderId, $orderItemId, $productId)
    {
        $exists = DB::table('orders_details_table')
            ->where('order_id', $orderId)
            ->where('order_details_id', $orderItemId)
            ->where('product_id', $productId)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'product_id' => 'This product is not part of the selected order item.',
            ]);
        }
    }
}