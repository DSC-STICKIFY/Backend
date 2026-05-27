<?php
namespace App\Mail;

use App\Models\Promotion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PromotionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Promotion $promotion) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->promotion->title ?? $this->promotion->name ?? 'Special Promotion',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.promotion_email',
            with: [
                'promotion' => $this->promotion,
            ],
        );
    }
}
