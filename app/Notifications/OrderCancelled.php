<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Models\OrdersModel;

class OrderCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    protected $order;
    protected $orderDetailsId;
    protected $cancelReason;

    /**
     * Create a new notification instance.
     */
    public function __construct(OrdersModel $order, ?int $orderDetailsId = null, ?string $cancelReason = null)
    {
        $this->order = $order;
        $this->orderDetailsId = $orderDetailsId;
        $this->cancelReason = $cancelReason ?? 'No reason provided';
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
        $orderNumber = $this->order->order_number ?? 'N/A';
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $orderUrl = $frontendUrl . '/customer/orders';

        $mail = (new MailMessage)
            ->greeting('Hello ' . $notifiable->first_name . '!');

        if ($this->orderDetailsId) {
            // Find the specific item
            $item = $this->order->orderDetails->firstWhere('order_details_id', $this->orderDetailsId);
            $productName = $item->product?->product_name ?? 'Product';
            
            $mail->subject('⚠️ Item Cancelled - Order #' . $orderNumber)
                 ->line('We regret to inform you that an item in your order has been cancelled.')
                 ->line('**Cancelled Item:** ' . $productName)
                 ->line('**Reason for Cancellation:** ' . $this->cancelReason)
                 ->line('**Updated Order Total:** ₱' . number_format($this->order->total_price, 2));
        } else {
            $mail->subject('❌ Order Cancelled - Order #' . $orderNumber)
                 ->line('We regret to inform you that your entire order has been cancelled.')
                 ->line('**Order Number:** #' . $orderNumber)
                 ->line('**Reason for Cancellation:** ' . $this->cancelReason);

            $isOnlinePayment = in_array(strtoupper($this->order->payment_method), ['GCASH', 'GRABPAY', 'PAYMONGO', 'CARD']);
            if ($isOnlinePayment) {
                if ($this->order->refund_status === 'Refund Initiated') {
                    $mail->line('**Refund Status:** Refund Initiated (via PayMongo)')
                         ->line('Note: The funds will be credited back to your original payment method within 3-7 business days.');
                } elseif ($this->order->refund_status === 'Refund Failed') {
                    $mail->line('**Refund Status:** Pending Manual Review')
                         ->line('Note: There was an issue processing the automatic refund. Our support team will reach out to you shortly to process your refund manually.');
                } else {
                    $mail->line('**Refund Status:** A refund will be processed back to your payment method.');
                }
            } else {
                $mail->line('**Payment Method:** Cash on Delivery (COD) - No payment was charged.');
            }
        }

        return $mail->action('View Your Orders', $orderUrl)
                    ->line('Thank you for choosing DSC Printing Services!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $orderNumber = $this->order->order_number ?? 'N/A';
        if ($this->orderDetailsId) {
            $item = $this->order->orderDetails->firstWhere('order_details_id', $this->orderDetailsId);
            $productName = $item->product?->product_name ?? 'Product';
            $message = "An item ({$productName}) in your order #{$orderNumber} has been cancelled.";
        } else {
            $message = "Your order #{$orderNumber} has been cancelled.";
        }

        return [
            'order_id' => $this->order->order_id,
            'order_number' => $orderNumber,
            'order_details_id' => $this->orderDetailsId,
            'status' => 'Cancelled',
            'cancel_reason' => $this->cancelReason,
            'message' => $message,
            'created_at' => now(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $orderNumber = $this->order->order_number ?? 'N/A';
        if ($this->orderDetailsId) {
            $item = $this->order->orderDetails->firstWhere('order_details_id', $this->orderDetailsId);
            $productName = $item->product?->product_name ?? 'Product';
            $message = "An item ({$productName}) in your order #{$orderNumber} has been cancelled.";
        } else {
            $message = "Your order #{$orderNumber} has been cancelled.";
        }

        return new BroadcastMessage([
            'title' => $this->orderDetailsId ? 'Item Cancelled' : 'Order Cancelled',
            'message' => $message,
            'order_id' => $this->order->order_id,
            'order_details_id' => $this->orderDetailsId,
        ]);
    }
}
