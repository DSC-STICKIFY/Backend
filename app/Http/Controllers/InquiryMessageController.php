<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\InquiryMessage;
use Illuminate\Http\Request;

class InquiryMessageController extends Controller
{
    /**
     * Fetch all messages for a specific inquiry.
     */
    public function index($inquiryId)
    {
        $inquiry = Inquiry::findOrFail($inquiryId);

        // Fetch messages with relations preloaded
        $messages = InquiryMessage::with(['userSender', 'adminSender', 'subAdminSender', 'employeeSender'])
            ->where('inquiry_id', $inquiryId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Format sender names nicely
        $formatted = $messages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'inquiry_id' => $msg->inquiry_id,
                'sender_type' => $msg->sender_type,
                'sender_id' => $msg->sender_id,
                'sender_name' => $msg->sender_name,
                'message' => $msg->message,
                'created_at' => $msg->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Store a new inquiry message.
     */
    public function store(Request $request, $inquiryId)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $inquiry = Inquiry::findOrFail($inquiryId);

        // Resolve authenticated user from all possible guards
        $user = auth('admin_api')->user()
            ?? auth('subadmin_api')->user()
            ?? auth('artist_api')->user()
            ?? auth('staff_api')->user()
            ?? auth('sanctum')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Determine sender type and primary key
        $senderId = $user->id;
        if ($user instanceof \App\Models\AdminModel) {
            $senderType = 'admin';
            $senderId = $user->admin_id;
        } elseif ($user instanceof \App\Models\SubAdminModel) {
            $senderType = 'subadmin';
            $senderId = $user->sub_admin_id;
        } elseif ($user instanceof \App\Models\EmployeeModel) {
            $senderType = $user->role; // 'artist', 'customer_service', 'staff'
            $senderId = $user->employee_id;
        } else {
            $senderType = 'user';
            $senderId = $user->user_id;
        }

        $message = InquiryMessage::create([
            'inquiry_id' => $inquiryId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $request->message,
        ]);

        // Format response
        $formatted = [
            'id' => $message->id,
            'inquiry_id' => $message->inquiry_id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender_name,
            'message' => $message->message,
            'created_at' => $message->created_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $formatted
        ], 201);
    }
}
