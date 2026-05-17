<?php

namespace App\Http\Controllers;

use App\Services\ReviewService;
use App\Http\Requests\ReviewRequest;
use Illuminate\Http\JsonResponse;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    protected ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * GET /api/reviews - Only admins can list all reviews
     */
    public function index()
    {
        try {
            $reviews = Review::with(['user', 'product', 'inquiry'])
                ->where('status', 'visible')           // Only show approved reviews
                ->latest()
                ->take(10)                             // Limit to latest 10
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load reviews'
            ], 500);
        }
    }

    /**
     * POST /api/reviews - Customer submits a review (already protected by auth, no admin check)
     */
    public function store(ReviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = auth()->id();

        $review = $this->reviewService->createReview($validated);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully for this product.',
            'data' => $review->load(['user', 'product', 'order']),
        ], 201);
    }

    /**
     * PATCH /api/reviews/{review}/reply - Only admins can reply
     */
    public function reply(Request $request, Review $review): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json(['message' => 'Unauthorized – admin only'], 403);
        }

        $request->validate([
            'reply' => 'required|string|max:1000',
        ]);

        $updated = $this->reviewService->replyToReview($review, $request->reply);

        return response()->json([
            'success' => true,
            'message' => 'Reply added successfully.',
            'data' => $updated->fresh(),
        ]);
    }

    /**
     * PATCH /api/reviews/{review}/toggle-status - Only admins can toggle
     */
    public function toggleStatus(Review $review): JsonResponse
    {
        if (!$this->isAdmin()) {
            return response()->json(['message' => 'Unauthorized – admin only'], 403);
        }

        $updated = $this->reviewService->toggleReviewStatus($review);

        return response()->json([
            'success' => true,
            'message' => 'Review status updated.',
            'data' => $updated->fresh(),
        ]);

    }

    private function isAdmin(): bool
    {
        $user = auth()->user();
        if (!$user)
            return false;
        return isset($user->admin_id) || !empty($user->is_admin);
    }
}