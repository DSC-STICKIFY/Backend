<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class InquiryStatusUpdated extends Notification
{
    use Queueable;

    protected $inquiry;

    public function __construct($inquiry)
    {
        $this->inquiry = $inquiry;
    }

    /**
     * Delivery channels.
     * Includes 'mail' for email notifications.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Route mail to the inquiry's own email address if the notifiable
     * (user model) does not have one or the user is a guest.
     */
    public function routeNotificationForMail(): string
    {
        return $this->inquiry->email
            ?? ($this->inquiry->user?->email ?? '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  EMAIL NOTIFICATION
    // ─────────────────────────────────────────────────────────────────────────
    public function toMail(object $notifiable): MailMessage
    {
        $inquiry     = $this->inquiry;
        $status      = strtolower($inquiry->status);
        $name        = $inquiry->customer_name
                        ?? trim(($notifiable->first_name ?? '') . ' ' . ($notifiable->last_name ?? ''))
                        ?: 'Valued Customer';
        $serviceType = ucwords(str_replace('_', ' ', $inquiry->service_type));
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        $inquiryUrl  = $frontendUrl . '/customer-inquiries';

        switch ($status) {

            // ── REVIEWED ──────────────────────────────────────────────────────
            case 'reviewed':
                $mail = (new MailMessage)
                    ->subject("👀 Inquiry Under Review – DSC Printing Services")
                    ->greeting("Hello, {$name}!")
                    ->line("We have received and are currently **reviewing** your inquiry for **{$serviceType}**.")
                    ->line("Our team will prepare a custom quotation for you shortly.");

                if ($inquiry->admin_message) {
                    $mail->line("**Note from Admin:** " . $inquiry->admin_message);
                }

                return $mail
                    ->line("You will receive another update as soon as the quotation is finalized.")
                    ->action("View Your Inquiry", $inquiryUrl)
                    ->line("Thank you for your patience!");

            // ── QUOTED ────────────────────────────────────────────────────────
            case 'quoted':
                $mail = (new MailMessage)
                    ->subject("💰 Your Quote is Ready – DSC Printing Services")
                    ->greeting("Hello, {$name}!")
                    ->line("Great news! We've reviewed your **{$serviceType}** inquiry and your custom quotation is now ready.")
                    ->line("**Quotation Amount:** ₱" . number_format($inquiry->quotation_amount ?? 0, 2));

                if ($inquiry->downpayment_amount) {
                    $mail->line("**Required Downpayment:** ₱" . number_format($inquiry->downpayment_amount, 2));
                }
                if ($inquiry->admin_message) {
                    $mail->line("**Message from Admin:** " . $inquiry->admin_message);
                }

                return $mail
                    ->line("Please log in to your account to review the quotation and proceed with the downpayment to lock in your appointment slot.")
                    ->action("Review Your Quote", $inquiryUrl)
                    ->line("Thank you for choosing DSC Printing Services!");

            // ── APPROVED ──────────────────────────────────────────────────────
            case 'approved':
                $mail = (new MailMessage)
                    ->subject("✅ Your Appointment is Approved – DSC Printing Services")
                    ->greeting("Hello, {$name}!")
                    ->line("Your **{$serviceType}** appointment has been **approved** by our team!");

                if ($inquiry->admin_message) {
                    $mail->line("**Admin Note:** " . $inquiry->admin_message);
                }

                return $mail
                    ->line("Our team will reach out to you shortly to finalize the installation schedule.")
                    ->line("Please ensure you are available and your vehicle is ready on the appointment date.")
                    ->action("View Your Appointment", $inquiryUrl)
                    ->line("We look forward to serving you!");

            // ── SCHEDULED ─────────────────────────────────────────────────────
            case 'scheduled':
                $mail = (new MailMessage)
                    ->subject("📅 Appointment Confirmed – DSC Printing Services")
                    ->greeting("Hello, {$name}!")
                    ->line("Your **{$serviceType}** installation appointment is now officially **confirmed and scheduled**!");

                if ($inquiry->schedule_date) {
                    $formatted = Carbon::parse($inquiry->schedule_date)->format('F j, Y \a\t g:i A');
                    $mail->line("**📅 Scheduled Date & Time:** {$formatted}");
                }
                if ($inquiry->admin_message) {
                    $mail->line("**Admin Note:** " . $inquiry->admin_message);
                }

                return $mail
                    ->line("**Please prepare the following before your appointment:**")
                    ->line("• Arrive at least 10 minutes before your scheduled time.")
                    ->line("• Ensure your vehicle is **clean and dry** prior to installation.")
                    ->line("• Remove any personal items from the vehicle interior if applicable.")
                    ->line("• Bring a valid ID and your payment receipt.")
                    ->action("View Appointment Details", $inquiryUrl)
                    ->line("We're excited to work on your vehicle. See you soon! 🚗");

            // ── IN PROGRESS ───────────────────────────────────────────────────
            case 'in_progress':
                $mail = (new MailMessage)
                    ->subject("🔧 Installation In Progress – DSC Printing Services")
                    ->greeting("Hello, {$name}!")
                    ->line("We want to let you know that the **{$serviceType}** installation for your vehicle has officially **started**!")
                    ->line("Our skilled team is currently working on your vehicle with the utmost care and precision.");

                if ($inquiry->admin_message) {
                    $mail->line("**Update from our Team:** " . $inquiry->admin_message);
                }

                return $mail
                    ->action("Track Your Inquiry", $inquiryUrl)
                    ->line("We will send you another notification once your vehicle is ready.");

            // ── COMPLETED ─────────────────────────────────────────────────────
            case 'completed':
                $mail = (new MailMessage)
                    ->subject("🎉 Installation Complete – Your Vehicle is Ready!")
                    ->greeting("Hello, {$name}!")
                    ->line("Fantastic news! Your **{$serviceType}** installation is now **complete** and your vehicle is ready for pickup.");

                if ($inquiry->admin_message) {
                    $mail->line("**Final Note from our Team:** " . $inquiry->admin_message);
                }

                return $mail
                    ->line("**After-Care Instructions — Please read carefully:**")
                    ->line("• Do **NOT** pressure wash or scrub the wrap/decal for at least **7 days** after installation.")
                    ->line("• Avoid parking in direct sunlight for extended periods during the first week.")
                    ->line("• Clean gently with a soft microfiber cloth and car-safe spray.")
                    ->line("• Do not use abrasive cleaners, wax, or polish near the edges.")
                    ->line("• Contact us immediately if you notice any lifting, bubbling, or peeling.")
                    ->line("We'd truly appreciate your feedback! It helps us serve you better. 🌟")
                    ->action("Leave a Review", $inquiryUrl)
                    ->line("Thank you for trusting DSC Printing Services!");

            // ── REJECTED ──────────────────────────────────────────────────────
            case 'rejected':
                $mail = (new MailMessage)
                    ->subject("📋 Update on Your Inquiry – DSC Printing Services")
                    ->greeting("Hello, {$name}!")
                    ->line("Thank you for submitting your **{$serviceType}** inquiry. After careful review, we regret that we are unable to process your request at this time.");

                if ($inquiry->rejection_reason) {
                    $mail->line("**Reason:** " . $inquiry->rejection_reason);
                }

                return $mail
                    ->line("Don't be discouraged! You're always welcome to resubmit with updated information. Here are some helpful tips:")
                    ->line("• Provide **clear, high-resolution** reference images.")
                    ->line("• Specify the **exact size and placement** of the decal/wrap.")
                    ->line("• Include any specific **color or design preferences**.")
                    ->line("• Make sure your **contact details** are accurate so we can reach you.")
                    ->action("Submit a New Inquiry", $frontendUrl)
                    ->line("We hope to hear from you again soon. Thank you for your understanding.");

            // ── DEFAULT (reviewed, pending, etc.) ────────────────────────────
            default:
                $mail = (new MailMessage)
                    ->subject("📝 Inquiry Status Update – DSC Printing Services")
                    ->greeting("Hello, {$name}!")
                    ->line("Your **{$serviceType}** inquiry status has been updated to **" . strtoupper($inquiry->status) . "**.");

                if ($inquiry->admin_message) {
                    $mail->line("**Message from Admin:** " . $inquiry->admin_message);
                }

                return $mail
                    ->action("View Inquiry", $inquiryUrl)
                    ->line("Thank you for choosing DSC Printing Services!");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  DATABASE NOTIFICATION
    // ─────────────────────────────────────────────────────────────────────────
    public function toArray(object $notifiable): array
    {
        $serviceType = ucwords(str_replace('_', ' ', $this->inquiry->service_type));

        $statusMessages = [
            'reviewed'    => "Your {$serviceType} inquiry is under review.",
            'quoted'      => "Your {$serviceType} quote is ready. Amount: ₱" . number_format($this->inquiry->quotation_amount ?? 0, 2),
            'approved'    => "Your {$serviceType} appointment has been approved!",
            'scheduled'   => "Your {$serviceType} appointment is confirmed for "
                              . ($this->inquiry->schedule_date
                                  ? Carbon::parse($this->inquiry->schedule_date)->format('M j, Y')
                                  : 'a scheduled date')
                              . ".",
            'in_progress' => "Your {$serviceType} installation has started! We'll notify you when it's done.",
            'completed'   => "Your {$serviceType} installation is complete! Your vehicle is ready. 🎉",
            'rejected'    => "Your {$serviceType} inquiry could not be processed. Please check the details.",
        ];

        $message = $statusMessages[$this->inquiry->status]
            ?? "Your {$serviceType} inquiry has been updated to " . strtoupper($this->inquiry->status) . ".";

        return [
            'inquiry_id'   => $this->inquiry->id,
            'service_type' => $this->inquiry->service_type,
            'status'       => $this->inquiry->status,
            'message'      => $message,
            'created_at'   => now(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  BROADCAST (REAL-TIME) NOTIFICATION
    // ─────────────────────────────────────────────────────────────────────────
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $serviceType = ucwords(str_replace('_', ' ', $this->inquiry->service_type));

        return new BroadcastMessage([
            'inquiry_id'   => $this->inquiry->id,
            'service_type' => $this->inquiry->service_type,
            'status'       => $this->inquiry->status,
            'message'      => "Your {$serviceType} inquiry #" . $this->inquiry->id . " is now " . strtoupper($this->inquiry->status) . ".",
        ]);
    }
}
