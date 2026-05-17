<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerInquiryController extends Controller
{
    /**
     * List all inquiries for the authenticated customer.
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user) return response()->json([], 401);

        $inquiries = Inquiry::with('review')->where(function($query) use ($user) {
                $query->where('user_id', $user->getKey())
                      ->orWhere('email', $user->email);
            })
            ->latest()
            ->get();
            
        return response()->json(['data' => $inquiries]);
    }

    /**
     * Show a specific inquiry.
     */
    public function show($id)
    {
        $user = Auth::user();
        $inquiry = Inquiry::where(function($query) use ($user) {
                $query->where('user_id', $user->getKey())
                      ->orWhere('email', $user->email);
            })->findOrFail($id);
            
        return response()->json(['data' => $inquiry]);
    }

    /**
     * Accept a quotation.
     */
    public function acceptQuotation($id)
    {
        $user = Auth::user();
        $inquiry = Inquiry::where(function($query) use ($user) {
                $query->where('user_id', $user->getKey())
                      ->orWhere('email', $user->email);
            })
            ->where('status', 'quoted')
            ->findOrFail($id);

        $inquiry->status = 'approved';
        $inquiry->save();

        return response()->json([
            'message' => 'Quotation accepted successfully. Waiting for admin to schedule.',
            'data' => $inquiry
        ]);
    }

    /**
     * Decline a quotation.
     */
    public function declineQuotation(Request $request, $id)
    {
        $user = Auth::user();
        $inquiry = Inquiry::where(function($query) use ($user) {
                $query->where('user_id', $user->getKey())
                      ->orWhere('email', $user->email);
            })
            ->where('status', 'quoted')
            ->findOrFail($id);

        $inquiry->status = 'rejected';
        $inquiry->rejection_reason = $request->reason ?? 'Customer declined the quotation.';
        $inquiry->save();

        return response()->json([
            'message' => 'Quotation declined.',
            'data' => $inquiry
        ]);
    }

    /**
     * Submit a review for a completed inquiry.
     */
    public function submitReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
        ]);

        $user = Auth::user();
        $inquiry = Inquiry::where(function($query) use ($user) {
                $query->where('user_id', $user->getKey())
                      ->orWhere('email', $user->email);
            })
            ->where('status', 'completed')
            ->findOrFail($id);

        $review = \App\Models\Review::create([
            'inquiry_id' => $id,
            'user_id' => $user->getKey(),
            'rating' => $request->rating,
            'comment' => $request->comment,
            'status' => 'visible'
        ]);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'data' => $review
        ]);
    }
}
