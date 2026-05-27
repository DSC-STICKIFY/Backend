<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;

class PromotionAnalyticsController extends Controller
{
    public function show(Promotion $promotion)
    {
        $totalSent  = $promotion->logs()->sum('total_recipients');
        $successful = $promotion->logs()->sum('successful_sends');
        $failed     = $promotion->logs()->sum('failed_sends');
        $rate       = $totalSent ? round(($successful / $totalSent) * 100, 2) : 0;

        return response()->json([
            'promotion' => $promotion,
            'analytics' => [
                'total_sent'          => (int) $totalSent,
                'successful_sends'    => (int) $successful,
                'failed_sends'        => (int) $failed,
                'success_rate_percent' => $rate,
            ],
        ]);
    }
}
