<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPromotionEmailsJob;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionQueueController extends Controller
{
    /**
     * Return promotions grouped by status for the Customer Service dashboard.
     */
    public function index()
    {
        $grouped = Promotion::with(['products', 'categories', 'types'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('status')
            ->map(fn($c) => $c->values());
        return response()->json($grouped);
    }

    /**
     * Mark a promotion as reviewed and ready to send.
     */
    public function review(Promotion $promotion)
    {
        if ($promotion->status !== 'pending_review') {
            return response()->json(['msg' => 'Only pending promotions can be reviewed'], 422);
        }
        $promotion->update(['status' => 'ready_to_send']);
        return response()->json(['msg' => 'Promotion ready for sending']);
    }

    /**
     * Dispatch the email batch job.
     */
    public function send(Promotion $promotion, Request $request)
    {
        if ($promotion->status !== 'ready_to_send') {
            return response()->json(['msg' => 'Promotion must be ready_to_send first'], 422);
        }
        $userId = \Auth::id() ?: ($request->user()?->user_id);
        SendPromotionEmailsJob::dispatch($promotion, $userId);
        $promotion->update(['status' => 'sent']);
        return response()->json(['msg' => 'Email batch dispatched']);
    }

    /**
     * Cancel a promotion.
     */
    public function cancel(Promotion $promotion)
    {
        if (in_array($promotion->status, ['sent'])) {
            return response()->json(['msg' => 'Cannot cancel a sent promotion'], 422);
        }
        $promotion->update(['status' => 'cancelled']);
        return response()->json(['msg' => 'Promotion cancelled']);
    }
}
