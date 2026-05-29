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
    // ✅ All roles that are considered "staff" (not customers)
    private const STAFF_ROLES = ['admin', 'subadmin', 'artist', 'staff', 'customer_service'];

    /**
     * ✅ CHECK IF ADMIN OR STAFF ROLE
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

        // ✅ Added customer_service and staff
        if (!empty($user->role) && in_array(strtolower($user->role), self::STAFF_ROLES)) {
            return true;
        }

        return false;
    }

    /**
     * ✅ GET SENDER ID + SENDER TYPE
     */
    private function getSenderInfo($user): array
    {
        if (!$user) return ['id' => null, 'type' => 'guest'];

        $role = strtolower($user->role ?? '');
        if (!$role && !empty($user->is_admin)) { $role = 'admin'; }

        // ✅ Added customer_service and staff to staff role check
        if ($role && in_array($role, self::STAFF_ROLES)) {
            if ($role === 'admin')            return ['id' => $user->getKey(), 'type' => 'admin'];
            if ($role === 'subadmin')         return ['id' => $user->getKey(), 'type' => 'subadmin'];
            if ($role === 'artist')           return ['id' => $user->getKey(), 'type' => 'artist'];
            if ($role === 'staff')            return ['id' => $user->getKey(), 'type' => 'staff'];
            if ($role === 'customer_service') return ['id' => $user->getKey(), 'type' => 'customer_service'];
        }

        return ['id' => $user->getKey(), 'type' => 'customer'];
    }

    /**
     * =========================================================
     * SEND MESSAGE
     * =========================================================
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'body'                     => 'nullable|string|max:1000',
            'receiver_id'              => 'nullable|integer',
            'product_id'               => 'nullable|integer',
            'customization_request_id' => 'nullable|integer',
            'image'                    => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:10240',
            'video'                    => 'nullable|file|mimes:mp4,mov,webm|max:102400',
        ]);

        $user = Auth::user();
        $isAdmin = $this->isAdmin($user);
        $senderInfo = $this->getSenderInfo($user);

        $isBot = $request->input('is_bot', false);

        if ($isBot) {
            $admin = AdminModel::first();
            $senderId = $admin ? $admin->admin_id : 1;
            $senderType = 'admin';
            $receiverId = $user->user_id ?? $user->id;
        } else {
            $senderId = $senderInfo['id'];
            $senderType = $senderInfo['type'];

            if ($isAdmin) {
                if (empty($validated['receiver_id'])) {
                    throw new InvalidArgumentException('receiver_id is required for admin/staff.');
                }
                $receiverId = (int) $validated['receiver_id'];
            } else {
                $productId = $validated['product_id'] ?? null;
                $customizationId = $validated['customization_request_id'] ?? null;
                $receiverId = null;

                // Try to resolve receiver from customization request first
                if ($customizationId) {
                    $cr = \App\Models\CustomizationRequest::find($customizationId);
                    if ($cr && $cr->artist_id) {
                        $receiverId = $cr->artist_id;
                    }
                }

                // Fallback: resolve from standard orders
                if (!$receiverId && $productId) {
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

        $imagePath = $request->file('image')
            ? $request->file('image')->store('message_images', 'public')
            : null;

        $videoPath = $request->file('video')
            ? $request->file('video')->store('message_videos', 'public')
            : null;

        $message = Message::create([
            'sender_id'                => $senderId,
            'receiver_id'              => $receiverId,
            'product_id'               => $validated['product_id'] ?? null,
            'customization_request_id' => $validated['customization_request_id'] ?? null,
            'body'                     => $validated['body'] ?? $request->input('message') ?? '',
            'image'                    => $imagePath,
            'video'                    => $videoPath,
            'sender_type'              => $senderType,
            'is_read'                  => false,
            'is_bot'                   => $isBot,
        ]);

        Log::info('Message Sent Successfully', [
            'from'      => $senderInfo['type'],
            'sender_id' => $senderInfo['id'],
            'to'        => $receiverId,
            'body'      => $message->body,
            'is_admin'  => $isAdmin,
        ]);

        try {
            broadcast(new MessageSent($message))->toOthers();
        } catch (\Exception $e) {
            Log::error('Pusher broadcast failed: ' . $e->getMessage());
        }

        return $message;
    }

    /**
     * =========================================================
     * GET CONVERSATIONS FOR SIDEBAR
     * =========================================================
     */
    public function getConversations()
    {
        $user = Auth::user();

        if (!$this->isAdmin($user)) {
            Log::warning('Blocked: Not a staff role', ['user_id' => $user?->id, 'role' => $user?->role]);
            return [];
        }

        // ✅ Also include customer_service and staff as sender types in message queries
        $staffSenderTypes = ['admin', 'subadmin', 'artist', 'staff', 'customer_service'];

        $messages = Message::where(function ($query) use ($staffSenderTypes) {
                $query->whereIn('sender_type', ['customer', 'user'])
                      ->orWhereIn('sender_type', $staffSenderTypes);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('Conversations - Messages fetched', ['total' => $messages->count()]);

        if ($messages->isEmpty()) {
            return [];
        }

        $grouped = [];

        foreach ($messages as $msg) {
            if (in_array($msg->sender_type, ['customer', 'user'])) {
                $customerId = $msg->sender_id;
            } else {
                $customerId = $msg->receiver_id;
            }

            if (!$customerId) continue;

            $productId = $msg->product_id;
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

        usort($conversations, fn($a, $b) =>
            strcmp((string)($b['last_at'] ?? ''), (string)($a['last_at'] ?? ''))
        );

        Log::info('Final conversations count', ['count' => count($conversations)]);

        return array_values($conversations);
    }

    /**
     * =========================================================
     * GET MESSAGES BETWEEN ADMIN/STAFF AND SPECIFIC USER
     * =========================================================
     */
    public function getAdminUserMessages(int $userId)
    {
        $productId = request()->query('product_id');
        $customizationId = request()->query('customization_request_id');
        $lastId = request()->query('last_id');

        if ($productId === 'null' || $productId === '' || $productId === 'undefined' || $productId === '0') {
            $productId = null;
        }
        if ($customizationId === 'null' || $customizationId === '' || $customizationId === 'undefined' || $customizationId === '0') {
            $customizationId = null;
        }

        // ✅ Include customer_service and staff as valid reply sender types
        $staffSenderTypes = ['admin', 'subadmin', 'artist', 'staff', 'customer_service'];

        // When customization_request_id is provided, filter by it (isolated thread per custom request)
        $filterByContext = function ($q) use ($productId, $customizationId) {
            if ($customizationId) {
                $q->where('customization_request_id', $customizationId);
            } elseif ($productId) {
                $q->where('product_id', $productId)->whereNull('customization_request_id');
            } else {
                $q->whereNull('product_id')->whereNull('customization_request_id');
            }
        };

        $messages = Message::where(function ($q) use ($userId, $lastId, $filterByContext) {
                $q->where('sender_id', $userId)
                  ->whereIn('sender_type', ['customer', 'user']);
                $filterByContext($q);
                if ($lastId) $q->where('id', '>', $lastId);
            })
            ->orWhere(function ($q) use ($userId, $lastId, $staffSenderTypes, $filterByContext) {
                $q->where('receiver_id', $userId)
                  ->whereIn('sender_type', $staffSenderTypes);
                $filterByContext($q);
                if ($lastId) $q->where('id', '>', $lastId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        Message::where('sender_id', $userId)
            ->whereIn('sender_type', ['customer', 'user'])
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $messages;
    }

    /**
     * =========================================================
     * GET MESSAGES FOR CUSTOMER SIDE
     * =========================================================
     */
    public function getUserMessages()
    {
        $user = Auth::user();
        $userId = $this->getSenderInfo($user)['id'];
        $productId = request()->query('product_id');
        $customizationId = request()->query('customization_request_id');
        $lastId = request()->query('last_id');

        if ($productId === 'null' || $productId === '' || $productId === 'undefined' || $productId === '0') {
            $productId = null;
        }
        if ($customizationId === 'null' || $customizationId === '' || $customizationId === 'undefined' || $customizationId === '0') {
            $customizationId = null;
        }

        // ✅ Customer sees replies from all staff types including customer_service
        $staffSenderTypes = ['admin', 'subadmin', 'artist', 'staff', 'customer_service'];

        // When customization_request_id is provided, filter by it (isolated thread per custom request)
        $filterByContext = function ($q) use ($productId, $customizationId) {
            if ($customizationId) {
                $q->where('customization_request_id', $customizationId);
            } elseif ($productId) {
                $q->where('product_id', $productId)->whereNull('customization_request_id');
            } else {
                $q->whereNull('product_id')->whereNull('customization_request_id');
            }
        };

        $messages = Message::where(function ($q) use ($userId, $lastId, $filterByContext) {
                $q->where('sender_id', $userId)
                  ->whereIn('sender_type', ['customer', 'user']);
                $filterByContext($q);
                if ($lastId) $q->where('id', '>', $lastId);
            })
            ->orWhere(function ($q) use ($userId, $lastId, $staffSenderTypes, $filterByContext) {
                $q->where('receiver_id', $userId)
                  ->whereIn('sender_type', $staffSenderTypes);
                $filterByContext($q);
                if ($lastId) $q->where('id', '>', $lastId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

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