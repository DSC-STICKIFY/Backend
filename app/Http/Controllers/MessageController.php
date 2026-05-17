<?php

namespace App\Http\Controllers;

use App\Services\MessageSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    protected MessageSystemService $service;

    public function __construct(MessageSystemService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/messages → Customer side
     */
    public function getMessages(): JsonResponse
    {
        try {
            $messages = $this->service->getUserMessages();
            return response()->json([
                'success' => true,
                'messages' => $messages
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch messages',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/messages → Send message (both customer and admin)
     */
    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $message = $this->service->sendMessage($request);

            return response()->json([
                'success' => true,
                'message' => $message
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'details' => $e->errors()
            ], 422);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            Log::error('Message send failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/conversations → Admin sidebar
     */
    public function getConversations(): JsonResponse
    {
        try {
            $conversations = $this->service->getConversations();

            return response()->json([
                'success' => true,
                'conversations' => $conversations
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch conversations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/messages/{userId} → Admin view specific chat
     */
    public function getAdminUserMessages(int $userId): JsonResponse
    {
        try {
            $messages = $this->service->getAdminUserMessages($userId);

            return response()->json([
                'success' => true,
                'messages' => $messages
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch messages',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/messages/unread-count → Customer unread count
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $count = $this->service->getUnreadCount();
            return response()->json([
                'success' => true,
                'unread_count' => $count
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get unread count',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}