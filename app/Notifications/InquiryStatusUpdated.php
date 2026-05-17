<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class InquiryStatusUpdated extends Notification
{
    use Queueable;

    protected $inquiry;

    /**
     * Create a new notification instance.
     */
    public function __construct($inquiry)
    {
        $this->inquiry = $inquiry;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'inquiry_id' => $this->inquiry->id,
            'service_type' => $this->inquiry->service_type,
            'status' => $this->inquiry->status,
            'message' => "Your inquiry for " . str_replace('_', ' ', $this->inquiry->service_type) . " has been updated to " . strtoupper($this->inquiry->status) . ".",
            'created_at' => now(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'inquiry_id' => $this->inquiry->id,
            'status' => $this->inquiry->status,
            'message' => "Your inquiry #" . $this->inquiry->id . " has been updated.",
        ]);
    }
}
