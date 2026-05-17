<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class RefundProcessed extends Notification implements ShouldQueue
{
    use Queueable;

    protected $returnRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct($returnRequest)
    {
        $this->returnRequest = $returnRequest;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->returnRequest->refund_amount, 2);
        $orderNumber = $this->returnRequest->order->order_number ?? 'N/A';
        $productName = $this->returnRequest->product_name;

        $mail = (new MailMessage)
            ->subject('Refunded - Order #' . $orderNumber)
            ->greeting('Hello ' . $notifiable->first_name . '!')
            ->line('Your refund for "' . $productName . '" has been refunded.')
            ->line('Refund Amount: ₱' . $amount);

        if ($this->returnRequest->paymongo_refund_id) {
            $mail->line('Refund Method: Automatic (via PayMongo)')
                 ->line('Refund Reference: ' . $this->returnRequest->paymongo_refund_id)
                 ->line('Note: The funds will be credited back to your original payment method (GCash) within 3-7 business days.');
        } else {
            $mail->line('Refund Method: Manual (via GCash)')
                 ->line('The admin has sent the refund to your provided GCash number.');
        }

        return $mail->action('View Order Details', config('app.frontend_url') . '/orders/' . $this->returnRequest->order_id)
            ->line('Thank you for choosing DSC Printing Services!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'return_id' => $this->returnRequest->id,
            'order_id' => $this->returnRequest->order_id,
            'product_name' => $this->returnRequest->product_name,
            'amount' => $this->returnRequest->refund_amount,
            'status' => 'refunded',
            'message' => "Your refund of ₱" . number_format($this->returnRequest->refund_amount, 2) . " for " . $this->returnRequest->product_name . " has been refunded.",
            'created_at' => now(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'title' => 'Refunded',
            'message' => "Your refund for " . $this->returnRequest->product_name . " has been refunded.",
            'amount' => $this->returnRequest->refund_amount,
            'order_id' => $this->returnRequest->order_id,
        ]);
    }
}
