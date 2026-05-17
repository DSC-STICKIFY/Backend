<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\AdminModel;
use App\Models\SubAdminModel;
use App\Models\Message;
use App\Models\ProductsModel;
use App\Models\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class MessageSystemService
{
    /**
     * ✅ CHECK IF ADMIN OR SUBADMIN (supports UserModel with is_admin=1)
     */
    private function isAdmin($user): bool
    {
        if (!$user) return false;

        if ($user instanceof AdminModel || $user instanceof SubAdminModel) {
            return true;
        }

        if (!empty($user->is_admin) && (int)$user->is_admin === 1) {
            return true;
        }

        if (!empty($user->role) && in_array($user->role, ['admin', 'subadmin', 'artist'])) {
            return true;
        }

        return false;
    }

    /**
     * ✅ GET SENDER ID + SENDER TYPE – FIXED for admin via UserModel
     */
    private function getSenderInfo($user): array
    {
        if (!$user) return ['id' => null, 'type' => 'guest'];

        $role = $user->role ?? null;
        if (!$role && !empty($user->is_admin)) { $role = 'admin'; }

        if ($role && in_array(strtolower($role), ['admin', 'subadmin', 'artist', 'staff'])) {
            $role = strtolower($user->role ?? '');
            if ($role === 'artist' || $role === 'staff') return ['id' => $user->getKey(), 'type' => 'artist'];
            if ($role === 'admin') return ['id' => $user->getKey(), 'type' => 'admin'];
            if ($role === 'subadmin') return ['id' => $user->getKey(), 'type' => 'subadmin'];
        }

        return ['id' => $user->getKey(), 'type' => 'customer'];
    }

    /**
     * =========================================================
     * SEND MESSAGE (Fixed sender_type for admin via UserModel)
     * =========================================================
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'body'        => 'nullable|string|max:1000',
            'receiver_id' => 'nullable|integer',
            'product_id'  => 'nullable|integer',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'video'       => 'nullable|file|mimes:mp4,mov,webm|max:102400',
        ]);

        $user = Auth::user();
        $isAdmin = $this->isAdmin($user);
        $senderInfo = $this->getSenderInfo($user);

        $isBot = $request->input('is_bot', false);

        if ($isBot) {
            // Bot messages act as Admin messages to the user
            $admin = AdminModel::first();
            $senderId = $admin ? $admin->admin_id : 1;
            $senderType = 'admin';
            $receiverId = $user->user_id ?? $user->id;
        } else {
            $senderId = $senderInfo['id'];
            $senderType = $senderInfo['type'];

            if ($isAdmin) {
                // Admin / SubAdmin sending to Customer
                if (empty($validated['receiver_id'])) {
                    throw new InvalidArgumentException('receiver_id is required for admin/subadmin.');
                }
                $receiverId = (int) $validated['receiver_id'];
            } else {
                // Customer sending to Admin or Assigned Artist
                $productId = $validated['product_id'] ?? null;
                $receiverId = null;

                if ($productId) {
                    // Try to find an active order for this product to see if an artist is assigned
                    $order = \App\Models\OrdersModel::where('user_id', $senderId)
                        ->whereHas('orderDetails', function($q) use ($productId) {
                            $q->where('product_id', $productId);
                        })
                        ->whereNotNull('artist_id')
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($order) {
                        $receiverId = $order->artist_id;
                    }
                }

                if (!$receiverId) {
                    $admin = AdminModel::first();
                    if (!$admin) {
                        throw new InvalidArgumentException('No admin found in the system.');
                    }
                    $receiverId = $admin->admin_id;
                }
            }
        }

        // File Uploads
        $imagePath = $request->file('image') 
            ? $request->file('image')->store('message_images', 'public') 
            : null;

        $videoPath = $request->file('video') 
            ? $request->file('video')->store('message_videos', 'public') 
            : null;

        $message = Message::create([
            'sender_id'   => $senderId,
            'receiver_id' => $receiverId,
            'product_id'  => $validated['product_id'] ?? null,
            'body'        => $validated['body'] ?? $request->input('message'), // support 'message' or 'body'
            'image'       => $imagePath,
            'video'       => $videoPath,
            'sender_type' => $senderType,
            'is_read'     => false,
            'is_bot'      => $isBot,
        ]);

        Log::info('Message Sent Successfully', [
            'from'        => $senderInfo['type'],
            'sender_id'   => $senderInfo['id'],
            'to'          => $receiverId,
            'body'        => $message->body,
            'is_admin'    => $isAdmin
        ]);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Exception $e) {
            Log::error('Pusher broadcast failed: ' . $e->getMessage());
            // We continue because the message is already saved in DB
        }

        return $message;
    }

    /**
     * =========================================================
     * GET CONVERSATIONS FOR SIDEBAR (Improved with both customer/user types)
     * =========================================================
     */
    public function getConversations()
    {
        $user = Auth::user();

        if (!$this->isAdmin($user)) {
            Log::warning('Blocked: Not admin/subadmin', ['user_id' => $user?->id]);
            return [];
        }

        // Fetch ALL messages involving customers
        $messages = Message::where(function ($query) {
                $query->whereIn('sender_type', ['customer', 'user'])
                      ->orWhereIn('sender_type', ['admin', 'subadmin', 'artist']);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('Conversations - Messages fetched', ['total' => $messages->count()]);

        if ($messages->isEmpty()) {
            return [];
        }

        // Group by (customer_id, product_id) → one row per product per customer
        $grouped = [];

        foreach ($messages as $msg) {
            if (in_array($msg->sender_type, ['customer', 'user'])) {
                $customerId = $msg->sender_id;
            } else {
                // If it's admin/subadmin/artist, the customer is the receiver
                $customerId = $msg->receiver_id;
            }

            if (!$customerId) continue;

            $productId = $msg->product_id;  // null = general (legacy) conversation
            $key = $customerId . '_' . ($productId ?? 'general');

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'customer_id' => $customerId,
                    'product_id'  => $productId,
                    'msgs'        => [],
                ];
            }
            $grouped[$key]['msgs'][] = $msg;
        }

        $conversations = [];

        foreach ($grouped as $entry) {
            $customerId = $entry['customer_id'];
            $productId  = $entry['product_id'];
            $msgs       = $entry['msgs'];

            $userModel = UserModel::where('user_id', $customerId)->first();
            if (!$userModel) continue;

            // Look up product name when product_id is set
            $productName = null;
            $productCategory = null;
            if ($productId) {
                $product = ProductsModel::where('product_id', $productId)->first();
                $productName     = $product?->product_name;
                $productCategory = $product?->product_category;
            }

            $lastMsg = collect($msgs)->sortByDesc('created_at')->first();

            $unreadCount = Message::where('sender_id', $customerId)
                ->whereIn('sender_type', ['customer', 'user'])
                ->when($productId, fn($q) => $q->where('product_id', $productId))
                ->when(!$productId, fn($q) => $q->whereNull('product_id'))
                ->where('is_read', false)
                ->count();

            $conversations[] = [
                'user_id'          => (int)$customerId,
                'product_id'       => $productId,
                'product_name'     => $productName,
                'product_category' => $productCategory,
                'user'             => $userModel,
                'unread_count'     => $unreadCount,
                'last_message'     => $lastMsg?->body ?: ($lastMsg?->image ? '📷 Image' : ($lastMsg?->video ? '🎥 Video' : '—')),
                'last_at'          => $lastMsg?->created_at,
            ];
        }

        // Sort by latest message descending
        usort($conversations, fn($a, $b) =>
            strcmp((string)($b['last_at'] ?? ''), (string)($a['last_at'] ?? ''))
        );

        Log::info('Final conversations count', ['count' => count($conversations)]);

        return array_values($conversations);
    }

    /**
     * =========================================================
     * GET MESSAGES BETWEEN ADMIN AND SPECIFIC USER
     * ✅ FIXED: Accepts both 'customer' and 'user' as customer types
     * =========================================================
     */
    public function getAdminUserMessages(int $userId)
    {
        $productId = request()->query('product_id');
        $lastId = request()->query('last_id');

        // Normalize product_id
        if ($productId === 'null' || $productId === '' || $productId === 'undefined' || $productId === '0') {
            $productId = null;
        }

        $messages = Message::where(function ($q) use ($userId, $productId, $lastId) {
                // Customer messages (both old 'user' and new 'customer' types)
                $q->where('sender_id', $userId)
                  ->whereIn('sender_type', ['customer', 'user']);
                
                if ($productId) $q->where('product_id', $productId);
                else $q->whereNull('product_id');

                if ($lastId) $q->where('id', '>', $lastId);
            })
            ->orWhere(function ($q) use ($userId, $productId, $lastId) {
                // Admin/Subadmin/Artist messages
                $q->where('receiver_id', $userId)
                  ->whereIn('sender_type', ['admin', 'subadmin', 'artist']);
                
                if ($productId) $q->where('product_id', $productId);
                else $q->whereNull('product_id');

                if ($lastId) $q->where('id', '>', $lastId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark unread customer messages as read
        Message::where('sender_id', $userId)
            ->whereIn('sender_type', ['customer', 'user'])
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $messages;
    }

    /**
     * =========================================================
     * GET MESSAGES FOR CUSTOMER SIDE (also supports both types)
     * =========================================================
     */
    public function getUserMessages()
    {
        $user = Auth::user();
        $userId = $this->getSenderInfo($user)['id'];
        $productId = request()->query('product_id');
        $lastId = request()->query('last_id');

        // Normalize product_id (handle 'null' string, empty string, or 'undefined' from frontend)
        if ($productId === 'null' || $productId === '' || $productId === 'undefined' || $productId === '0') {
            $productId = null;
        }

        $messages = Message::where(function ($q) use ($userId, $productId, $lastId) {
                $q->where('sender_id', $userId)
                  ->whereIn('sender_type', ['customer', 'user']);
                
                if ($productId) $q->where('product_id', $productId);
                else $q->whereNull('product_id');
                
                if ($lastId) $q->where('id', '>', $lastId);
            })
            ->orWhere(function ($q) use ($userId, $productId, $lastId) {
                $q->where('receiver_id', $userId)
                  ->whereIn('sender_type', ['admin', 'subadmin', 'artist']);
                
                if ($productId) $q->where('product_id', $productId);
                else $q->whereNull('product_id');

                if ($lastId) $q->where('id', '>', $lastId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        // ✅ ULTRA-CLEAN: Mark ALL unread messages for this user as read
        // No matter who sent it, if the user is the receiver and they view the inbox, it's read.
        Message::where('receiver_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $messages;
    }

    /**
     * =========================================================
     * UNREAD COUNT (CUSTOMER ONLY)
     * =========================================================
     */
    public function getUnreadCount()
    {
        $user = Auth::user();

        if ($this->isAdmin($user)) {
            return 0;
        }

        $userId = $this->getSenderInfo($user)['id'];

        return Message::where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}