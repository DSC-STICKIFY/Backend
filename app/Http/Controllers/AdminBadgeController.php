<?php

namespace App\Http\Controllers;

use App\Models\OrdersModel;
use App\Models\ReturnRefundModel;
use App\Models\Inquiry;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

class AdminBadgeController extends Controller
{
    public function getCounts(): JsonResponse
    {
        // 1. Orders Badge: New Pending orders
        $ordersCount = OrdersModel::where('status', 'Pending')->count();

        // 2. Return/Refund Badge: Pending return/refund requests
        $returnsCount = ReturnRefundModel::where('status', 'pending')->count();

        // 3. Inquiries Badge: Pending inquiries
        $inquiriesCount = Inquiry::where('status', 'pending')->count();

        // 4. Artists Badge: Unread messages from Artists AND Orders Awaiting Shipment Approval
        $artistMessagesCount = Message::where('sender_type', 'artist')->where('is_read', false)->count();
        $artistShipmentCount = OrdersModel::where('status', 'Awaiting Shipment Approval')->count();
        $artistsCount = $artistMessagesCount + $artistShipmentCount;

        // 5. Inbox Badge: Unread messages from customers
        $inboxCount = Message::where('sender_type', 'user')->where('is_read', false)->count();

        return response()->json([
            'success' => true,
            'counts' => [
                'orders' => $ordersCount,
                'returns' => $returnsCount,
                'inquiries' => $inquiriesCount,
                'artists' => $artistsCount,
                'inbox' => $inboxCount,
            ]
        ]);
    }
}
