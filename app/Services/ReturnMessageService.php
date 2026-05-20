<?php

namespace App\Services;

use App\Models\ReturnMessageModel;
use Illuminate\Validation\ValidationException;

class ReturnMessageService
{
    public function addMessage($returnId, string $message)
    {
        $authUser = auth()->user() ?? auth('admin')->user();

        if (!$authUser) {
            throw ValidationException::withMessages(['auth' => 'You must be logged in.']);
        }

        $senderType = $this->getSenderType($authUser);
        $senderId = $this->getSenderId($authUser);

        $returnMessage = ReturnMessageModel::create([
            'return_id' => $returnId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $message,
        ]);

        $returnMessage->load(['userSender', 'adminSender', 'subAdminSender']);

        // Attach unified sender
        $returnMessage->sender = $returnMessage->sender_type === 'admin'
            ? ($returnMessage->adminSender ?? $returnMessage->subAdminSender)
            : $returnMessage->userSender;

        return $returnMessage;
    }

    public function getMessages($returnId)
    {
        return ReturnMessageModel::where('return_id', $returnId)
            ->with([
                'userSender:user_id,first_name,last_name',
                'adminSender:admin_id,first_name,last_name',
                'subAdminSender:sub_admin_id,first_name,last_name',
            ])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($msg) {
                $msg->sender = $msg->sender_type === 'admin'
                    ? ($msg->adminSender ?? $msg->subAdminSender)
                    : $msg->userSender;
                return $msg;
            });
    }

    private function getSenderType($user): string
    {
        if ($user instanceof \App\Models\AdminModel || $user->getTable() === 'admin_table' ||
            $user instanceof \App\Models\SubAdminModel || $user->getTable() === 'sub_admin_table') {
            return 'admin';
        }
        return 'user';
    }

    private function getSenderId($user)
    {
        return $user->admin_id ?? $user->sub_admin_id ?? $user->user_id ?? $user->id;
    }
}