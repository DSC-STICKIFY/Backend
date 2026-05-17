<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // Importante ni!
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

   public function broadcastOn(): array
{
    $customerTypes = ['customer', 'user'];
    
    $customerId = in_array($this->message->sender_type, $customerTypes)
        ? $this->message->sender_id    // customer sent it
        : $this->message->receiver_id; // admin/subadmin sent it → receiver is customer

    return [
        new PrivateChannel('chat.' . $customerId),
    ];
}

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id'          => $this->message->id,
                'body'        => $this->message->body,
                'sender_id'   => $this->message->sender_id,
                'receiver_id' => $this->message->receiver_id,
                'product_id'  => $this->message->product_id,
                'sender_type' => $this->message->sender_type,
                'image'       => $this->message->image,
                'video'       => $this->message->video,
                'created_at'  => $this->message->created_at,
            ]
        ];
    }
}